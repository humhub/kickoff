<?php

namespace humhub\modules\kickoff\adapters;

use DateTimeImmutable;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Team;
use Yii;

/**
 * WM-2026-sized mock: 48 teams in 12 groups of 4, full bracket
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
 * Bracket seeding is simplified (sequential pairings instead of FIFA's actual
 * bracket). For real WM data, use `football-data.org`.
 */
class MockLargeAdapter extends MockAdapter
{
    public const KEY = 'mock-large';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return Yii::t('KickoffModule.base', 'Mock (WM 2026 size, 48 teams)');
    }

    public function listAvailable(): array
    {
        return [
            ['id' => 'default', 'name' => 'Mock Tournament (WM 2026 size)', 'season' => 'sandbox'],
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

        $config = $competition->getDataSourceConfig();
        $startOffsetDays = max(0, (int) ($config['start_offset_days'] ?? 0));
        $baseDate = (new DateTimeImmutable())->setTime(18, 0)->modify("+{$startOffsetDays} days");

        $dayOffset = 0;
        foreach ($rounds as $pairings) {
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
                        $this->createGame($competition, $home, $away, $time, Game::STAGE_GROUP, $groupLabel, $report);
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
     * Day-based scheduling for KO rounds: the new stage starts the day after
     * the previous stage's last game, at 18:00, with 5-minute spacing between
     * games within the same calendar day.
     *
     * @return array{0:DateTimeImmutable, 1:string}
     */
    protected function nextKickoffCursor(Competition $competition, string $afterStage): array
    {
        $lastKickoff = Game::find()
            ->where(['competition_id' => $competition->id, 'stage' => $afterStage])
            ->max('kickoff_at');
        if ($lastKickoff === null) {
            $base = (new DateTimeImmutable())->setTime(18, 0);
        } else {
            $base = (new DateTimeImmutable((string) $lastKickoff))->modify('+1 day')->setTime(18, 0);
        }
        return [$base, '+5 minutes'];
    }

    private function createRoundOf32(Competition $competition, SyncReport $report): void
    {
        $standings = $this->computeGroupStandings($competition);

        $directQualifiers = [];
        $thirdPlaced = [];
        foreach ($standings as $rows) {
            if (isset($rows[0])) {
                $directQualifiers[] = $rows[0];
            }
            if (isset($rows[1])) {
                $directQualifiers[] = $rows[1];
            }
            if (isset($rows[2])) {
                $thirdPlaced[] = $rows[2];
            }
        }

        usort($thirdPlaced, fn($a, $b) => [$b['points'], $b['diff'], $b['for']] <=> [$a['points'], $a['diff'], $a['for']]);
        $bestThirds = array_slice($thirdPlaced, 0, 8);

        $qualified = array_merge($directQualifiers, $bestThirds);
        if (count($qualified) < 32) {
            $report->addError(
                'Not enough qualified teams for the Round of 32: got ' . count($qualified) . ' / 32.',
            );
            return;
        }
        $qualified = array_slice($qualified, 0, 32);

        [$cursor, $advance] = $this->nextKickoffCursor($competition, Game::STAGE_GROUP);
        // 16 R32 games split across 2 calendar days (8 per day)
        for ($i = 0; $i < 32; $i += 2) {
            if ($i === 16) {
                $cursor = $cursor->modify('+1 day')->setTime(18, 0);
            }
            $home = $qualified[$i]['team'];
            $away = $qualified[$i + 1]['team'];
            if ($home instanceof Team && $away instanceof Team) {
                if ($this->createGame($competition, $home, $away, $cursor, Game::STAGE_ROUND_OF_32, null, $report)) {
                    $cursor = $cursor->modify($advance);
                }
            }
        }
    }
}
