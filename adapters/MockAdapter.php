<?php

namespace humhub\modules\kickoff\adapters;

use DateTimeImmutable;
use DateTimeZone;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\CompetitionTeam;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Team;
use humhub\modules\kickoff\services\KickoffTime;
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
        return Yii::t('KickoffModule.base', 'Mock (small, 8 teams)');
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function listAvailable(): array
    {
        return [
            ['id' => 'default', 'name' => 'Mock Tournament (8 teams)', 'season' => 'sandbox'],
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

        $teams = $this->createTeams($competition, $this->getGroupsCount(), $this->getTeamsPerGroup(), $report);
        if (!$report->isSuccess()) {
            return $report;
        }

        $cursor = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("+{$startOffsetMinutes} minutes");
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

    /** Live window of the small mock: 5 real minutes per game (compressed wall-clock match). */
    private const LIVE_WINDOW_SEC = 5 * 60;

    public function syncResults(Competition $competition): SyncReport
    {
        $report = new SyncReport();
        $now = time();
        $nowFmt = KickoffTime::nowDb();

        // 1) Past-kickoff scheduled games: enter LIVE (or jump straight to FINISHED
        //    if they're already past the live window).
        $scheduled = Game::find()
            ->where([
                'competition_id' => $competition->id,
                'status' => Game::STATUS_SCHEDULED,
            ])
            ->andWhere(['<=', 'kickoff_at', $nowFmt])
            ->all();

        foreach ($scheduled as $game) {
            $elapsed = $now - (KickoffTime::parse($game->kickoff_at) ?? $now);
            if ($elapsed > self::LIVE_WINDOW_SEC) {
                $game->home_score = $game->home_score ?? random_int(0, 4);
                $game->away_score = $game->away_score ?? random_int(0, 4);
                $game->status = Game::STATUS_FINISHED;
                $game->current_minute = null;
            } else {
                $game->home_score = $game->home_score ?? 0;
                $game->away_score = $game->away_score ?? 0;
                $game->status = Game::STATUS_LIVE;
                $game->current_minute = $this->mockMatchMinute($elapsed);
            }
            $game->last_synced_at = $nowFmt;
            if ($game->save()) {
                $report->updated++;
            } else {
                $report->addError("Could not score mock game #{$game->id}: " . implode(', ', $game->getFirstErrors()));
            }
        }

        // 2) Currently LIVE games: tick the simulated minute, roll for goals,
        //    finish them once past the live window.
        $live = Game::find()
            ->where([
                'competition_id' => $competition->id,
                'status' => Game::STATUS_LIVE,
            ])
            ->all();

        foreach ($live as $game) {
            $elapsed = $now - (KickoffTime::parse($game->kickoff_at) ?? $now);
            if ($elapsed > self::LIVE_WINDOW_SEC) {
                $game->status = Game::STATUS_FINISHED;
                $game->current_minute = null;
                $game->last_synced_at = $nowFmt;
                if ($game->save()) {
                    $report->updated++;
                }
                continue;
            }
            // ~1-in-3 chance per side per sync — over five 1-minute syncs each
            // game is overwhelmingly likely to pick up at least one goal so the
            // live state actually shows a non-trivial scoreline.
            if (random_int(0, 2) === 0) {
                $game->home_score = (int) $game->home_score + 1;
            }
            if (random_int(0, 2) === 0) {
                $game->away_score = (int) $game->away_score + 1;
            }
            $game->current_minute = $this->mockMatchMinute($elapsed);
            $game->last_synced_at = $nowFmt;
            $game->save();
        }

        $this->advanceBracket($competition, $report);

        return $report;
    }

    /**
     * Maps elapsed wall-clock seconds inside the 5-minute live window to the
     * 0–114 wall-clock-minute value that `Game::getFormattedLiveMinute()`
     * expects (which already encodes HT between minutes 51 and 65, second
     * half from 66 onwards, and FT past 114). One real minute of mock time
     * ≈ 23 simulated match minutes so the live badge ticks through
     * "23'", "45+1'", "73'", "90+5'" instead of jumping from kickoff to FT.
     */
    private function mockMatchMinute(int $elapsedSec): int
    {
        $scaled = (int) floor($elapsedSec * 115 / self::LIVE_WINDOW_SEC);
        return max(0, min(114, $scaled));
    }

    public function supportsLive(): bool
    {
        return false;
    }

    public function getExpectedStages(): array
    {
        return [
            Game::STAGE_GROUP,
            Game::STAGE_SEMI,
            Game::STAGE_THIRD_PLACE,
            Game::STAGE_FINAL,
        ];
    }

    public function getEstimatedStageDate(Competition $competition, string $stage): ?string
    {
        return null;
    }

    public function getLiveSyncIntervalMinutes(): ?int
    {
        // Tick the simulated live window every minute so the score, status and
        // displayed match minute progress visibly while a tester watches a
        // mock game go live.
        return 1;
    }

    protected function getGroupsCount(): int
    {
        return 2;
    }

    protected function getTeamsPerGroup(): int
    {
        return 4;
    }

    /**
     * Materialises the next bracket stage once the previous one is fully finished.
     * Small mock: groups → semis → final/3rd.
     */
    protected function advanceBracket(Competition $competition, SyncReport $report): void
    {
        if ($this->groupStageComplete($competition) && !$this->stageExists($competition, Game::STAGE_SEMI)) {
            $this->createSemiFinals($competition, $report);
            return;
        }

        if ($this->stageExists($competition, Game::STAGE_SEMI)
            && $this->stageComplete($competition, Game::STAGE_SEMI)
            && !$this->stageExists($competition, Game::STAGE_FINAL)) {
            $this->createFinalAndThirdPlaceFromSemis($competition, $report);
        }
    }

    private function createSemiFinals(Competition $competition, SyncReport $report): void
    {
        $standings = $this->computeGroupStandings($competition);
        if (!isset($standings['A'], $standings['B']) || count($standings['A']) < 2 || count($standings['B']) < 2) {
            return;
        }

        [$cursor, $advance] = $this->nextKickoffCursor($competition, Game::STAGE_GROUP);

        if ($this->createGame($competition, $standings['A'][0]['team'], $standings['B'][1]['team'], $cursor, Game::STAGE_SEMI, null, $report)) {
            $cursor = $cursor->modify($advance);
        }
        $this->createGame($competition, $standings['B'][0]['team'], $standings['A'][1]['team'], $cursor, Game::STAGE_SEMI, null, $report);
    }

    protected function createFinalAndThirdPlaceFromSemis(Competition $competition, SyncReport $report): void
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
            [$winnerId, $loserId] = $this->pickKnockoutWinner($semi);
            $winners[] = $winnerId;
            $losers[] = $loserId;
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
     * Pairs winners of $fromStage games (in order) into $toStage games (consecutive pairs).
     */
    protected function createKnockoutNextRound(
        Competition $competition,
        string $fromStage,
        string $toStage,
        SyncReport $report,
    ): void {
        $games = Game::find()
            ->where(['competition_id' => $competition->id, 'stage' => $fromStage])
            ->orderBy(['kickoff_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $winners = [];
        foreach ($games as $g) {
            [$winnerId, $_] = $this->pickKnockoutWinner($g);
            $winners[] = $winnerId;
        }

        [$cursor, $advance] = $this->nextKickoffCursor($competition, $fromStage);
        for ($i = 0; $i + 1 < count($winners); $i += 2) {
            $home = Team::findOne($winners[$i]);
            $away = Team::findOne($winners[$i + 1]);
            if ($home === null || $away === null) {
                continue;
            }
            if ($this->createGame($competition, $home, $away, $cursor, $toStage, null, $report)) {
                $cursor = $cursor->modify($advance);
            }
        }
    }

    /**
     * @return array{0:int,1:int}  [winnerTeamId, loserTeamId]
     */
    protected function pickKnockoutWinner(Game $game): array
    {
        $hs = (int) $game->home_score;
        $as = (int) $game->away_score;
        if ($hs > $as || ($hs === $as && random_int(0, 1) === 1)) {
            return [$game->home_team_id, $game->away_team_id];
        }
        return [$game->away_team_id, $game->home_team_id];
    }

    protected function groupStageComplete(Competition $competition): bool
    {
        return $this->stageExists($competition, Game::STAGE_GROUP)
            && $this->stageComplete($competition, Game::STAGE_GROUP);
    }

    protected function stageExists(Competition $competition, string $stage): bool
    {
        return Game::find()
            ->where(['competition_id' => $competition->id, 'stage' => $stage])
            ->exists();
    }

    protected function stageComplete(Competition $competition, string $stage): bool
    {
        return !Game::find()
            ->where(['competition_id' => $competition->id, 'stage' => $stage])
            ->andWhere(['<>', 'status', Game::STATUS_FINISHED])
            ->exists();
    }

    /**
     * @return array{0:DateTimeImmutable, 1:string}
     */
    protected function nextKickoffCursor(Competition $competition, string $afterStage): array
    {
        $config = $competition->getDataSourceConfig();
        $compression = max(1, (int) ($config['compression_minutes'] ?? 1));
        $lastKickoff = Game::find()
            ->where(['competition_id' => $competition->id, 'stage' => $afterStage])
            ->max('kickoff_at');
        $utc = new DateTimeZone('UTC');
        $base = $lastKickoff
            ? new DateTimeImmutable((string) $lastKickoff, $utc)
            : new DateTimeImmutable('now', $utc);
        return [$base->modify("+{$compression} minutes"), "+{$compression} minutes"];
    }

    /**
     * Standings per group, sorted by points → goal-diff → goals-for.
     *
     * @return array<string, array<int, array{team:Team, points:int, diff:int, for:int}>>
     */
    protected function computeGroupStandings(Competition $competition): array
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
            $rows = [];
            foreach ($teamStats as $tid => $stat) {
                $team = Team::findOne($tid);
                if ($team === null) {
                    continue;
                }
                $rows[] = ['team' => $team, 'points' => $stat['points'], 'diff' => $stat['diff'], 'for' => $stat['for']];
            }
            usort($rows, fn($a, $b) => [$b['points'], $b['diff'], $b['for']] <=> [$a['points'], $a['diff'], $a['for']]);
            $standings[$group] = $rows;
        }
        ksort($standings);
        return $standings;
    }

    /**
     * @return array<string, Team[]>
     */
    protected function createTeams(Competition $competition, int $groups, int $perGroup, SyncReport $report): array
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

    protected function createGame(
        Competition $c,
        Team $home,
        Team $away,
        DateTimeImmutable $kickoff,
        string $stage,
        ?string $groupLabel,
        SyncReport $report,
        ?string $venue = null,
        ?int $matchdayNumber = null,
    ): bool {
        $game = new Game();
        $game->competition_id = $c->id;
        $game->home_team_id = $home->id;
        $game->away_team_id = $away->id;
        $game->kickoff_at = $kickoff->format('Y-m-d H:i:s');
        $game->stage = $stage;
        $game->group_label = $groupLabel;
        $game->status = Game::STATUS_SCHEDULED;
        if ($venue !== null) {
            $game->venue = $venue;
        }
        if ($matchdayNumber !== null) {
            $game->matchday_number = $matchdayNumber;
        }
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
    protected function roundRobinPairs(array $teams): array
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
