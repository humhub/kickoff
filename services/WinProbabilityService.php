<?php

namespace humhub\modules\kickoff\services;

use humhub\modules\kickoff\models\Game;

/**
 * Estimates win/draw/loss probabilities for a match from the participating
 * teams' strength ratings (FIFA points and/or Elo). Uses the standard Elo
 * formula `P(A wins) = 1 / (1 + 10^((R_B − R_A) / 400))` and adds a draw
 * adjustment for group-stage games.
 *
 * Not a betting product — values are heuristics shown to help inexperienced
 * tippers, deliberately rendered as percentages rather than odds.
 */
class WinProbabilityService
{
    /**
     * Base draw probability at equal ratings, shrinking linearly with rating
     * gap (large gap → close to a binary outcome). Tuned for national teams.
     */
    private const DRAW_BASE = 0.27;
    private const DRAW_DECAY_PER_RATING = 0.0005;

    /**
     * @return array{home: float, draw: float, away: float}|null
     *         Probabilities as percentages summing to 100, or null when either
     *         team has no rating data and we'd just be making numbers up.
     */
    public function forGame(Game $game): ?array
    {
        $homeRating = $game->homeTeam?->getStrengthRating();
        $awayRating = $game->awayTeam?->getStrengthRating();
        if ($homeRating === null || $awayRating === null) {
            return null;
        }

        $diff = $homeRating - $awayRating;
        $pHomeRaw = 1.0 / (1.0 + 10 ** (-$diff / 400.0));

        if ($game->stage === Game::STAGE_GROUP) {
            $drawShare = max(0.05, self::DRAW_BASE - abs($diff) * self::DRAW_DECAY_PER_RATING);
            $home = $pHomeRaw * (1 - $drawShare);
            $away = (1 - $pHomeRaw) * (1 - $drawShare);
            $draw = $drawShare;
        } else {
            // Knockout games eventually have a winner — collapse to a 2-way split
            // (extra time / penalties absorb the "draw after 90'" outcome).
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
