<?php

namespace humhub\modules\kickoff\adapters;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\CompetitionTeam;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\SpecialBet;
use humhub\modules\kickoff\models\Team;
use humhub\modules\kickoff\Module;
use Yii;

/**
 * Pre-packaged competition data served by api.humhub.com. The server pulls
 * fixtures and results from upstream providers (football-data.org for the FIFA
 * World Cup) on a cron and exposes a single normalized JSON document per
 * competition. The module just consumes that document — no API key, no
 * rate-limit budget, no upstream-vocabulary mapping. Same payload also carries
 * static metadata (team ratings, default special-bet templates) so the
 * one-click setup can complete a competition end-to-end from one fetch.
 */
class HumHubApiAdapter implements CompetitionDataAdapter
{
    public const KEY = 'humhub-api';

    public const SETTING_BASE_URL = 'humhub-api.base_url';
    public const SETTING_LOCAL_FIXTURE = 'humhub-api.local_fixture_path';

    public const COMPETITION_WM2026 = 'wm2026';

    private const DEFAULT_BASE_URL = 'https://api.humhub.com';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return Yii::t('KickoffModule.base', 'HumHub Data Service (no API key required)');
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function listAvailable(): array
    {
        try {
            $response = $this->fetchJson('competitions');
        } catch (\Throwable $e) {
            Yii::error('humhub-api listAvailable: ' . $e->getMessage());
            return [];
        }
        $available = [];
        foreach (($response['competitions'] ?? []) as $comp) {
            $available[] = [
                'id' => (string) ($comp['id'] ?? ''),
                'name' => (string) ($comp['name'] ?? ''),
                'season' => isset($comp['season']) ? (string) $comp['season'] : null,
            ];
        }
        return $available;
    }

    public function syncFixtures(Competition $competition): SyncReport
    {
        $report = new SyncReport();
        $payload = $this->loadPayload($competition, $report);
        if ($payload === null) {
            return $report;
        }
        $this->applyTeams($competition, $payload['teams'] ?? [], $report);
        $teamsByExt = $this->indexTeamsByExternalId($competition);
        foreach (($payload['games'] ?? []) as $gameData) {
            $this->applyGame($competition, $gameData, $teamsByExt, $report);
        }
        return $report;
    }

    public function syncResults(Competition $competition): SyncReport
    {
        // The payload always carries fixtures + current results together, so a
        // results sync is just another full refresh. Keeping a separate method
        // anyway because the cron pipeline calls them differently and may grow
        // its own short-circuit logic later.
        return $this->syncFixtures($competition);
    }

    public function supportsLive(): bool
    {
        return true;
    }

    public function getExpectedStages(): array
    {
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
        return null;
    }

    public function getLiveSyncIntervalMinutes(): ?int
    {
        return 2;
    }

    /**
     * Imports the static portions of the payload — team ratings and default
     * special-bet templates — into an already-synced competition. Called by
     * the one-click WM 2026 setup so the admin gets a fully populated
     * competition (teams, fixtures, ratings, special bets) from a single
     * action.
     */
    public function applyMetadata(Competition $competition): SyncReport
    {
        $report = new SyncReport();
        $payload = $this->loadPayload($competition, $report);
        if ($payload === null) {
            return $report;
        }
        $this->applyRatings($competition, $payload['ratings'] ?? [], $report);
        $this->applySpecialBetTemplates($competition, $payload['special_bet_templates'] ?? [], $report);
        return $report;
    }

