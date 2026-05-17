<?php

namespace humhub\modules\kickoff\services;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\MatchdayBonus;
use humhub\modules\kickoff\models\SpecialBet;
use Yii;
use yii\db\Expression;
use yii\db\Query;

/**
 * Awards a bonus to the top-scoring participant(s) of each matchday, plus a
 * separate bonus for the overall special-bet ("bonus") round. The per-bonus
 * value lives on `ScoringScheme.matchday_winner_points` — set it to 0 to
 * disable the feature for a competition.
 *
 * Buckets:
 *   - Group-stage games are bucketed by `matchday_number` →
 *     `group-md-1`, `group-md-2`, `group-md-3`.
 *   - Each KO stage is its own bucket → `ko-round_of_32`, `ko-final`, etc.
 *   - All resolved special bets together form the `bonus` bucket.
 *
 * A bucket is awarded as soon as every game/bet in it is in a terminal
 * state (FINISHED/CANCELLED for games, resolved_value set for bets) and at
 * least one of them is non-cancelled. Awards are idempotent: each
 * (competition, bucket, user) row exists at most once.
 *
 * Ties on rank 1 mean every tied user gets the full bonus (not split).
 */
class MatchdayBonusService
{
    public const BUCKET_BONUS = 'bonus';

    private Competition $competition;
    private int $bonusPoints;

    public function __construct(Competition $competition)
    {
        $this->competition = $competition;
        $scheme = $competition->scoringScheme;
        $this->bonusPoints = $scheme !== null ? (int) $scheme->matchday_winner_points : 0;
    }

    /**
     * Runs through every bucket of the competition, awards the bonus to
     * top-scoring user(s) of any bucket that just turned terminal and isn't
     * already recorded. Returns the number of (user, bucket) bonuses created
     * in this pass.
     */
    public function awardForCompleteMatchdays(): int
    {
        if ($this->bonusPoints <= 0) {
            return 0;
        }

        $created = 0;
        foreach ($this->buckets() as $key => $bucket) {
            if (!$this->isBucketComplete($bucket)) {
                continue;
            }
            $winners = $this->topScorersFor($bucket);
            foreach ($winners as $userId) {
                if ($this->insertBonus($key, $userId, $this->bonusPoints)) {
                    $created++;
                }
            }
        }
        return $created;
    }

    /**
     * @return array<string, array{type:'games'|'bets', ids:int[]}>
     */
    private function buckets(): array
    {
        $buckets = [];

        // Group-stage matchdays bucket by matchday_number.
        $groupRows = (new Query())
            ->select(['matchday_number', 'game_ids' => new Expression('GROUP_CONCAT(id)')])
            ->from('kickoff_game')
            ->where([
                'competition_id' => $this->competition->id,
                'stage' => Game::STAGE_GROUP,
            ])
            ->andWhere(['IS NOT', 'matchday_number', null])
            ->groupBy('matchday_number')
            ->all();
        foreach ($groupRows as $row) {
            $ids = array_map('intval', explode(',', (string) $row['game_ids']));
            $buckets['group-md-' . (int) $row['matchday_number']] = ['type' => 'games', 'ids' => $ids];
        }

        // Each KO stage is its own bucket.
        $koRows = (new Query())
            ->select(['stage', 'game_ids' => new Expression('GROUP_CONCAT(id)')])
            ->from('kickoff_game')
            ->where([
                'competition_id' => $this->competition->id,
                'stage' => Game::STAGES_KNOCKOUT,
            ])
            ->groupBy('stage')
            ->all();
        foreach ($koRows as $row) {
            $ids = array_map('intval', explode(',', (string) $row['game_ids']));
            $buckets['ko-' . (string) $row['stage']] = ['type' => 'games', 'ids' => $ids];
        }

        // Bonus round: all special bets together, as one bucket.
        $betIds = SpecialBet::find()
            ->select('id')
            ->where(['competition_id' => $this->competition->id])
            ->column();
        if ($betIds !== []) {
            $buckets[self::BUCKET_BONUS] = ['type' => 'bets', 'ids' => array_map('intval', $betIds)];
        }

        return $buckets;
    }

