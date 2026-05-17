<?php

namespace humhub\modules\kickoff\services;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\ScoringScheme;
use humhub\modules\kickoff\models\SpecialBet;
use humhub\modules\kickoff\models\SpecialBetTip;
use humhub\modules\kickoff\models\Tip;
use LogicException;

class ScoringService
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

    public function scoreAllFinishedGames(): int
    {
        $updated = 0;
        $games = Game::find()
            ->where(['competition_id' => $this->competition->id, 'status' => Game::STATUS_FINISHED])
            ->all();
        foreach ($games as $game) {
            $updated += $this->scoreGame($game);
        }
        return $updated;
    }

    public function scoreGame(Game $game): int
    {
        if (!$game->isFinished()) {
            return 0;
        }
        $score = $this->getEffectiveScore($game);
        if ($score === null) {
            return 0;
        }
        [$actualHome, $actualAway] = $score;
        $updated = 0;
        foreach ($game->tips as $tip) {
            $points = $this->computePoints($tip->home_score, $tip->away_score, $actualHome, $actualAway);
            if ($tip->points !== $points) {
                $tip->updateAttributes(['points' => $points]);
                $updated++;
            }
        }
        return $updated;
    }

    public function scoreTip(Tip $tip): ?int
    {
        $game = $tip->game;
        if ($game === null || !$game->isFinished()) {
            return null;
        }
        $score = $this->getEffectiveScore($game);
        if ($score === null) {
            return null;
        }
        return $this->computePoints($tip->home_score, $tip->away_score, $score[0], $score[1]);
    }

    public function computePoints(int $tipHome, int $tipAway, int $actualHome, int $actualAway): int
    {
        return PointCalculator::compute(
            $this->scheme->points_exact,
            $this->scheme->points_goal_diff,
            $this->scheme->points_tendency,
            $tipHome,
            $tipAway,
            $actualHome,
            $actualAway,
        );
    }

    /**
     * @return array{0:int,1:int}|null
     */
    public function getEffectiveScore(Game $game): ?array
    {
        if ($game->home_score === null || $game->away_score === null) {
            return null;
        }
        if ($this->competition->ko_scoring_mode === Competition::KO_FULL_TIME
            && $game->isKnockout()
            && $game->home_score_et !== null
            && $game->away_score_et !== null
        ) {
            return [(int) $game->home_score_et, (int) $game->away_score_et];
        }
        return [(int) $game->home_score, (int) $game->away_score];
    }

    public function scoreAllResolvedSpecialBets(): int
    {
        $updated = 0;
        $bets = SpecialBet::find()
            ->where(['competition_id' => $this->competition->id])
            ->andWhere(['IS NOT', 'resolved_value', null])
            ->all();
        foreach ($bets as $bet) {
            $updated += $this->scoreSpecialBet($bet);
        }
        return $updated;
    }

    public function scoreSpecialBet(SpecialBet $bet): int
    {
        if (!$bet->isResolved()) {
            return 0;
        }
        $updated = 0;
        foreach ($bet->tips as $tip) {
            $points = $this->scoreSpecialBetTipValue($tip->value, $bet);
            if ($tip->points !== $points) {
                $tip->updateAttributes(['points' => $points]);
                $updated++;
            }
        }
        return $updated;
    }

    public function scoreSpecialBetTip(SpecialBetTip $tip): ?int
    {
        $bet = $tip->specialBet;
        if ($bet === null || !$bet->isResolved()) {
            return null;
        }
        return $this->scoreSpecialBetTipValue($tip->value, $bet);
    }

    private function scoreSpecialBetTipValue(string $value, SpecialBet $bet): int
    {
        return $this->matchesResolved($value, $bet) ? $bet->points : 0;
    }

    private function matchesResolved(string $value, SpecialBet $bet): bool
    {
        $expected = $bet->resolved_value;
        if ($expected === null) {
            return false;
        }
        if ($bet->type === SpecialBet::TYPE_TOP_SCORER) {
            return $this->normalize($value) === $this->normalize($expected);
        }
        return $value === $expected;
    }

    private function normalize(string $s): string
    {
        return mb_strtolower(trim($s));
    }
}
