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
    private Competition $competition;
    private ScoringScheme $scheme;

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
    public function compute(?int $limit = null): array
    {
        $sql = <<<SQL
            SELECT
                p.user_id,
                COALESCE(t.total, 0) + COALESCE(sb.total, 0) AS total,
                COALESCE(t.exact_count, 0) AS exact_count,
                COALESCE(t.diff_count, 0) AS diff_count,
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
            WHERE p.competition_id = :comp
            ORDER BY total DESC, exact_count DESC, diff_count DESC, p.joined_at ASC
        SQL;

        $rows = Yii::$app->db->createCommand($sql, [
            ':comp' => $this->competition->id,
            ':exact' => $this->scheme->points_exact,
            ':diff' => $this->scheme->points_goal_diff,
        ])->queryAll();

        $userIds = array_column($rows, 'user_id');
        $users = $userIds === [] ? [] : User::find()->where(['id' => $userIds])->indexBy('id')->all();

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
                'user' => $users[$row['user_id']] ?? null,
                'total' => $total,
                'exact' => $exact,
                'diff' => $diff,
            ];
            $rank = $displayRank;
            $previousKey = $key;
            if ($limit !== null && count($leaderboard) >= $limit) {
                break;
            }
        }
        return $leaderboard;
    }

    /**
     * Leaderboard restricted to a specific set of games (typically one matchday).
     * Special bet points are intentionally excluded — they belong to the Bonus tab.
     *
     * @param int[] $gameIds
     * @return array<int, array{rank:int, user:?User, total:int, exact:int, diff:int}>
     */
    public function computeForGames(array $gameIds, ?int $limit = null): array
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

        $userIds = array_column($rows, 'user_id');
        $users = $userIds === [] ? [] : User::find()->where(['id' => $userIds])->indexBy('id')->all();

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
                'user' => $users[$row['user_id']] ?? null,
                'total' => $total,
                'exact' => $exact,
                'diff' => $diff,
            ];
            $rank = $displayRank;
            $previousKey = $key;
            if ($limit !== null && count($leaderboard) >= $limit) {
                break;
            }
        }
        return $leaderboard;
    }

    /**
     * Returns the user's row in the full overall leaderboard, or null if not ranked.
     *
     * @return array{rank:int, user:?User, total:int, exact:int, diff:int}|null
     */
    public function findUserRank(int $userId): ?array
    {
        foreach ($this->compute() as $row) {
            if ($row['user'] !== null && $row['user']->id === $userId) {
                return $row;
            }
        }
        return null;
    }
}
