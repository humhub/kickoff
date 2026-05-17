<?php

namespace humhub\modules\kickoff\services;

/**
 * Pure scoring math, dependency-free for unit testing.
 *
 * Rules:
 *   1. Exact score → `exact` points
 *   2. Same goal difference (non-exact) → `diff` points
 *   3. Same tendency (winner side or both draws) → `tendency` points
 *   4. Otherwise → 0
 *
 * 0:0 tipped on a 1:1 actual scores `diff` (both diffs are 0).
 * A draw tip on a non-draw actual scores 0 (tendency mismatch).
 */
final class PointCalculator
{
    public static function compute(
        int $pointsExact,
        int $pointsDiff,
        int $pointsTendency,
        int $tipHome,
        int $tipAway,
        int $actualHome,
        int $actualAway,
    ): int {
        if ($tipHome === $actualHome && $tipAway === $actualAway) {
            return $pointsExact;
        }
        $tipDiff = $tipHome - $tipAway;
        $actualDiff = $actualHome - $actualAway;
        if ($tipDiff === $actualDiff) {
            return $pointsDiff;
        }
        if (($tipDiff <=> 0) === ($actualDiff <=> 0)) {
            return $pointsTendency;
        }
        return 0;
    }
}
