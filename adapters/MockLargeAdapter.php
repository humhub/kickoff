<?php

namespace humhub\modules\kickoff\adapters;

use DateTimeImmutable;
use DateTimeZone;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\CompetitionTeam;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Team;
use Yii;

/**
 * FWC-2026-sized mock: 48 teams in 12 groups of 4, full bracket
 * (Group → R32 → R16 → QF → SF → Final + Third place), 104 games total.
 *
 * Unlike the small mock (which packs all games into minutes for fast smoke
 * testing), the large mock spreads the group stage across **12 real calendar
 * days** with 6 games per day, and schedules each subsequent KO round one day
 * after the previous one finishes. This makes the matchday grouping in the UI
 * meaningful and gives admins a realistic feel of WM scale.
 *
 * Because real-world kickoff times are days away, the auto-sync cron only
 * progresses one matchday per real day. Admins can use the "Fast forward 1
 * matchday" button to skip ahead at will.
 *
 * Bracket seeding is simplified (sequential pairings instead of the actual
 * tournament bracket). For real WM data, use `football-data.org`.
 */
class MockLargeAdapter extends MockAdapter
{
    public const KEY = 'mock-large';

    /** Real FWC 2026 opening match date. */
    private const WM_BASE_DATE = '2026-06-11';

    /** Real-FWC-2026 first-day offsets per stage, in days after WM_BASE_DATE. */
    private const WM_STAGE_OFFSETS = [
        Game::STAGE_GROUP => 0,        // Jun 11
        Game::STAGE_ROUND_OF_32 => 17, // Jun 28
        Game::STAGE_ROUND_OF_16 => 23, // Jul 4
        Game::STAGE_QUARTER => 28,     // Jul 9
        Game::STAGE_SEMI => 33,        // Jul 14
        Game::STAGE_THIRD_PLACE => 37, // Jul 18
        Game::STAGE_FINAL => 38,       // Jul 19
    ];

    /** 16 real FWC 2026 host venues, cycled for game assignment. */
    private const WM_STADIUMS = [
        'Mexico City — Estadio Azteca',
        'Guadalajara — Estadio Akron',
        'Monterrey — Estadio BBVA',
        'Toronto — BMO Field',
        'Vancouver — BC Place',
        'Atlanta — Mercedes-Benz Stadium',
        'Boston — Gillette Stadium',
        'Dallas — AT&T Stadium',
        'Houston — NRG Stadium',
        'Kansas City — GEHA Field at Arrowhead',
        'Los Angeles — SoFi Stadium',
        'Miami — Hard Rock Stadium',
        'New York / New Jersey — MetLife Stadium',
        'Philadelphia — Lincoln Financial Field',
        'San Francisco Bay Area — Levi\'s Stadium',
        'Seattle — Lumen Field',
    ];

