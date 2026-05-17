<?php

namespace kickoff;

use Codeception\Test\Unit;
use humhub\modules\kickoff\services\KickoffTime;

class KickoffTimeTest extends Unit
{
    private string $originalTz = '';

    protected function _before(): void
    {
        // Force PHP's default timezone away from UTC so we exercise the
        // explicit-UTC path. If KickoffTime::parse is correct it returns the
        // same epoch regardless of the runtime default.
        $this->originalTz = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
    }

    protected function _after(): void
    {
        date_default_timezone_set($this->originalTz);
    }

    public function testParseNaiveStringAsUtc(): void
    {
        // 2026-06-11 20:00:00 UTC == 1781208000
        $this->assertSame(1781208000, KickoffTime::parse('2026-06-11 20:00:00'));
    }

    public function testParseHonoursExplicitZuluTimezone(): void
    {
        $this->assertSame(1781208000, KickoffTime::parse('2026-06-11T20:00:00Z'));
    }

    public function testParseHonoursExplicitOffset(): void
    {
        // 2026-06-11T22:00:00+02:00 == 2026-06-11T20:00:00Z
        $this->assertSame(1781208000, KickoffTime::parse('2026-06-11T22:00:00+02:00'));
    }

    public function testParseReturnsNullForBlankInput(): void
    {
        $this->assertNull(KickoffTime::parse(null));
        $this->assertNull(KickoffTime::parse(''));
        $this->assertNull(KickoffTime::parse('   '));
    }

    public function testParseReturnsNullForGarbage(): void
    {
        $this->assertNull(KickoffTime::parse('not a date'));
    }

    public function testNowDbReturnsUtcFormattedString(): void
    {
        $before = time();
        $s = KickoffTime::nowDb();
        $after = time();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s);
        $parsed = KickoffTime::parse($s);
        $this->assertNotNull($parsed);
        $this->assertGreaterThanOrEqual($before, $parsed);
        $this->assertLessThanOrEqual($after + 1, $parsed);
    }

    public function testDbAtRoundtrip(): void
    {
        $ts = 1781208000;
        $s = KickoffTime::dbAt($ts);
        $this->assertSame('2026-06-11 20:00:00', $s);
        $this->assertSame($ts, KickoffTime::parse($s));
    }
}
