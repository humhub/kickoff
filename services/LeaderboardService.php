<?php

namespace humhub\modules\kickoff\services;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\ScoringScheme;
use humhub\modules\user\models\User;
use LogicException;
use Yii;
use yii\db\Expression;
use yii\db\Query;

class LeaderboardService
{
    private readonly Competition $competition;
    private readonly ScoringScheme $scheme;

    public function __construct(Competition $competition)
    {
        $scheme = $competition->scoringScheme;
        if ($scheme === null) {
            throw new LogicException("Competition #{$competition->id} has no scoring scheme.");
        }
        $this->competition = $competition;
        $this->scheme = $scheme;
    }

    /**
     * Returns the leaderboard rows ordered top to bottom.
     *
     * Each row: ['rank' => int, 'user' => User|null, 'total' => int, 'exact' => int, 'diff' => int].
     *
     * @return array<int, array{rank:int, user:?User, total:int, exact:int, diff:int}>
     */
    public function compute(?int $limit = null, int $offset = 0): array
    {
        $rows = $this->aggregateRows();

        // Rank computation is cheap (plain int compare on already-fetched
        // rows) and has to walk every row — competition ranking with ties
        // depends on the global order. We do it across all rows even when
        // only returning a slice, so a paginated request returns globally
        // correct ranks. What the slice lets us skip is the *expensive*
        // part: hydrating User objects for everyone (a 10k-row WHERE-IN +
        // model construction).
        $assembled = $this->assignRanks($rows);

        if ($offset > 0 || $limit !== null) {
            $assembled = array_slice($assembled, $offset, $limit);
        }

        $this->attachUsers($assembled);

        return $assembled;
    }

    /**
     * Returns the number of participations on this competition — what a
     * UI-side pagination needs to render the page count without forcing
     * the full leaderboard through PHP.
     */
    public function countParticipants(): int
    {
        return (int) (new Query())
            ->from('kickoff_participation')
            ->where(['competition_id' => $this->competition->id])
            ->count();
    }

    /**
     * Runs the heavy aggregation query but stops at raw rows — no User
     * objects, no rank assignment, no PHP iteration beyond what the DB
     * returns. Internal helper for compute() and findUserRank() to share.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aggregateRows(): array
    {
        // Matchday-winner bonuses are summed in via their own LEFT JOIN so a
        // scheme with `matchday_winner_points = 0` still works (no rows in
        // `kickoff_matchday_bonus` for that competition → contributes 0).
        $sql = <<<SQL
            SELECT
                p.user_id,
                COALESCE(t.total, 0) + COALESCE(sb.total, 0) + COALESCE(mb.total, 0) AS total,
                COALESCE(t.exact_count, 0) AS exact_count,
                COALESCE(t.diff_count, 0) AS diff_count,
                COALESCE(mb.total, 0) AS bonus_total,
                p.joined_at
            FROM kickoff_participation p
            LEFT JOIN (
                SELECT tip.user_id,
                       SUM(tip.points) AS total,
                       SUM(CASE WHEN tip.points = :exact THEN 1 ELSE 0 END) AS exact_count,
                       SUM(CASE WHEN tip.points = :diff THEN 1 ELSE 0 END) AS diff_count
                FROM kickoff_tip tip
                JOIN kickoff_game g ON g.id = tip.game_id
                WHERE g.competition_id = :comp AND tip.points IS NOT NULL
                GROUP BY tip.user_id
            ) t ON t.user_id = p.user_id
            LEFT JOIN (
                SELECT sbt.user_id, SUM(sbt.points) AS total
                FROM kickoff_special_bet_tip sbt
                JOIN kickoff_special_bet sb ON sb.id = sbt.special_bet_id
                WHERE sb.competition_id = :comp AND sbt.points IS NOT NULL
                GROUP BY sbt.user_id
            ) sb ON sb.user_id = p.user_id
            LEFT JOIN (
                SELECT user_id, SUM(points) AS total
                FROM kickoff_matchday_bonus
                WHERE competition_id = :comp
                GROUP BY user_id
            ) mb ON mb.user_id = p.user_id
            WHERE p.competition_id = :comp
            ORDER BY total DESC, exact_count DESC, diff_count DESC, p.joined_at ASC
        SQL;

        return Yii::$app->db->createCommand($sql, [
            ':comp' => $this->competition->id,
            ':exact' => $this->scheme->points_exact,
            ':diff' => $this->scheme->points_goal_diff,
        ])->queryAll();
    }

    /**
     * Walks the sorted aggregate rows and assigns competition ranks
     * (consecutive ties share a rank, then the next distinct (total, exact,
     * diff) triple skips ahead).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{rank:int, user:null, user_id:int, total:int, exact:int, diff:int, bonus:int}>
     */
    private function assignRanks(array $rows): array
    {
        $assembled = [];
        $rank = 0;
        $previousKey = null;
        foreach ($rows as $i => $row) {
            $total = (int) $row['total'];
            $exact = (int) $row['exact_count'];
            $diff = (int) $row['diff_count'];
            $key = "{$total}-{$exact}-{$diff}";
            $displayRank = $key === $previousKey ? $rank : $i + 1;
            $assembled[] = [
                'rank' => $displayRank,
                'user' => null,
                'user_id' => (int) $row['user_id'],
                'total' => $total,
                'exact' => $exact,
                'diff' => $diff,
                'bonus' => (int) ($row['bonus_total'] ?? 0),
            ];
            $rank = $displayRank;
            $previousKey = $key;
        }
        return $assembled;
    }

