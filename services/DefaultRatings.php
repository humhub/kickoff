<?php

namespace humhub\modules\kickoff\services;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Team;
use Yii;

/**
 * Reads the bundled WM 2026 FIFA / Elo snapshot and applies the ratings to a
 * competition's teams. Centralised so the small mock's auto-setup and the
 * admin's "Apply default ratings" action share the same lookup and merge
 * rules — code variants (ISO-2 / ISO-3 / FIFA-style), skip teams that already
 * have ratings, and report which codes had no snapshot entry.
 */
final class DefaultRatings
{
    /**
     * @return array{matched:int, unmatchedCodes:array<string,int>}
     *   `matched` is the number of teams that received at least one new value.
     *   `unmatchedCodes` maps each country code that was *not* found in the
     *   snapshot to how many of this competition's teams carry that code.
     */
    public static function applyToCompetition(Competition $competition): array
    {
        $snapshot = require Yii::getAlias('@humhub/modules/kickoff/data/wm2026_ratings.php');
        if (!is_array($snapshot) || $snapshot === []) {
            return ['matched' => 0, 'unmatchedCodes' => []];
        }

        $lookup = [];
        foreach ($snapshot as $entry) {
            foreach ((array) ($entry['codes'] ?? []) as $code) {
                $lookup[strtoupper((string) $code)] = $entry;
            }
        }

        $matched = 0;
        $unmatched = [];
        $teams = Team::find()
            ->innerJoin('kickoff_competition_team ct', 'ct.team_id = kickoff_team.id')
            ->where(['ct.competition_id' => $competition->id])
            ->all();
        foreach ($teams as $team) {
            $code = strtoupper((string) $team->country_code);
            if ($code === '') {
                continue;
            }
            if (!isset($lookup[$code])) {
                $unmatched[$code] = ($unmatched[$code] ?? 0) + 1;
                continue;
            }
            $entry = $lookup[$code];
            $changed = false;
            if ($team->fifa_points === null && isset($entry['fifa'])) {
                $team->fifa_points = (int) $entry['fifa'];
                $changed = true;
            }
            if ($team->elo_rating === null && isset($entry['elo'])) {
                $team->elo_rating = (int) $entry['elo'];
                $changed = true;
            }
            if ($changed && $team->save()) {
                $matched++;
            }
        }
        return ['matched' => $matched, 'unmatchedCodes' => $unmatched];
    }
}
