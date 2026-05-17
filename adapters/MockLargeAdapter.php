<?php

namespace humhub\modules\kickoff\adapters;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Team;
use Yii;

/**
 * WM-2026-sized mock: 48 teams in 12 groups of 4, full bracket
 * (Group → Round of 32 → Round of 16 → Quarter → Semi → Final + Third place)
 * for a total of 104 games. Useful for getting a feel of the actual tournament
 * scale in the UI before WM kicks off.
 *
 * The bracket simplifies FIFA's actual seeding: top 2 of each group plus the
 * 8 best third-placed teams (ranked by points → goal diff → goals for) qualify
 * for the R32; pairings from R32 onward are sequential rather than following
 * the FIFA bracket. That's enough to test the pipeline at scale; for real WM
 * data use the `football-data.org` adapter.
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

    protected function getGroupsCount(): int
    {
        return 12;
    }

    protected function getTeamsPerGroup(): int
    {
        return 4;
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

    private function createRoundOf32(Competition $competition, SyncReport $report): void
    {
        $standings = $this->computeGroupStandings($competition);

        // Top 2 of each of the 12 groups → 24 direct qualifiers
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

        // 8 best 3rd-placed teams ranked by points → goal diff → goals for
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

        // Sequential pairing (1-2, 3-4, ...) — not the FIFA bracket, but exercises the pipeline.
        [$cursor, $advance] = $this->nextKickoffCursor($competition, Game::STAGE_GROUP);
        for ($i = 0; $i < 32; $i += 2) {
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
