<?php

namespace humhub\modules\kickoff\services;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\Module;
use Yii;

/**
 * Builds the user-facing matchday/round entries for a competition. Pulled
 * out of CompetitionController so the leaderboard can offer a matchday
 * selector with the same labels and ids that the main competition view uses.
 */
final class MatchdayEntries
{
    /**
     * @return list<array{id:string, label:string, games:Game[], isPlaceholder:bool}>
     */
    public static function forCompetition(Competition $competition): array
    {
        $allGames = Game::find()
            ->where(['competition_id' => $competition->id])
            ->andWhere(['<>', 'status', Game::STATUS_CANCELLED])
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy(['kickoff_at' => SORT_ASC])
            ->all();

        return self::build($competition, $allGames);
    }

    /**
     * @param Game[] $allGames
     * @return list<array{id:string, label:string, games:Game[], isPlaceholder:bool}>
     */
    public static function build(Competition $competition, array $allGames): array
    {
        $adapter = Module::instance()->getAdapterRegistry()->forCompetition($competition);
        $expectedStages = $adapter !== null
            ? $adapter->getExpectedStages()
            : [Game::STAGE_GROUP];

        $gamesByDate = [];
        $stageOfDate = [];
        foreach ($allGames as $g) {
            $date = substr($g->kickoff_at, 0, 10);
            $gamesByDate[$date][] = $g;
            $stageOfDate[$date] = $g->stage;
        }

        $formatter = Yii::$app->formatter;
        $entries = [];

        foreach ($expectedStages as $stage) {
            $dates = [];
            foreach ($gamesByDate as $date => $_) {
                if ($stageOfDate[$date] === $stage) {
                    $dates[] = $date;
                }
            }

            if ($stage === Game::STAGE_GROUP) {
                $groupGames = array_filter($allGames, fn($g) => $g->stage === Game::STAGE_GROUP);
                $byMatchday = [];
                $allHaveNumber = $groupGames !== [];
                foreach ($groupGames as $g) {
                    if ($g->matchday_number === null) {
                        $allHaveNumber = false;
                        break;
                    }
                    $byMatchday[(int) $g->matchday_number][] = $g;
                }

                if ($allHaveNumber && $byMatchday !== []) {
                    ksort($byMatchday);
                    foreach ($byMatchday as $num => $games) {
                        usort($games, fn($a, $b) => strcmp($a->kickoff_at, $b->kickoff_at));
                        $firstDate = substr($games[0]->kickoff_at, 0, 10);
                        $lastDate = substr($games[count($games) - 1]->kickoff_at, 0, 10);
                        $dateLabel = $firstDate === $lastDate
                            ? $formatter->asDate($firstDate, 'EEE, d. MMM')
                            : $formatter->asDate($firstDate, 'd. MMM') . ' – ' . $formatter->asDate($lastDate, 'd. MMM');
                        $entries[] = [
                            'id' => 'md-' . $num,
                            'label' => Yii::t('KickoffModule.base', 'Matchday {n} · {date}', [
                                'n' => $num,
                                'date' => $dateLabel,
                            ]),
                            'games' => $games,
                            'isPlaceholder' => false,
                        ];
                    }
                } else {
                    foreach ($dates as $idx => $date) {
                        $entries[] = [
                            'id' => $date,
                            'label' => Yii::t('KickoffModule.base', 'Matchday {n} · {date}', [
                                'n' => $idx + 1,
                                'date' => $formatter->asDate($date, 'EEE, d. MMM'),
                            ]),
                            'games' => $gamesByDate[$date],
                            'isPlaceholder' => false,
                        ];
                    }
                }
                continue;
            }

            if ($dates === []) {
                $estimated = $adapter !== null ? $adapter->getEstimatedStageDate($competition, $stage) : null;
                $label = Game::stageLabel($stage) . ' · ';
                $label .= $estimated !== null
                    ? Yii::t('KickoffModule.base', '~ {date}', ['date' => $formatter->asDate($estimated, 'EEE, d. MMM')])
                    : Yii::t('KickoffModule.base', 'TBD');
                $entries[] = [
                    'id' => 'stage:' . $stage,
                    'label' => $label,
                    'games' => [],
                    'isPlaceholder' => true,
                ];
                continue;
            }

            foreach ($dates as $idx => $date) {
                $label = Game::stageLabel($stage);
                if (count($dates) > 1) {
                    $label .= ' · ' . Yii::t('KickoffModule.base', 'Day {n}', ['n' => $idx + 1]);
                }
                $label .= ' · ' . $formatter->asDate($date, 'EEE, d. MMM');
                $entries[] = [
                    'id' => $date,
                    'label' => $label,
                    'games' => $gamesByDate[$date],
                    'isPlaceholder' => false,
                ];
            }
        }

        return $entries;
    }
}
