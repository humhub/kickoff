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

        $this->advanceBracket($competition, $report);

        return $report;
    }

    public function supportsLive(): bool
    {
        return false;
    }

    /**
     * Materialises the next bracket stage once the previous one is fully finished.
     * Only the 2-groups-of-4 default produces KO games; other configs stop after the group stage.
     */
    private function advanceBracket(Competition $competition, SyncReport $report): void
    {
        $config = $competition->getDataSourceConfig();
        $groupsCount = max(2, (int) ($config['groups'] ?? 2));
        $teamsPerGroup = max(2, (int) ($config['teams_per_group'] ?? 4));
        if ($groupsCount !== 2 || $teamsPerGroup !== 4) {
            return;
        }

        if ($this->groupStageComplete($competition) && !$this->stageExists($competition, Game::STAGE_SEMI)) {
            $this->createSemiFinals($competition, $report);
            return;
        }

        if ($this->stageExists($competition, Game::STAGE_SEMI)
            && $this->stageComplete($competition, Game::STAGE_SEMI)
            && !$this->stageExists($competition, Game::STAGE_FINAL)) {
            $this->createFinalAndThirdPlace($competition, $report);
        }
    }

    private function groupStageComplete(Competition $competition): bool
    {
        return $this->stageExists($competition, Game::STAGE_GROUP)
            && $this->stageComplete($competition, Game::STAGE_GROUP);
    }

    private function stageExists(Competition $competition, string $stage): bool
    {
        return Game::find()
            ->where(['competition_id' => $competition->id, 'stage' => $stage])
            ->exists();
    }

    private function stageComplete(Competition $competition, string $stage): bool
    {
        return !Game::find()
            ->where(['competition_id' => $competition->id, 'stage' => $stage])
            ->andWhere(['<>', 'status', Game::STATUS_FINISHED])
            ->exists();
    }

    private function createSemiFinals(Competition $competition, SyncReport $report): void
    {
        $standings = $this->computeGroupStandings($competition);
        if (!isset($standings['A'], $standings['B']) || count($standings['A']) < 2 || count($standings['B']) < 2) {
            return;
        }

        [$cursor, $advance] = $this->nextKickoffCursor($competition, Game::STAGE_GROUP);

        // A1 vs B2, then B1 vs A2 — standard cross-bracket pairing.
        if ($this->createGame($competition, $standings['A'][0], $standings['B'][1], $cursor, Game::STAGE_SEMI, null, $report)) {
            $cursor = $cursor->modify($advance);
        }
        $this->createGame($competition, $standings['B'][0], $standings['A'][1], $cursor, Game::STAGE_SEMI, null, $report);
    }

    private function createFinalAndThirdPlace(Competition $competition, SyncReport $report): void
    {
        $semis = Game::find()
            ->where(['competition_id' => $competition->id, 'stage' => Game::STAGE_SEMI])
            ->orderBy(['kickoff_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
        if (count($semis) < 2) {
            return;
        }

        $winners = [];
        $losers = [];
        foreach ($semis as $semi) {
            $homeScore = (int) $semi->home_score;
            $awayScore = (int) $semi->away_score;
            // Mock has no extra time; on a draw we coin-flip so the bracket still progresses.
            if ($homeScore > $awayScore || ($homeScore === $awayScore && random_int(0, 1) === 1)) {
                $winners[] = $semi->home_team_id;
                $losers[] = $semi->away_team_id;
            } else {
                $winners[] = $semi->away_team_id;
                $losers[] = $semi->home_team_id;
            }
        }

        [$cursor, $advance] = $this->nextKickoffCursor($competition, Game::STAGE_SEMI);

        $loser1 = Team::findOne($losers[0]);
        $loser2 = Team::findOne($losers[1]);
        if ($loser1 !== null && $loser2 !== null) {
            if ($this->createGame($competition, $loser1, $loser2, $cursor, Game::STAGE_THIRD_PLACE, null, $report)) {
                $cursor = $cursor->modify($advance);
            }
        }

        $winner1 = Team::findOne($winners[0]);
        $winner2 = Team::findOne($winners[1]);
        if ($winner1 !== null && $winner2 !== null) {
            $this->createGame($competition, $winner1, $winner2, $cursor, Game::STAGE_FINAL, null, $report);
        }
    }

    /**
     * @return array{0:DateTimeImmutable, 1:string}  [cursor, "+N minutes" advance string]
     */
    private function nextKickoffCursor(Competition $competition, string $afterStage): array
    {
        $config = $competition->getDataSourceConfig();
        $compression = max(1, (int) ($config['compression_minutes'] ?? 1));
        $lastKickoff = Game::find()
            ->where(['competition_id' => $competition->id, 'stage' => $afterStage])
            ->max('kickoff_at');
        $base = $lastKickoff ? new DateTimeImmutable((string) $lastKickoff) : new DateTimeImmutable();
        return [$base->modify("+{$compression} minutes"), "+{$compression} minutes"];
    }

    /**
     * Computes the standings per group, ordered by points / goal-diff / goals-for.
     *
     * @return array<string, Team[]>
     */
    private function computeGroupStandings(Competition $competition): array
    {
        $games = Game::find()
            ->where([
                'competition_id' => $competition->id,
                'stage' => Game::STAGE_GROUP,
                'status' => Game::STATUS_FINISHED,
            ])
            ->all();

        $stats = [];
        foreach ($games as $g) {
            $group = (string) $g->group_label;
            foreach ([$g->home_team_id, $g->away_team_id] as $tid) {
                if (!isset($stats[$group][$tid])) {
                    $stats[$group][$tid] = ['points' => 0, 'diff' => 0, 'for' => 0];
                }
            }
            $hs = (int) $g->home_score;
            $as = (int) $g->away_score;
            $stats[$group][$g->home_team_id]['for'] += $hs;
            $stats[$group][$g->home_team_id]['diff'] += ($hs - $as);
            $stats[$group][$g->away_team_id]['for'] += $as;
            $stats[$group][$g->away_team_id]['diff'] += ($as - $hs);
            if ($hs > $as) {
                $stats[$group][$g->home_team_id]['points'] += 3;
            } elseif ($hs < $as) {
                $stats[$group][$g->away_team_id]['points'] += 3;
            } else {
                $stats[$group][$g->home_team_id]['points'] += 1;
                $stats[$group][$g->away_team_id]['points'] += 1;
            }
        }

        $standings = [];
        foreach ($stats as $group => $teamStats) {
            $entries = [];
            foreach ($teamStats as $tid => $stat) {
                $entries[] = ['tid' => $tid] + $stat;
            }
            usort($entries, fn($a, $b) => [$b['points'], $b['diff'], $b['for']] <=> [$a['points'], $a['diff'], $a['for']]);
            $teams = [];
            foreach ($entries as $entry) {
                $team = Team::findOne($entry['tid']);
                if ($team !== null) {
                    $teams[] = $team;
                }
            }
            $standings[$group] = $teams;
        }
        ksort($standings);
        return $standings;
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
