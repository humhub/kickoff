<?php

namespace humhub\modules\kickoff\adapters;

use DateTimeImmutable;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\CompetitionTeam;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Team;
use Yii;

class MockAdapter implements CompetitionDataAdapter
{
    public const KEY = 'mock';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return Yii::t('KickoffModule.base', 'Mock (test sandbox)');
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function listAvailable(): array
    {
        return [
            ['id' => 'default', 'name' => 'Mock Tournament', 'season' => 'sandbox'],
        ];
    }

    public function syncFixtures(Competition $competition): SyncReport
    {
        $report = new SyncReport();

        if (Game::find()->where(['competition_id' => $competition->id])->exists()) {
            $report->skipped = (int) Game::find()->where(['competition_id' => $competition->id])->count();
            return $report;
        }

        $config = $competition->getDataSourceConfig();
        $compressionMinutes = max(1, (int) ($config['compression_minutes'] ?? 1));
        $startOffsetMinutes = max(1, (int) ($config['start_offset_minutes'] ?? 1));
        $groupsCount = max(2, (int) ($config['groups'] ?? 2));
        $teamsPerGroup = max(2, (int) ($config['teams_per_group'] ?? 4));

        $teams = $this->createTeams($competition, $groupsCount, $teamsPerGroup, $report);
        if (!$report->isSuccess()) {
            return $report;
        }

        $cursor = (new DateTimeImmutable())->modify("+{$startOffsetMinutes} minutes");
        $advance = "+{$compressionMinutes} minutes";

        foreach ($teams as $groupLabel => $groupTeams) {
            foreach ($this->roundRobinPairs($groupTeams) as [$home, $away]) {
                if ($this->createGame($competition, $home, $away, $cursor, Game::STAGE_GROUP, $groupLabel, $report)) {
                    $cursor = $cursor->modify($advance);
                }
            }
        }

        // Simple KO bracket only for the 2x4 default. Anything else just gets a group stage.
        if ($groupsCount === 2 && $teamsPerGroup === 4) {
            $a = $teams['A'];
            $b = $teams['B'];
            // Semis use seeds A1-B2 / B1-A2; for mock we just use index positions.
            if ($this->createGame($competition, $a[0], $b[1], $cursor, Game::STAGE_SEMI, null, $report)) {
                $cursor = $cursor->modify($advance);
            }
            if ($this->createGame($competition, $b[0], $a[1], $cursor, Game::STAGE_SEMI, null, $report)) {
                $cursor = $cursor->modify($advance);
            }
            if ($this->createGame($competition, $a[1], $b[1], $cursor, Game::STAGE_THIRD_PLACE, null, $report)) {
                $cursor = $cursor->modify($advance);
            }
            $this->createGame($competition, $a[0], $b[0], $cursor, Game::STAGE_FINAL, null, $report);
        }

        return $report;
    }

    public function syncResults(Competition $competition): SyncReport
    {
        $report = new SyncReport();
        $games = Game::find()
            ->where([
                'competition_id' => $competition->id,
                'status' => Game::STATUS_SCHEDULED,
            ])
            ->andWhere(['<=', 'kickoff_at', date('Y-m-d H:i:s')])
            ->all();

        foreach ($games as $game) {
            $game->home_score = random_int(0, 4);
            $game->away_score = random_int(0, 4);
            $game->status = Game::STATUS_FINISHED;
            $game->last_synced_at = date('Y-m-d H:i:s');
            if ($game->save()) {
                $report->updated++;
            } else {
                $report->addError("Could not score mock game #{$game->id}: " . implode(', ', $game->getFirstErrors()));
            }
        }
        return $report;
    }

    public function supportsLive(): bool
    {
        return false;
    }

    /**
     * @return array<string, Team[]>
     */
    private function createTeams(Competition $competition, int $groups, int $perGroup, SyncReport $report): array
    {
        $byGroup = [];
        for ($g = 0; $g < $groups; $g++) {
            $groupLabel = chr(ord('A') + $g);
            for ($t = 1; $t <= $perGroup; $t++) {
                $team = new Team();
                $team->name = "Mock Team {$groupLabel}{$t}";
                $team->short_name = "{$groupLabel}{$t}";
                $team->setExternalIds(['mock' => "team-{$competition->id}-{$groupLabel}{$t}"]);
                if (!$team->save()) {
                    $report->addError("Could not create team {$groupLabel}{$t}: " . implode(', ', $team->getFirstErrors()));
                    return $byGroup;
                }

                $ct = new CompetitionTeam();
                $ct->competition_id = $competition->id;
                $ct->team_id = $team->id;
                $ct->group_label = $groupLabel;
                if (!$ct->save()) {
                    $report->addError("Could not link team {$groupLabel}{$t} to competition: " . implode(', ', $ct->getFirstErrors()));
                    return $byGroup;
                }

                $byGroup[$groupLabel][] = $team;
                $report->created++;
            }
        }
        return $byGroup;
    }

    private function createGame(
        Competition $c,
        Team $home,
        Team $away,
        DateTimeImmutable $kickoff,
        string $stage,
        ?string $groupLabel,
        SyncReport $report,
    ): bool {
        $game = new Game();
        $game->competition_id = $c->id;
        $game->home_team_id = $home->id;
        $game->away_team_id = $away->id;
        $game->kickoff_at = $kickoff->format('Y-m-d H:i:s');
        $game->stage = $stage;
        $game->group_label = $groupLabel;
        $game->status = Game::STATUS_SCHEDULED;
        if (!$game->save()) {
            $report->addError("Could not create game {$home->name} vs {$away->name}: " . implode(', ', $game->getFirstErrors()));
            return false;
        }
        $report->created++;
        return true;
    }

    /**
     * @param Team[] $teams
     * @return array<int, array{0:Team,1:Team}>
     */
    private function roundRobinPairs(array $teams): array
    {
        $pairs = [];
        $n = count($teams);
        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $pairs[] = [$teams[$i], $teams[$j]];
            }
        }
        return $pairs;
    }
}
