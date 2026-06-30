<?php

namespace kickoff;

use Codeception\Test\Unit;
use humhub\modules\kickoff\services\MatchResult;

class MatchResultTest extends Unit
{
    public function testPointsRelevantRegularTimeModeIgnoresExtraTime(): void
    {
        // 1:1 after 90, 1:1 after ET, won on penalties — regular-time scoring
        // counts the 90-minute score, never the extra-time/penalty result.
        $this->assertSame([1, 1], MatchResult::pointsRelevant(false, 1, 1, 1, 1));
    }

    public function testPointsRelevantFullTimeModeUsesExtraTime(): void
    {
        // 1:1 after 90, won 2:1 in extra time — full-time scoring counts 2:1.
        $this->assertSame([2, 1], MatchResult::pointsRelevant(true, 1, 1, 2, 1));
    }

    public function testPointsRelevantFullTimeModeFallsBackTo90WhenNoExtraTime(): void
    {
        $this->assertSame([2, 1], MatchResult::pointsRelevant(true, 2, 1, null, null));
    }

    public function testPointsRelevantNullWhenNoResult(): void
    {
        $this->assertNull(MatchResult::pointsRelevant(false, null, null, null, null));
    }

    public function testStagesPenaltyShootoutListsAllThree(): void
    {
        $this->assertSame([
            ['stage' => '90', 'home' => 1, 'away' => 1],
            ['stage' => 'et', 'home' => 1, 'away' => 1],
            ['stage' => 'pen', 'home' => 6, 'away' => 5],
        ], MatchResult::stages(1, 1, 1, 1, 6, 5));
    }

    public function testStagesExtraTimeWinListsNinetyAndExtraTime(): void
    {
        $this->assertSame([
            ['stage' => '90', 'home' => 1, 'away' => 1],
            ['stage' => 'et', 'home' => 2, 'away' => 1],
        ], MatchResult::stages(1, 1, 2, 1, null, null));
    }

    public function testStagesRegularTimeMatchListsOnlyNinety(): void
    {
        $this->assertSame([
            ['stage' => '90', 'home' => 2, 'away' => 1],
        ], MatchResult::stages(2, 1, null, null, null, null));
    }

    public function testStagesEmptyWhenNoResult(): void
    {
        $this->assertSame([], MatchResult::stages(null, null, null, null, null, null));
    }

    public function testSecondaryStagesRegularTimeModeShowsExtraTimeAndPenalties(): void
    {
        // 90 min is shown big, so extra time and penalties go to the small line.
        $this->assertSame([
            ['stage' => 'et', 'home' => 1, 'away' => 1],
            ['stage' => 'pen', 'home' => 6, 'away' => 5],
        ], MatchResult::secondaryStages(false, 1, 1, 1, 1, 6, 5));
    }

    public function testSecondaryStagesRegularTimeModeRegularMatchShowsNothing(): void
    {
        $this->assertSame([], MatchResult::secondaryStages(false, 2, 1, null, null, null, null));
    }

    public function testSecondaryStagesFullTimeModeShowsNinetyAndPenalties(): void
    {
        // End of extra time is shown big, so 90 min and penalties go small.
        $this->assertSame([
            ['stage' => '90', 'home' => 1, 'away' => 1],
            ['stage' => 'pen', 'home' => 6, 'away' => 5],
        ], MatchResult::secondaryStages(true, 1, 1, 1, 1, 6, 5));
    }

    public function testSecondaryStagesFullTimeModeWithoutExtraTimeShowsNothing(): void
    {
        // Full-time scoring but the match ended in regulation: 90 min is big.
        $this->assertSame([], MatchResult::secondaryStages(true, 2, 1, null, null, null, null));
    }
}