    /**
     * @param array{type:'games'|'bets', ids:int[]} $bucket
     */
    private function isBucketComplete(array $bucket): bool
    {
        if ($bucket['ids'] === []) {
            return false;
        }
        if ($bucket['type'] === 'games') {
            // Terminal = FINISHED or CANCELLED. At least one non-cancelled
            // result is needed for the bonus to make sense — a fully
            // cancelled matchday has no winner.
            $hasNonTerminal = (new Query())
                ->from('kickoff_game')
                ->where(['id' => $bucket['ids']])
                ->andWhere(['NOT IN', 'status', [Game::STATUS_FINISHED, Game::STATUS_CANCELLED]])
                ->exists();
            if ($hasNonTerminal) {
                return false;
            }
            $hasFinished = (new Query())
                ->from('kickoff_game')
                ->where(['id' => $bucket['ids'], 'status' => Game::STATUS_FINISHED])
                ->exists();
            return $hasFinished;
        }

        // Bets bucket — terminal = every bet has a resolved_value.
        $unresolved = (new Query())
            ->from('kickoff_special_bet')
            ->where(['id' => $bucket['ids']])
            ->andWhere(['IS', 'resolved_value', null])
            ->exists();
        return !$unresolved;
    }

    /**
     * Returns the user IDs whose tip-points sum is the highest in the bucket.
     * Ties yield multiple winners. Empty bucket (no scored tips) returns [].
     *
     * @param array{type:'games'|'bets', ids:int[]} $bucket
     * @return int[]
     */
    private function topScorersFor(array $bucket): array
    {
        if ($bucket['ids'] === []) {
            return [];
        }
        $query = (new Query())
            ->select(['user_id', 'total' => new Expression('SUM(points)')])
            ->andWhere(['IS NOT', 'points', null])
            ->groupBy('user_id');

        if ($bucket['type'] === 'games') {
            $query->from('kickoff_tip')->andWhere(['game_id' => $bucket['ids']]);
        } else {
            $query->from('kickoff_special_bet_tip')->andWhere(['special_bet_id' => $bucket['ids']]);
        }

        $rows = $query->all();
        if ($rows === []) {
            return [];
        }
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, (int) $row['total']);
        }
        if ($max <= 0) {
            // Nobody scored a point in this matchday — no winner to award.
            return [];
        }
        $winners = [];
        foreach ($rows as $row) {
            if ((int) $row['total'] === $max) {
                $winners[] = (int) $row['user_id'];
            }
        }
        return $winners;
    }

    /**
     * INSERT-IGNORE-style: only writes a row when the (comp, bucket, user)
     * triple isn't already there. Returns true if a row was created.
     */
    private function insertBonus(string $matchdayKey, int $userId, int $points): bool
    {
        $exists = MatchdayBonus::find()
            ->where([
                'competition_id' => $this->competition->id,
                'matchday_key' => $matchdayKey,
                'user_id' => $userId,
            ])
            ->exists();
        if ($exists) {
            return false;
        }
        $bonus = new MatchdayBonus();
        $bonus->competition_id = $this->competition->id;
        $bonus->matchday_key = $matchdayKey;
        $bonus->user_id = $userId;
        $bonus->points = $points;
        $bonus->awarded_at = KickoffTime::nowDb();
        return $bonus->save();
    }

    /**
     * Re-runs the award computation from scratch, dropping any existing
     * matchday-bonus rows for this competition first. Used by the admin
     * "Recompute points" action so a scheme change (e.g. new bonus value)
     * or a corrected tip propagates cleanly.
     */
    public function recompute(): int
    {
        MatchdayBonus::deleteAll(['competition_id' => $this->competition->id]);
        return $this->awardForCompleteMatchdays();
    }
}