    /**
     * Hydrates User models in-place for the given assembled rows. Loading
     * happens with a single WHERE-IN over only the user ids we'll actually
     * return — paginating before this step is what makes a 10k-strong
     * leaderboard cheap to serve page-by-page.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function attachUsers(array &$rows): void
    {
        $userIds = [];
        foreach ($rows as $row) {
            $userIds[] = $row['user_id'];
        }
        if ($userIds === []) {
            return;
        }
        $users = User::find()->where(['id' => $userIds])->indexBy('id')->all();
        foreach ($rows as &$row) {
            $row['user'] = $users[$row['user_id']] ?? null;
            unset($row['user_id']);
        }
    }

    /**
     * Leaderboard restricted to a specific set of games (typically one matchday).
     * Special bet points are intentionally excluded — they belong to the Bonus tab.
     *
     * Ranks are computed across the full result set so a paginated slice
     * still reports globally correct ranks for the matchday.
     *
     * @param int[] $gameIds
     * @return array<int, array{rank:int, user:?User, total:int, exact:int, diff:int}>
     */
    public function computeForGames(array $gameIds, ?int $limit = null, int $offset = 0): array
    {
        if ($gameIds === []) {
            return [];
        }

        $rows = (new Query())
            ->select([
                'user_id',
                'total' => new Expression('SUM(points)'),
                'exact_count' => new Expression('SUM(CASE WHEN points = :exact THEN 1 ELSE 0 END)'),
                'diff_count' => new Expression('SUM(CASE WHEN points = :diff THEN 1 ELSE 0 END)'),
            ])
            ->from('kickoff_tip')
            ->where(['game_id' => $gameIds])
            ->andWhere(['IS NOT', 'points', null])
            ->groupBy('user_id')
            ->orderBy(['total' => SORT_DESC, 'exact_count' => SORT_DESC, 'diff_count' => SORT_DESC])
            ->addParams([
                ':exact' => $this->scheme->points_exact,
                ':diff' => $this->scheme->points_goal_diff,
            ])
            ->all();

        $leaderboard = [];
        $rank = 0;
        $previousKey = null;
        foreach ($rows as $i => $row) {
            $total = (int) $row['total'];
            $exact = (int) $row['exact_count'];
            $diff = (int) $row['diff_count'];
            $key = "{$total}-{$exact}-{$diff}";
            $displayRank = $key === $previousKey ? $rank : $i + 1;
            $leaderboard[] = [
                'rank' => $displayRank,
                'user' => null,
                'user_id' => (int) $row['user_id'],
                'total' => $total,
                'exact' => $exact,
                'diff' => $diff,
            ];
            $rank = $displayRank;
            $previousKey = $key;
        }

        if ($offset > 0 || $limit !== null) {
            $leaderboard = array_slice($leaderboard, $offset, $limit);
        }

        $userIds = array_map(fn($r) => $r['user_id'], $leaderboard);
        $users = $userIds === [] ? [] : User::find()->where(['id' => $userIds])->indexBy('id')->all();
        foreach ($leaderboard as &$row) {
            $row['user'] = $users[$row['user_id']] ?? null;
            unset($row['user_id']);
        }

        return $leaderboard;
    }

