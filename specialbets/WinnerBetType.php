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
}
