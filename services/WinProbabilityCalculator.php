<?php

namespace humhub\modules\kickoff\services;

/**
 * Pure win-probability math, dependency-free for unit testing.
 *
 * Standard Elo expectancy: `P(home wins) = 1 / (1 + 10^((R_away − R_home) / 400))`.
 * For group stage, a draw share is carved out of the binary result; for
 * knockout stages we leave it as a two-way split (ET/penalties absorb draws).
 */
final class WinProbabilityCalculator
{
    private const DRAW_BASE = 0.27;
    private const DRAW_DECAY_PER_RATING = 0.0005;
    private const DRAW_MIN = 0.05;

    /**
     * @return array{home: float, draw: float, away: float}
     *         Percentages (0–100) summing to ~100.
     */
    public static function compute(float $homeRating, float $awayRating, bool $isGroupStage): array
    {
        $diff = $homeRating - $awayRating;
        $pHomeRaw = 1.0 / (1.0 + 10 ** (-$diff / 400.0));

        if ($isGroupStage) {
            $drawShare = max(self::DRAW_MIN, self::DRAW_BASE - abs($diff) * self::DRAW_DECAY_PER_RATING);
            $home = $pHomeRaw * (1 - $drawShare);
            $away = (1 - $pHomeRaw) * (1 - $drawShare);
            $draw = $drawShare;
        } else {
            $home = $pHomeRaw;
            $away = 1 - $pHomeRaw;
            $draw = 0.0;
        }

        return [
            'home' => round($home * 100, 1),
            'draw' => round($draw * 100, 1),
            'away' => round($away * 100, 1),
        ];
    }
}