    /**
     * 48 plausible FWC-2026 nations across 12 groups of 4.
     * Format per team: [display name, country code, 3-letter international code].
     * The country code is ISO-3166-1 alpha-2 — except for the British home
     * nations, which have none and use their FIFA trigram (e.g. SCO) so the
     * subdivision flag resolution kicks in.
     * The team-to-group assignment is mock — for the real FWC 2026 draw use the
     * `football-data` adapter with the official competition id.
     */
    private const WM_GROUPS = [
        'A' => [['Mexico', 'MX', 'MEX'], ['Poland', 'PL', 'POL'], ['Saudi Arabia', 'SA', 'KSA'], ['Tunisia', 'TN', 'TUN']],
        'B' => [['Argentina', 'AR', 'ARG'], ['France', 'FR', 'FRA'], ['Croatia', 'HR', 'CRO'], ['Scotland', 'SCO', 'SCO']],
        'C' => [['United States', 'US', 'USA'], ['Belgium', 'BE', 'BEL'], ['Senegal', 'SN', 'SEN'], ['Japan', 'JP', 'JPN']],
        'D' => [['Canada', 'CA', 'CAN'], ['England', 'ENG', 'ENG'], ['Spain', 'ES', 'ESP'], ['Australia', 'AU', 'AUS']],
        'E' => [['Brazil', 'BR', 'BRA'], ['Germany', 'DE', 'GER'], ['Serbia', 'RS', 'SRB'], ['South Korea', 'KR', 'KOR']],
        'F' => [['Portugal', 'PT', 'POR'], ['Uruguay', 'UY', 'URU'], ['Ghana', 'GH', 'GHA'], ['Iran', 'IR', 'IRN']],
        'G' => [['Netherlands', 'NL', 'NED'], ['Denmark', 'DK', 'DEN'], ['Ecuador', 'EC', 'ECU'], ['Cameroon', 'CM', 'CMR']],
        'H' => [['Italy', 'IT', 'ITA'], ['Switzerland', 'CH', 'SUI'], ['Costa Rica', 'CR', 'CRC'], ['Slovakia', 'SK', 'SVK']],
        'I' => [['Colombia', 'CO', 'COL'], ['Sweden', 'SE', 'SWE'], ['Algeria', 'DZ', 'ALG'], ['Qatar', 'QA', 'QAT']],
        'J' => [['Chile', 'CL', 'CHI'], ['Czech Republic', 'CZ', 'CZE'], ['Nigeria', 'NG', 'NGA'], ['Egypt', 'EG', 'EGY']],
        'K' => [['Norway', 'NO', 'NOR'], ['Austria', 'AT', 'AUT'], ['Peru', 'PE', 'PER'], ['Iceland', 'IS', 'ISL']],
        'L' => [['Turkey', 'TR', 'TUR'], ['Romania', 'RO', 'ROU'], ['Slovenia', 'SI', 'SVN'], ['Greece', 'GR', 'GRE']],
    ];

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return Yii::t('KickoffModule.base', 'Mock (FWC 2026 size, 48 teams)');
    }

    public function listAvailable(): array
    {
        return [
            ['id' => 'default', 'name' => 'Mock Tournament (FWC 2026 size)', 'season' => 'sandbox'],
        ];
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

    protected function getGroupsCount(): int
    {
        return 12;
    }

    protected function getTeamsPerGroup(): int
    {
        return 4;
    }

    /**
     * Override parent's generic "Mock Team A1" naming with real FWC-2026 nations
     * so the UI shows recognisable names and flag emojis (via country_code).
     *
     * @return array<string, Team[]>
     */
    protected function createTeams(Competition $competition, int $groups, int $perGroup, SyncReport $report): array
    {
        $byGroup = [];
        foreach (self::WM_GROUPS as $groupLabel => $countries) {
            foreach ($countries as [$name, $iso2, $shortName]) {
                $team = new Team();
                $team->name = $name;
                $team->short_name = $shortName;
                $team->country_code = $iso2;
                $team->setExternalIds(['mock-large' => "team-{$competition->id}-{$iso2}"]);
                if (!$team->save()) {
                    $report->addError("Could not create team {$name}: " . implode(', ', $team->getFirstErrors()));
                    return $byGroup;
                }
                $ct = new CompetitionTeam();
                $ct->competition_id = $competition->id;
                $ct->team_id = $team->id;
                $ct->group_label = $groupLabel;
                if (!$ct->save()) {
                    $report->addError("Could not link team {$name}: " . implode(', ', $ct->getFirstErrors()));
                    return $byGroup;
                }
                $byGroup[$groupLabel][] = $team;
                $report->created++;
            }
        }
        return $byGroup;
    }

    public function syncFixtures(Competition $competition): SyncReport
    {
        $report = new SyncReport();

        if (Game::find()->where(['competition_id' => $competition->id])->exists()) {
            $report->skipped = (int) Game::find()->where(['competition_id' => $competition->id])->count();
            return $report;
        }

        $teams = $this->createTeams($competition, $this->getGroupsCount(), $this->getTeamsPerGroup(), $report);
        if (!$report->isSuccess()) {
            return $report;
        }

        $groupKeys = array_keys($teams);
        sort($groupKeys);

        // Round-robin pairings for 4 teams over 3 matchdays
        $rounds = [
            [[0, 1], [2, 3]],
            [[0, 2], [1, 3]],
            [[0, 3], [1, 2]],
        ];

        $baseDate = new DateTimeImmutable(self::WM_BASE_DATE . ' 18:00', new DateTimeZone('UTC'));

        $dayOffset = 0;
        foreach ($rounds as $roundIdx => $pairings) {
            $matchdayNumber = $roundIdx + 1;
            foreach ($pairings as $pairing) {
                $slotGames = [];
                foreach ($groupKeys as $gk) {
                    $slotGames[] = [$teams[$gk][$pairing[0]], $teams[$gk][$pairing[1]], $gk];
                }
                // Split the 12 simultaneous matches across two calendar days
                $halves = [array_slice($slotGames, 0, 6), array_slice($slotGames, 6, 6)];
                foreach ($halves as $halfGames) {
                    $dayDate = $baseDate->modify("+{$dayOffset} days");
                    $time = $dayDate;
                    foreach ($halfGames as [$home, $away, $groupLabel]) {
                        $this->createGame($competition, $home, $away, $time, Game::STAGE_GROUP, $groupLabel, $report, null, $matchdayNumber);
                        $time = $time->modify('+5 minutes');
                    }
                    $dayOffset++;
                }
            }
        }

        return $report;
    }

    /**
     * Group → Round of 32 (top 2 of each group + 8 best thirds) → R16 → QF → SF → F + 3rd.
     */
    protected function advanceBracket(Competition $competition, SyncReport $report): void
    {
        if ($this->groupStageComplete($competition) && !$this->stageExists($competition, Game::STAGE_ROUND_OF_32)) {
            $this->createRoundOf32($competition, $report);
            return;
        }

        $transitions = [
            [Game::STAGE_ROUND_OF_32, Game::STAGE_ROUND_OF_16],
            [Game::STAGE_ROUND_OF_16, Game::STAGE_QUARTER],
            [Game::STAGE_QUARTER, Game::STAGE_SEMI],
        ];
        foreach ($transitions as [$from, $to]) {
            if ($this->stageExists($competition, $from)
                && $this->stageComplete($competition, $from)
                && !$this->stageExists($competition, $to)) {
                $this->createKnockoutNextRound($competition, $from, $to, $report);
                return;
            }
        }

        if ($this->stageExists($competition, Game::STAGE_SEMI)
            && $this->stageComplete($competition, Game::STAGE_SEMI)
            && !$this->stageExists($competition, Game::STAGE_FINAL)) {
            $this->createFinalAndThirdPlaceFromSemis($competition, $report);
        }
    }

    /**
     * KO scheduling anchored to the real FWC 2026 schedule: each stage starts
     * on the canonical day of the tournament (e.g. R32 on Jun 28, Final on Jul 19)
     * rather than chained from the previous game's date.
     *
     * @return array{0:DateTimeImmutable, 1:string}
     */
    protected function nextKickoffCursor(Competition $competition, string $afterStage): array
    {
        static $next = [
            Game::STAGE_GROUP => Game::STAGE_ROUND_OF_32,
            Game::STAGE_ROUND_OF_32 => Game::STAGE_ROUND_OF_16,
            Game::STAGE_ROUND_OF_16 => Game::STAGE_QUARTER,
            Game::STAGE_QUARTER => Game::STAGE_SEMI,
            Game::STAGE_SEMI => Game::STAGE_THIRD_PLACE,
        ];
        $nextStage = $next[$afterStage] ?? null;
        $offset = $nextStage !== null ? (self::WM_STAGE_OFFSETS[$nextStage] ?? null) : null;
        if ($offset === null) {
            return parent::nextKickoffCursor($competition, $afterStage);
        }
        $base = (new DateTimeImmutable(self::WM_BASE_DATE . ' 18:00', new DateTimeZone('UTC')))
            ->modify("+{$offset} days");
        return [$base, '+15 minutes'];
    }

    public function getEstimatedStageDate(Competition $competition, string $stage): ?string
    {
        $offset = self::WM_STAGE_OFFSETS[$stage] ?? null;
        if ($offset === null) {
            return null;
        }
        return (new DateTimeImmutable(self::WM_BASE_DATE, new DateTimeZone('UTC')))
            ->modify("+{$offset} days")
            ->format('Y-m-d');
    }

    public function getLiveSyncIntervalMinutes(): ?int
    {
        return 2;
    }

    /**
     * Overridden so the final and third-place game land on their own canonical
     * dates (Jul 18 and Jul 19) instead of being stacked minutes apart, and so
     * both can use their iconic real-WM venues.
     */
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

        $base = new DateTimeImmutable(self::WM_BASE_DATE . ' 18:00', new DateTimeZone('UTC'));

        $thirdDate = $base->modify('+' . self::WM_STAGE_OFFSETS[Game::STAGE_THIRD_PLACE] . ' days');
        $loser1 = Team::findOne($losers[0]);
        $loser2 = Team::findOne($losers[1]);
        if ($loser1 instanceof Team && $loser2 instanceof Team) {
            $this->createGame(
                $competition,
                $loser1,
                $loser2,
                $thirdDate,
                Game::STAGE_THIRD_PLACE,
                null,
                $report,
                'Miami — Hard Rock Stadium',
            );
        }

        $finalDate = $base->modify('+' . self::WM_STAGE_OFFSETS[Game::STAGE_FINAL] . ' days');
        $winner1 = Team::findOne($winners[0]);
        $winner2 = Team::findOne($winners[1]);
        if ($winner1 instanceof Team && $winner2 instanceof Team) {
            $this->createGame(
                $competition,
                $winner1,
                $winner2,
                $finalDate,
                Game::STAGE_FINAL,
                null,
                $report,
                'New York / New Jersey — MetLife Stadium',
            );
        }
    }

    private int $stadiumCursor = 0;

    /**
     * Auto-assigns a real FWC 2026 host venue (cycling through the 16 official
     * stadiums) if the caller doesn't pass an explicit one.
     */
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
        if ($venue === null) {
            $venue = self::WM_STADIUMS[$this->stadiumCursor % count(self::WM_STADIUMS)];
            $this->stadiumCursor++;
        }
        return parent::createGame($c, $home, $away, $kickoff, $stage, $groupLabel, $report, $venue, $matchdayNumber);
    }

    private function createRoundOf32(Competition $competition, SyncReport $report): void
    {
        $standings = $this->computeGroupStandings($competition);

        $winners = [];
        $runners = [];
        $thirdPlaced = [];
        foreach ($standings as $rows) {
            if (isset($rows[0])) {
                $winners[] = $rows[0];
            }
            if (isset($rows[1])) {
                $runners[] = $rows[1];
            }
            if (isset($rows[2])) {
                $thirdPlaced[] = $rows[2];
            }
        }

        usort($thirdPlaced, fn($a, $b) => [$b['points'], $b['diff'], $b['for']] <=> [$a['points'], $a['diff'], $a['for']]);
        $bestThirds = array_slice($thirdPlaced, 0, 8);

        // Order: [12 winners][12 runners][8 best thirds] = 32 teams
        $qualified = array_merge($winners, $runners, $bestThirds);
        if (count($qualified) < 32) {
            $report->addError(
                'Not enough qualified teams for the Round of 32: got ' . count($qualified) . ' / 32.',
            );
            return;
        }

        // Half-vs-half pairing: position N plays position N+16. Avoids same-group
        // matchups across all winner/runner combinations (positions 0-11 vs 12-23
        // are always different groups since runner-up at offset N+16-12 = N+4 mod 12).
        [$cursor, $advance] = $this->nextKickoffCursor($competition, Game::STAGE_GROUP);
        $mid = 16;
        for ($i = 0; $i < $mid; $i++) {
            if ($i === 8) {
                $cursor = $cursor->modify('+1 day')->setTime(18, 0);
            }
            $home = $qualified[$i]['team'];
            $away = $qualified[$i + $mid]['team'];
            if ($home instanceof Team && $away instanceof Team) {
                if ($this->createGame($competition, $home, $away, $cursor, Game::STAGE_ROUND_OF_32, null, $report)) {
                    $cursor = $cursor->modify($advance);
                }
            }
        }
    }
}
