<?php

namespace kickoff;

use Codeception\Test\Unit;
use humhub\modules\kickoff\services\GroupStandings;

class GroupStandingsTest extends Unit
{
    /**
     * @return array{home:int,away:int,homeScore:int,awayScore:int}
     */
    private function r(int $h, int $a, int $hs, int $as): array
    {
        return ['home' => $h, 'away' => $a, 'homeScore' => $hs, 'awayScore' => $as];
    }

    public function testEmptyGroup(): void
    {
        $this->assertNull(GroupStandings::winner([]));
        $this->assertSame([], GroupStandings::ranked([]));
    }

    public function testThreeWayTieOnPoints(): void
    {
        // Team 1 beats 2, 3 beats 1, 2 beats 3 → all on 3 points.
        $results = [
            $this->r(1, 2, 2, 0),
            $this->r(3, 1, 1, 0),
            $this->r(2, 3, 3, 1),
        ];
        $ranked = GroupStandings::ranked($results);
        $this->assertCount(3, $ranked);
        foreach ($ranked as $row) {
            $this->assertSame(3, $row['points'], "team {$row['teamId']} has 3 points");
        }
    }

    public function testClearLeaderByPoints(): void
    {
        $results = [
            $this->r(1, 2, 3, 0),
            $this->r(1, 3, 2, 0),
            $this->r(2, 3, 1, 1),
        ];
        $this->assertSame(1, GroupStandings::winner($results));
        $ranked = GroupStandings::ranked($results);
        $this->assertSame(6, $ranked[0]['points']);
        $this->assertSame(5, $ranked[0]['diff']);
        $this->assertSame(5, $ranked[0]['for']);
    }

    public function testTieBrokenByGoalDifference(): void
    {
        $results = [
            $this->r(1, 2, 3, 0),  // team 1 wins big
            $this->r(3, 4, 1, 0),  // team 3 wins narrow
            $this->r(1, 3, 0, 0),
            $this->r(2, 4, 2, 1),
        ];
        $this->assertSame(1, GroupStandings::winner($results), 'team 1 wins on goal difference');
    }

    public function testTieBrokenByGoalsFor(): void
    {
        $results = [
            $this->r(1, 2, 4, 1),  // 1 wins +3, scored 4
            $this->r(3, 4, 3, 0),  // 3 wins +3, scored 3
            $this->r(1, 3, 0, 0),
            $this->r(2, 4, 0, 0),
        ];
        $this->assertSame(1, GroupStandings::winner($results), 'team 1 wins on goals-for');
    }

    public function testAllDrawsRankedByGoalsFor(): void
    {
        $results = [
            $this->r(1, 2, 2, 2),
            $this->r(3, 4, 0, 0),
            $this->r(1, 3, 1, 1),
        ];
        $ranked = GroupStandings::ranked($results);
        $this->assertSame(1, $ranked[0]['teamId']);
        $this->assertSame(2, $ranked[0]['points']);
        $this->assertSame(3, $ranked[1]['teamId']);
    }
}
