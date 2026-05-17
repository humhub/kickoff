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
        return WinProbabilityCalculator::compute(
            $homeRating,
            $awayRating,
            $game->stage === Game::STAGE_GROUP,
        );
    }
}