    /**
     * Number of unique participants who have a scored tip in any of the
     * given games — companion to computeForGames() for pagination.
     *
     * @param int[] $gameIds
     */
    public function countParticipantsForGames(array $gameIds): int
    {
        if ($gameIds === []) {
            return 0;
        }
        return (int) (new Query())
            ->from('kickoff_tip')
            ->where(['game_id' => $gameIds])
            ->andWhere(['IS NOT', 'points', null])
            ->select('COUNT(DISTINCT user_id)')
            ->scalar();
    }

    /**
     * Leaderboard restricted to resolved special-bet tips for this competition.
     * Match-tip points are excluded.
     *
     * @return array<int, array{rank:int, user:?User, total:int}>
     */
    public function computeForSpecialBets(?int $limit = null): array
    {
        $rows = (new Query())
            ->select([
                'sbt.user_id',
                'total' => new Expression('SUM(sbt.points)'),
            ])
            ->from('kickoff_special_bet_tip sbt')
            ->innerJoin('kickoff_special_bet sb', 'sb.id = sbt.special_bet_id')
            ->where(['sb.competition_id' => $this->competition->id])
            ->andWhere(['IS NOT', 'sbt.points', null])
            ->groupBy('sbt.user_id')
            ->orderBy(['total' => SORT_DESC])
            ->all();

        $userIds = array_column($rows, 'user_id');
        $users = $userIds === [] ? [] : User::find()->where(['id' => $userIds])->indexBy('id')->all();

        $leaderboard = [];
        $rank = 0;
        $previousTotal = null;
        foreach ($rows as $i => $row) {
            $total = (int) $row['total'];
            $displayRank = $total === $previousTotal ? $rank : $i + 1;
            $leaderboard[] = [
                'rank' => $displayRank,
                'user' => $users[$row['user_id']] ?? null,
                'total' => $total,
            ];
            $rank = $displayRank;
            $previousTotal = $total;
            if ($limit !== null && count($leaderboard) >= $limit) {
                break;
            }
        }
        return $leaderboard;
    }

