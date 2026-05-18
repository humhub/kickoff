<?php

namespace humhub\modules\kickoff\adapters;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\CompetitionTeam;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Team;
use humhub\modules\kickoff\Module;
use humhub\modules\kickoff\services\KickoffTime;
use Yii;

class FootballDataOrgAdapter implements CompetitionDataAdapter
{
    public const KEY = 'football-data';
    public const SETTING_TOKEN = 'football-data.token';

    private const BASE_URL = 'https://api.football-data.org/v4';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return Yii::t('KickoffModule.base', 'football-data.org');
    }

    public function isConfigured(): bool
    {
        return $this->getApiToken() !== null;
    }

    public function listAvailable(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }
        try {
            $response = $this->httpGet('/competitions');
        } catch (\Throwable $e) {
            Yii::error("football-data listAvailable: " . $e->getMessage());
            return [];
        }
        $available = [];
        foreach (($response['competitions'] ?? []) as $comp) {
            $available[] = [
                'id' => (string) ($comp['id'] ?? ''),
                'name' => (string) ($comp['name'] ?? ''),
                'season' => isset($comp['currentSeason']['startDate'])
                    ? substr((string) $comp['currentSeason']['startDate'], 0, 4)
                    : null,
            ];
        }
        return $available;
    }

    public function syncFixtures(Competition $competition): SyncReport
    {
        $report = new SyncReport();
        $externalId = $this->getExternalCompetitionId($competition, $report);
        if ($externalId === null) {
            return $report;
        }

        try {
            $teamsResponse = $this->httpGet("/competitions/{$externalId}/teams");
            $this->syncTeams($competition, $teamsResponse['teams'] ?? [], $report);

            $matchesResponse = $this->httpGet("/competitions/{$externalId}/matches");
            $teamsByExternalId = $this->indexTeamsByExternalId($competition);
            foreach (($matchesResponse['matches'] ?? []) as $matchData) {
                $this->syncMatch($competition, $matchData, $teamsByExternalId, $report);
            }
        } catch (\Throwable $e) {
            $report->addError('Sync failed: ' . $e->getMessage());
        }
        return $report;
    }

    public function syncResults(Competition $competition): SyncReport
    {
        $report = new SyncReport();
        $externalId = $this->getExternalCompetitionId($competition, $report);
        if ($externalId === null) {
            return $report;
        }

        try {
            // football-data expects UTC dates in its `dateFrom`/`dateTo` query.
            $from = gmdate('Y-m-d', time() - 2 * 86400);
            $to = gmdate('Y-m-d', time() + 1 * 86400);
            $response = $this->httpGet("/competitions/{$externalId}/matches?dateFrom={$from}&dateTo={$to}");
            $teamsByExternalId = $this->indexTeamsByExternalId($competition);
            foreach (($response['matches'] ?? []) as $matchData) {
                $this->syncMatch($competition, $matchData, $teamsByExternalId, $report);
            }
        } catch (\Throwable $e) {
            $report->addError('Results sync failed: ' . $e->getMessage());
        }
        return $report;
    }

    public function supportsLive(): bool
    {
        return true;
    }

    public function getExpectedStages(): array
    {
        // Best fit for the WM 2026 format. Older tournaments without R32
        // simply never create those games — placeholder entries stay TBD.
        return [
            Game::STAGE_GROUP,
            Game::STAGE_ROUND_OF_32,
            Game::STAGE_ROUND_OF_16,
            Game::STAGE_QUARTER,
            Game::STAGE_SEMI,
            Game::STAGE_THIRD_PLACE,
            Game::STAGE_FINAL,
        ];
    }

    public function getEstimatedStageDate(Competition $competition, string $stage): ?string
    {
        // Real dates come from the API itself once fixtures are scheduled; nothing
        // to estimate beyond that.
        return null;
    }

    public function getLiveSyncIntervalMinutes(): ?int
    {
        // 30 calls/min budget on the free tier — 2 min gives ~10 calls/hour
        // per active live window. Safe headroom for one competition.
        return 2;
    }

    private function getApiToken(): ?string
    {
        $token = Module::instance()->settings->get(self::SETTING_TOKEN);
        return $token !== null && trim((string) $token) !== '' ? trim((string) $token) : null;
    }

    private function getExternalCompetitionId(Competition $competition, SyncReport $report): ?string
    {
        if (!$this->isConfigured()) {
            $report->addError('football-data.org API token not configured in module settings.');
            return null;
        }
        $config = $competition->getDataSourceConfig();
        $externalId = $config['external_competition_id'] ?? null;
        if (empty($externalId)) {
            $report->addError('Set "external_competition_id" in the competition\'s data source config.');
            return null;
        }
        return (string) $externalId;
    }

    /**
     * @param array<int, array<string,mixed>> $teamsData
     */
    private function syncTeams(Competition $competition, array $teamsData, SyncReport $report): void
    {
        foreach ($teamsData as $teamData) {
            $externalId = (string) ($teamData['id'] ?? '');
            if ($externalId === '') {
                continue;
            }
            $team = Team::findByExternalId(self::KEY, $externalId);
            $isNew = $team === null;
            if ($team === null) {
                $team = new Team();
            }
            $team->name = (string) ($teamData['name'] ?? $team->name ?? 'Unknown');
            $team->short_name = $teamData['tla'] ?? $teamData['shortName'] ?? $team->short_name;
            $team->country_code = $teamData['area']['code'] ?? $team->country_code;
            $team->logo_url = $teamData['crest'] ?? $team->logo_url;
            $ids = $team->getExternalIds();
            $ids[self::KEY] = $externalId;
            $team->setExternalIds($ids);

            if ($team->save()) {
                $isNew ? $report->created++ : $report->updated++;
            } else {
                $report->addError("Could not save team {$externalId}: " . implode(', ', $team->getFirstErrors()));
                continue;
            }

            $link = CompetitionTeam::findOne(['competition_id' => $competition->id, 'team_id' => $team->id]);
            if ($link === null) {
                $link = new CompetitionTeam();
                $link->competition_id = $competition->id;
                $link->team_id = $team->id;
                $link->save();
            }
        }
    }

    /**
     * @return array<string, Team>
     */
    private function indexTeamsByExternalId(Competition $competition): array
    {
        $teams = Team::find()
            ->innerJoin('kickoff_competition_team ct', 'ct.team_id = kickoff_team.id')
            ->where(['ct.competition_id' => $competition->id])
            ->all();
        $byExt = [];
        foreach ($teams as $team) {
            $id = $team->getExternalId(self::KEY);
            if ($id !== null) {
                $byExt[$id] = $team;
            }
        }
        return $byExt;
    }

    /**
     * @param array<string, Team> $teamsByExternalId
     */
    private function syncMatch(Competition $competition, array $matchData, array $teamsByExternalId, SyncReport $report): void
    {
        $externalId = (string) ($matchData['id'] ?? '');
        if ($externalId === '') {
            return;
        }
        $homeExt = (string) ($matchData['homeTeam']['id'] ?? '');
        $awayExt = (string) ($matchData['awayTeam']['id'] ?? '');
        $home = $teamsByExternalId[$homeExt] ?? null;
        $away = $teamsByExternalId[$awayExt] ?? null;
        if ($home === null || $away === null) {
            $report->addError("Match {$externalId}: teams not found ({$homeExt} vs {$awayExt}). Re-run fixtures sync.");
            return;
        }

        $game = Game::findOne(['competition_id' => $competition->id, 'external_id' => $externalId]);
        $isNew = $game === null;
        if ($game === null) {
            $game = new Game();
            $game->competition_id = $competition->id;
            $game->external_id = $externalId;
        }
        $game->home_team_id = $home->id;
        $game->away_team_id = $away->id;
        $game->kickoff_at = isset($matchData['utcDate'])
            ? KickoffTime::dbAt(KickoffTime::parse((string) $matchData['utcDate']) ?? time())
            : $game->kickoff_at;
        $game->stage = FootballDataMatchParser::stage($matchData['stage'] ?? null);
        $normalizedGroup = FootballDataMatchParser::groupLabel($matchData['group'] ?? null);
        if ($normalizedGroup !== null) {
            $game->group_label = $normalizedGroup;
        }
        $game->status = FootballDataMatchParser::status($matchData['status'] ?? null);
        if (isset($matchData['venue']) && $matchData['venue'] !== '') {
            $game->venue = (string) $matchData['venue'];
        }
        $game->current_minute = isset($matchData['minute']) && is_numeric($matchData['minute'])
            ? (int) $matchData['minute']
            : ($game->status === Game::STATUS_LIVE ? $game->current_minute : null);
        $game->matchday_number = isset($matchData['matchday']) && is_numeric($matchData['matchday'])
            ? (int) $matchData['matchday']
            : null;

        $score = $matchData['score'] ?? [];
        $game->home_score = $score['fullTime']['home'] ?? null;
        $game->away_score = $score['fullTime']['away'] ?? null;
        $game->home_score_et = $score['extraTime']['home'] ?? null;
        $game->away_score_et = $score['extraTime']['away'] ?? null;
        $game->home_score_pen = $score['penalties']['home'] ?? null;
        $game->away_score_pen = $score['penalties']['away'] ?? null;
        $game->last_synced_at = KickoffTime::nowDb();

        if ($game->save()) {
            $isNew ? $report->created++ : $report->updated++;
            // football-data only carries group info on matches, not on team listings —
            // propagate the group label onto each team's CompetitionTeam link so
            // group-stage features (group-winner bets, group standings) have data.
            if ($game->stage === Game::STAGE_GROUP && !empty($game->group_label)) {
                foreach ([$home->id, $away->id] as $teamId) {
                    CompetitionTeam::updateAll(
                        ['group_label' => $game->group_label],
                        [
                            'competition_id' => $competition->id,
                            'team_id' => $teamId,
                        ],
                    );
                }
            }
        } else {
            $report->addError("Could not save match {$externalId}: " . implode(', ', $game->getFirstErrors()));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function httpGet(string $path): array
    {
        $url = self::BASE_URL . $path;
        $headers = [
            'X-Auth-Token: ' . $this->getApiToken(),
            'Accept: application/json',
            'User-Agent: HumHub-Kickoff/1.0',
        ];
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $last = error_get_last();
            throw new \RuntimeException("HTTP request failed for {$url}: " . ($last['message'] ?? 'unknown error'));
        }

        $statusCode = $this->parseStatusCode($http_response_header ?? []);
        if ($statusCode >= 400) {
            throw new \RuntimeException("HTTP {$statusCode} from {$url}: " . substr($body, 0, 200));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Invalid JSON response from {$url}");
        }
        return $decoded;
    }

    /** @param string[] $headers */
    private function parseStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/[0-9.]+\s+(\d+)#', $header, $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }
}