    private function loadPayload(Competition $competition, SyncReport $report): ?array
    {
        $config = $competition->getDataSourceConfig();
        $externalId = (string) ($config['external_competition_id'] ?? '');
        if ($externalId === '') {
            $report->addError('Set "external_competition_id" in the competition\'s data source config (e.g. "wm2026").');
            return null;
        }
        try {
            return $this->fetchJson('competitions/' . $externalId);
        } catch (\Throwable $e) {
            $report->addError('Fetch failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resource is a path fragment under `/v1/kickoff/` (e.g. `competitions`,
     * `competitions/wm2026`). When `SETTING_LOCAL_FIXTURE` is set, reads from
     * the local filesystem instead — used for development against the bundled
     * mock payload before the server endpoint exists.
     *
     * @return array<string, mixed>
     */
    private function fetchJson(string $resource): array
    {
        $localFixture = trim((string) Module::instance()->settings->get(self::SETTING_LOCAL_FIXTURE));
        if ($localFixture !== '') {
            $path = rtrim($localFixture, '/') . '/' . $resource . '.json';
            $body = @file_get_contents($path);
            if ($body === false) {
                throw new \RuntimeException("Could not read local fixture {$path}");
            }
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException("Invalid JSON in {$path}");
            }
            return $decoded;
        }

        $base = trim((string) (Module::instance()->settings->get(self::SETTING_BASE_URL) ?? self::DEFAULT_BASE_URL));
        if ($base === '') {
            $base = self::DEFAULT_BASE_URL;
        }
        $url = rtrim($base, '/') . '/v1/kickoff/' . $resource;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\nUser-Agent: HumHub-Kickoff/1.0\r\n",
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

    /**
     * @param array<int, array<string, mixed>> $teamsData
     */
    private function applyTeams(Competition $competition, array $teamsData, SyncReport $report): void
    {
        foreach ($teamsData as $teamData) {
            $externalId = (string) ($teamData['external_id'] ?? '');
            if ($externalId === '') {
                continue;
            }
            $team = Team::findByExternalId(self::KEY, $externalId);
            $isNew = $team === null;
            if ($team === null) {
                $team = new Team();
            }
            $team->name = (string) ($teamData['name'] ?? $team->name ?? 'Unknown');
            $team->short_name = $teamData['short_name'] ?? $team->short_name;
            $team->country_code = $teamData['country_code'] ?? $team->country_code;
            $team->logo_url = $teamData['logo_url'] ?? $team->logo_url;
            $ids = $team->getExternalIds();
            $ids[self::KEY] = $externalId;
            $team->setExternalIds($ids);

            if (!$team->save()) {
                $report->addError("Could not save team {$externalId}: " . implode(', ', $team->getFirstErrors()));
                continue;
            }
            $isNew ? $report->created++ : $report->updated++;

            $link = CompetitionTeam::findOne(['competition_id' => $competition->id, 'team_id' => $team->id]);
            if ($link === null) {
                $link = new CompetitionTeam();
                $link->competition_id = $competition->id;
                $link->team_id = $team->id;
            }
            if (array_key_exists('group_label', $teamData) && $teamData['group_label'] !== null) {
                $link->group_label = (string) $teamData['group_label'];
            }
            $link->save();
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
     * @param array<string, mixed> $matchData
     * @param array<string, Team>  $teamsByExternalId
     */
    private function applyGame(
        Competition $competition,
        array $matchData,
        array $teamsByExternalId,
        SyncReport $report,
    ): void {
        $externalId = (string) ($matchData['external_id'] ?? '');
        if ($externalId === '') {
            return;
        }
        $homeExt = (string) ($matchData['home_external_id'] ?? '');
        $awayExt = (string) ($matchData['away_external_id'] ?? '');
        $home = $teamsByExternalId[$homeExt] ?? null;
        $away = $teamsByExternalId[$awayExt] ?? null;
        if ($home === null || $away === null) {
            $report->addError("Match {$externalId}: teams not found ({$homeExt} vs {$awayExt}).");
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
        if (isset($matchData['kickoff_at'])) {
            $game->kickoff_at = gmdate('Y-m-d H:i:s', strtotime((string) $matchData['kickoff_at']));
        }
        $game->stage = (string) ($matchData['stage'] ?? Game::STAGE_GROUP);
        if (array_key_exists('group_label', $matchData)) {
            $game->group_label = $matchData['group_label'] !== null ? (string) $matchData['group_label'] : null;
        }
        $game->status = (string) ($matchData['status'] ?? Game::STATUS_SCHEDULED);
        if (isset($matchData['venue']) && $matchData['venue'] !== '') {
            $game->venue = (string) $matchData['venue'];
        }
        $game->current_minute = isset($matchData['minute']) && is_numeric($matchData['minute'])
            ? (int) $matchData['minute']
            : ($game->status === Game::STATUS_LIVE ? $game->current_minute : null);
        $game->matchday_number = isset($matchData['matchday_number']) && is_numeric($matchData['matchday_number'])
            ? (int) $matchData['matchday_number']
            : null;

        $game->home_score = $matchData['home_score'] ?? null;
        $game->away_score = $matchData['away_score'] ?? null;
        $game->home_score_et = $matchData['home_score_et'] ?? null;
        $game->away_score_et = $matchData['away_score_et'] ?? null;
        $game->home_score_pen = $matchData['home_score_pen'] ?? null;
        $game->away_score_pen = $matchData['away_score_pen'] ?? null;
        $game->last_synced_at = date('Y-m-d H:i:s');

        if (!$game->save()) {
            $report->addError("Could not save match {$externalId}: " . implode(', ', $game->getFirstErrors()));
            return;
        }
        $isNew ? $report->created++ : $report->updated++;

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
    }

    /**
     * @param array<int, array<string, mixed>> $ratings
     */
    private function applyRatings(Competition $competition, array $ratings, SyncReport $report): void
    {
        if ($ratings === []) {
            return;
        }
        $lookup = [];
        foreach ($ratings as $entry) {
            foreach ((array) ($entry['country_codes'] ?? []) as $code) {
                $lookup[strtoupper((string) $code)] = $entry;
            }
        }
        $teams = Team::find()
            ->innerJoin('kickoff_competition_team ct', 'ct.team_id = kickoff_team.id')
            ->where(['ct.competition_id' => $competition->id])
            ->all();
        foreach ($teams as $team) {
            $code = strtoupper((string) $team->country_code);
            if ($code === '' || !isset($lookup[$code])) {
                continue;
            }
            $entry = $lookup[$code];
            $changed = false;
            if ($team->fifa_points === null && isset($entry['fifa'])) {
                $team->fifa_points = (int) $entry['fifa'];
                $changed = true;
            }
            if ($team->elo_rating === null && isset($entry['elo'])) {
                $team->elo_rating = (int) $entry['elo'];
                $changed = true;
            }
            if ($changed && $team->save()) {
                $report->updated++;
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     */
    private function applySpecialBetTemplates(Competition $competition, array $templates, SyncReport $report): void
    {
        $registry = Module::instance()->getSpecialBetTypeRegistry();
        foreach ($templates as $tmpl) {
            $type = (string) ($tmpl['type'] ?? '');
            $points = (int) ($tmpl['points'] ?? 0);
            if ($type === '') {
                continue;
            }
            $betType = $registry->get($type);
            if ($betType === null) {
                continue;
            }

            if ($type === SpecialBet::TYPE_WINNER) {
                $exists = SpecialBet::find()
                    ->where(['competition_id' => $competition->id, 'type' => $type])
                    ->exists();
                if ($exists) {
                    continue;
                }
                $bet = new SpecialBet();
                $bet->competition_id = $competition->id;
                $bet->type = $type;
                $bet->points = $points > 0 ? $points : $betType->getDefaultPoints();
                $bet->deadline_at = $competition->starts_at ?: date('Y-m-d H:i:s');
                $bet->setOptions($betType->buildOptions($competition, $bet));
                if ($bet->save()) {
                    $report->created++;
                }
            } elseif ($type === SpecialBet::TYPE_GROUP_WINNER) {
                $this->createGroupWinnerBets($competition, $points, $report);
            }
        }
    }

    private function createGroupWinnerBets(Competition $competition, int $points, SyncReport $report): void
    {
        $registry = Module::instance()->getSpecialBetTypeRegistry();
        $type = $registry->get(SpecialBet::TYPE_GROUP_WINNER);
        if ($type === null) {
            return;
        }

        $groups = (new \yii\db\Query())
            ->select('group_label')
            ->from('kickoff_game')
            ->where([
                'competition_id' => $competition->id,
                'stage' => Game::STAGE_GROUP,
            ])
            ->andWhere(['IS NOT', 'group_label', null])
            ->andWhere(['<>', 'group_label', ''])
            ->distinct()
            ->orderBy('group_label')
            ->column();

        $existing = array_flip(array_filter(SpecialBet::find()
            ->select('group_label')
            ->where(['competition_id' => $competition->id, 'type' => SpecialBet::TYPE_GROUP_WINNER])
            ->column()));

        foreach ($groups as $groupLabel) {
            if (isset($existing[$groupLabel])) {
                continue;
            }
            $firstGame = Game::find()
                ->where([
                    'competition_id' => $competition->id,
                    'stage' => Game::STAGE_GROUP,
                    'group_label' => $groupLabel,
                ])
                ->orderBy(['kickoff_at' => SORT_ASC])
                ->one();

            $bet = new SpecialBet();
            $bet->competition_id = $competition->id;
            $bet->type = SpecialBet::TYPE_GROUP_WINNER;
            $bet->group_label = $groupLabel;
            $bet->points = $points > 0 ? $points : $type->getDefaultPoints();
            $bet->deadline_at = $firstGame !== null
                ? $firstGame->kickoff_at
                : ($competition->starts_at ?: date('Y-m-d H:i:s'));
            $bet->setOptions($type->buildOptions($competition, $bet));
            if ($bet->save()) {
                $report->created++;
            }
        }
    }
}
