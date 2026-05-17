<?php

namespace humhub\modules\kickoff\specialbets;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\SpecialBet;
use humhub\modules\kickoff\models\Team;
use Yii;

class GroupWinnerBetType implements SpecialBetType
{
    public function getKey(): string
    {
        return SpecialBet::TYPE_GROUP_WINNER;
    }

    public function getLabel(): string
    {
        return Yii::t('KickoffModule.base', 'Group winner');
    }

    public function getDefaultPoints(): int
    {
        return 5;
    }

    public function buildOptions(Competition $competition, SpecialBet $bet): array
    {
        if ($bet->group_label === null || $bet->group_label === '') {
            return [];
        }
        $teams = Team::find()
            ->innerJoin('kickoff_competition_team ct', 'ct.team_id = kickoff_team.id')
            ->where(['ct.competition_id' => $competition->id, 'ct.group_label' => $bet->group_label])
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
        return true;
    }

    public function getDefaultQuestion(SpecialBet $bet): string
    {
        return Yii::t('KickoffModule.base', 'Winner of Group {label}?', [
            'label' => $bet->group_label ?? '?',
        ]);
    }

    public function tryResolve(SpecialBet $bet, Competition $competition): ?string
    {
        if (empty($bet->group_label)) {
            return null;
        }
        $games = \humhub\modules\kickoff\models\Game::find()
            ->where([
                'competition_id' => $competition->id,
                'stage' => \humhub\modules\kickoff\models\Game::STAGE_GROUP,
                'group_label' => $bet->group_label,
            ])
            ->all();
        if ($games === []) {
            return null;
        }
        foreach ($games as $g) {
            if ($g->status !== \humhub\modules\kickoff\models\Game::STATUS_FINISHED
                || $g->home_score === null
                || $g->away_score === null) {
                return null;
            }
        }

        $stats = [];
        foreach ($games as $g) {
            foreach ([$g->home_team_id, $g->away_team_id] as $tid) {
                if (!isset($stats[$tid])) {
                    $stats[$tid] = ['points' => 0, 'diff' => 0, 'for' => 0];
                }
            }
            $hs = (int) $g->home_score;
            $as = (int) $g->away_score;
            $stats[$g->home_team_id]['for'] += $hs;
            $stats[$g->home_team_id]['diff'] += ($hs - $as);
            $stats[$g->away_team_id]['for'] += $as;
            $stats[$g->away_team_id]['diff'] += ($as - $hs);
            if ($hs > $as) {
                $stats[$g->home_team_id]['points'] += 3;
            } elseif ($hs < $as) {
                $stats[$g->away_team_id]['points'] += 3;
            } else {
                $stats[$g->home_team_id]['points'] += 1;
                $stats[$g->away_team_id]['points'] += 1;
            }
        }
        $teamIds = array_keys($stats);
        usort($teamIds, fn($a, $b) => [$stats[$b]['points'], $stats[$b]['diff'], $stats[$b]['for']]
            <=> [$stats[$a]['points'], $stats[$a]['diff'], $stats[$a]['for']]);
        return (string) $teamIds[0];
    }
}
