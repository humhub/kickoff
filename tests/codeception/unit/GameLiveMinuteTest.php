<?php

namespace kickoff;

use Codeception\Test\Unit;
use humhub\modules\kickoff\models\Game;

/**
 * `current_minute` carries the true match minute (the football clock) exactly
 * as the live-data adapters report it. These freeze the pure formatting and
 * fallback-estimation helpers so the live badge can never again re-derive a
 * match minute from a value that is already a match minute.
 *
 * Regression: the old formatter assumed `current_minute` held a wall-clock
 * value (the mock's 0–114 encoding) and subtracted a ~19-minute half-time
 * offset from every value, so an API-reported 85' was shown as 66' and the
 * real 51'–65' window was mislabelled "HT".
 */
class GameLiveMinuteTest extends Unit
{
    public function testFormatsApiMatchMinuteVerbatim(): void
    {
        // The bug: an API-provided 85' rendered as 66' (85 - 19). It must show 85'.
        $this->assertSame("85'", Game::formatMatchMinute(85));
        $this->assertSame("30'", Game::formatMatchMinute(30));
        $this->assertSame("45'", Game::formatMatchMinute(45));
        // The old formatter mislabelled minutes 51–65 as half-time.
        $this->assertSame("60'", Game::formatMatchMinute(60));
        $this->assertSame("90'", Game::formatMatchMinute(90));
    }

    public function testFormatsStoppageBeyondNinety(): void
    {
        $this->assertSame("90+1'", Game::formatMatchMinute(91));
        $this->assertSame("90+5'", Game::formatMatchMinute(95));
    }

    public function testFormatGuardsAgainstNonPositive(): void
    {
        $this->assertSame("0'", Game::formatMatchMinute(0));
        $this->assertSame("0'", Game::formatMatchMinute(-3));
    }

    public function testEstimatesMatchMinuteFromWallClockDiscountingHalfTime(): void
    {
        $this->assertSame(30, Game::estimateMatchMinute(30 * 60));  // first half
        $this->assertSame(45, Game::estimateMatchMinute(45 * 60));  // end of first half
        $this->assertSame(45, Game::estimateMatchMinute(55 * 60));  // half-time break stays at 45'
        $this->assertSame(85, Game::estimateMatchMinute(100 * 60)); // 100 wall-clock − 15 HT
    }
}