    /**
     * Returns the user's row in the full overall leaderboard, or null if not
     * ranked. Two-query path that skips materializing the whole leaderboard
     * — for a 10k-participation competition this is the difference between
     * "load 10k aggregate rows + 10k User objects every page view" and
     * "two indexed lookups". Window functions would make it one query but
     * those need MySQL 8.0, and we still support 5.7.
     *
     * @return array{rank:int, user:?User, total:int, exact:int, diff:int, bonus:int}|null
     */
    public function findUserRank(int $userId): ?array
    {
        $self = $this->aggregateOne($userId);
        if ($self === null) {
            return null;
        }

        $total = (int) $self['total'];
        $exact = (int) $self['exact_count'];
        $diff = (int) $self['diff_count'];

        $rankOffset = (int) Yii::$app->db->createCommand(<<<SQL
            SELECT COUNT(*) FROM (
                SELECT
                    p.user_id,
                    COALESCE(t.total, 0) + COALESCE(sb.total, 0) + COALESCE(mb.total, 0) AS total,
                    COALESCE(t.exact_count, 0) AS exact_count,
                    COALESCE(t.diff_count, 0) AS diff_count
                FROM kickoff_participation p
                LEFT JOIN (
                    SELECT tip.user_id,
                           SUM(tip.points) AS total,
                           SUM(CASE WHEN tip.points = :exact THEN 1 ELSE 0 END) AS exact_count,
                           SUM(CASE WHEN tip.points = :diff THEN 1 ELSE 0 END) AS diff_count
                    FROM kickoff_tip tip
                    JOIN kickoff_game g ON g.id = tip.game_id
                    WHERE g.competition_id = :comp AND tip.points IS NOT NULL
                    GROUP BY tip.user_id
                ) t ON t.user_id = p.user_id
                LEFT JOIN (
                    SELECT sbt.user_id, SUM(sbt.points) AS total
                    FROM kickoff_special_bet_tip sbt
                    JOIN kickoff_special_bet sb ON sb.id = sbt.special_bet_id
                    WHERE sb.competition_id = :comp AND sbt.points IS NOT NULL
                    GROUP BY sbt.user_id
                ) sb ON sb.user_id = p.user_id
                LEFT JOIN (
                    SELECT user_id, SUM(points) AS total
                    FROM kickoff_matchday_bonus
                    WHERE competition_id = :comp
                    GROUP BY user_id
                ) mb ON mb.user_id = p.user_id
                WHERE p.competition_id = :comp
            ) ranked
            WHERE total > :selfTotal
               OR (total = :selfTotal AND exact_count > :selfExact)
               OR (total = :selfTotal AND exact_count = :selfExact AND diff_count > :selfDiff)
        SQL, [
            ':comp' => $this->competition->id,
            ':exact' => $this->scheme->points_exact,
            ':diff' => $this->scheme->points_goal_diff,
            ':selfTotal' => $total,
            ':selfExact' => $exact,
            ':selfDiff' => $diff,
        ])->queryScalar();

        return [
            'rank' => $rankOffset + 1,
            'user' => User::findOne($userId),
            'total' => $total,
            'exact' => $exact,
            'diff' => $diff,
            'bonus' => (int) ($self['bonus_total'] ?? 0),
        ];
    }

    /**
     * Aggregated stats for a single user. Returns null when the user has
     * no participation row for this competition (they never tipped).
     *
     * @return array<string, mixed>|null
     */
    private function aggregateOne(int $userId): ?array
    {
        $row = Yii::$app->db->createCommand(<<<SQL
            SELECT
                COALESCE(t.total, 0) + COALESCE(sb.total, 0) + COALESCE(mb.total, 0) AS total,
                COALESCE(t.exact_count, 0) AS exact_count,
                COALESCE(t.diff_count, 0) AS diff_count,
                COALESCE(mb.total, 0) AS bonus_total
            FROM kickoff_participation p
            LEFT JOIN (
                SELECT tip.user_id,
                       SUM(tip.points) AS total,
                       SUM(CASE WHEN tip.points = :exact THEN 1 ELSE 0 END) AS exact_count,
                       SUM(CASE WHEN tip.points = :diff THEN 1 ELSE 0 END) AS diff_count
                FROM kickoff_tip tip
                JOIN kickoff_game g ON g.id = tip.game_id
                WHERE g.competition_id = :comp AND tip.user_id = :user AND tip.points IS NOT NULL
                GROUP BY tip.user_id
            ) t ON t.user_id = p.user_id
            LEFT JOIN (
                SELECT sbt.user_id, SUM(sbt.points) AS total
                FROM kickoff_special_bet_tip sbt
                JOIN kickoff_special_bet sb ON sb.id = sbt.special_bet_id
                WHERE sb.competition_id = :comp AND sbt.user_id = :user AND sbt.points IS NOT NULL
                GROUP BY sbt.user_id
            ) sb ON sb.user_id = p.user_id
            LEFT JOIN (
                SELECT user_id, SUM(points) AS total
                FROM kickoff_matchday_bonus
                WHERE competition_id = :comp AND user_id = :user
                GROUP BY user_id
            ) mb ON mb.user_id = p.user_id
            WHERE p.competition_id = :comp AND p.user_id = :user
        SQL, [
            ':comp' => $this->competition->id,
            ':user' => $userId,
            ':exact' => $this->scheme->points_exact,
            ':diff' => $this->scheme->points_goal_diff,
        ])->queryOne();

        return $row !== false ? $row : null;
    }
}
