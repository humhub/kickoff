<?php

namespace humhub\modules\kickoff\services;

/**
 * Pure helpers for turning a game's stored score fields into the values the
 * UI and scoring need, dependency-free for unit testing.
 *
 * The module stores three score pairs per game (see FootballDataMatchParser):
 *   - home_score / away_score        → after 90 minutes (regular time)
 *   - home_score_et / away_score_et  → cumulative at the end of extra time
 *   - home_score_pen / away_score_pen → penalty shootout goals
 *
 * Which pair *counts* for points is per competition ("Knockout scoring"):
 * regular time uses the 90-minute score, full time uses the end-of-extra-time
 * score for knockout games.
 */
final class MatchResult
{
    /**
     * The score points are awarded against.
     *
     * @return array{0:int,1:int}|null null when the game has no result yet
     */
    public static function pointsRelevant(
        bool $useExtraTime,
        ?int $home,
        ?int $away,
        ?int $homeEt,
        ?int $awayEt,
    ): ?array {
        if ($home === null || $away === null) {
            return null;
        }
        if ($useExtraTime && $homeEt !== null && $awayEt !== null) {
            return [$homeEt, $awayEt];
        }
        return [$home, $away];
    }

    /**
     * The real-world result stages that actually occurred, in chronological
     * order, for display. The '90' stage is present once a result exists;
     * 'et'/'pen' only appear for knockout games that went that far (the
     * adapter leaves those fields null otherwise).
     *
     * @return list<array{stage:string,home:int,away:int}>
     */
    public static function stages(
        ?int $home,
        ?int $away,
        ?int $homeEt,
        ?int $awayEt,
        ?int $homePen,
        ?int $awayPen,
    ): array {
        if ($home === null || $away === null) {
            return [];
        }
        $stages = [['stage' => '90', 'home' => $home, 'away' => $away]];
        if ($homeEt !== null && $awayEt !== null) {
            $stages[] = ['stage' => 'et', 'home' => $homeEt, 'away' => $awayEt];
        }
        if ($homePen !== null && $awayPen !== null) {
            $stages[] = ['stage' => 'pen', 'home' => $homePen, 'away' => $awayPen];
        }
        return $stages;
    }

    /**
     * The result stages to show *secondarily* (small) under the prominent
     * score — every stage that actually occurred except the one shown big.
     * The big stage is extra time when full-time scoring applies and an ET
     * score exists, otherwise the 90-minute score.
     *
     * @return list<array{stage:string,home:int,away:int}>
     */
    public static function secondaryStages(
        bool $useExtraTime,
        ?int $home,
        ?int $away,
        ?int $homeEt,
        ?int $awayEt,
        ?int $homePen,
        ?int $awayPen,
    ): array {
        $bigStage = ($useExtraTime && $homeEt !== null && $awayEt !== null) ? 'et' : '90';
        $secondary = [];
        foreach (self::stages($home, $away, $homeEt, $awayEt, $homePen, $awayPen) as $stage) {
            if ($stage['stage'] !== $bigStage) {
                $secondary[] = $stage;
            }
        }
        return $secondary;
    }
}
