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
        // Derive group members from group-stage games rather than
        // CompetitionTeam.group_label — adapters don't all populate the latter,
        // but the games table always has the group label per match.
        $teamIds = (new \yii\db\Query())
            ->select('home_team_id')
            ->from('kickoff_game')
            ->where([
                'competition_id' => $competition->id,
                'stage' => \humhub\modules\kickoff\models\Game::STAGE_GROUP,
                'group_label' => $bet->group_label,
            ])
            ->union(
                (new \yii\db\Query())
                    ->select('away_team_id')
                    ->from('kickoff_game')
                    ->where([
                        'competition_id' => $competition->id,
                        'stage' => \humhub\modules\kickoff\models\Game::STAGE_GROUP,
                        'group_label' => $bet->group_label,
                    ]),
            )
            ->column();
        if ($teamIds === []) {
            return [];
        }
        $teams = Team::find()
            ->where(['id' => array_unique(array_map('intval', $teamIds))])
            ->orderBy(['name' => SORT_ASC])
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

    public function isManualResolveOnly(): bool
    {
        return false;
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

        $results = [];
        foreach ($games as $g) {
            $results[] = [
                'home' => (int) $g->home_team_id,
                'away' => (int) $g->away_team_id,
                'homeScore' => (int) $g->home_score,
                'awayScore' => (int) $g->away_score,
            ];
        }
        $winner = \humhub\modules\kickoff\services\GroupStandings::winner($results);
        return $winner !== null ? (string) $winner : null;
    }
}
