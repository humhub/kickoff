<?php

namespace humhub\modules\kickoff\specialbets;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\SpecialBet;
use humhub\modules\kickoff\models\Team;
use Yii;

class WinnerBetType implements SpecialBetType
{
    public function getKey(): string
    {
        return SpecialBet::TYPE_WINNER;
    }

    public function getLabel(): string
    {
        return Yii::t('KickoffModule.base', 'Tournament winner');
    }

    public function getDefaultPoints(): int
    {
        return 20;
    }

    public function buildOptions(Competition $competition, SpecialBet $bet): array
    {
        $teams = Team::find()
            ->innerJoin('kickoff_competition_team ct', 'ct.team_id = kickoff_team.id')
            ->where(['ct.competition_id' => $competition->id])
            ->orderBy(['kickoff_team.name' => SORT_ASC])
            ->all();
        $options = [];
        foreach ($teams as $team) {
            $options[(string) $team->id] = $team->name;
        }
        return $options;
    }

    public function validateValue(string $value, SpecialBet $bet): bool
    {
        $options = $bet->getOptions();
        return $options !== [] && isset($options[$value]);
    }

    public function needsGroupLabel(): bool
    {
        return false;
    }

    public function tryResolve(SpecialBet $bet, Competition $competition): ?string
    {
        $final = \humhub\modules\kickoff\models\Game::find()
            ->where([
                'competition_id' => $competition->id,
                'stage' => \humhub\modules\kickoff\models\Game::STAGE_FINAL,
                'status' => \humhub\modules\kickoff\models\Game::STATUS_FINISHED,
            ])
            ->one();
        if ($final === null) {
            return null;
        }
        // Penalties first (highest precedence in real football)
        if ($final->home_score_pen !== null && $final->away_score_pen !== null) {
            if ($final->home_score_pen > $final->away_score_pen) {
                return (string) $final->home_team_id;
            }
            if ($final->away_score_pen > $final->home_score_pen) {
                return (string) $final->away_team_id;
            }
        }
        if ($final->home_score_et !== null && $final->away_score_et !== null) {
            if ($final->home_score_et > $final->away_score_et) {
                return (string) $final->home_team_id;
            }
            if ($final->away_score_et > $final->home_score_et) {
                return (string) $final->away_team_id;
            }
        }
        if ($final->home_score !== null && $final->away_score !== null) {
            if ($final->home_score > $final->away_score) {
                return (string) $final->home_team_id;
            }
            if ($final->away_score > $final->home_score) {
                return (string) $final->away_team_id;
            }
        }
        return null;
    }
}
