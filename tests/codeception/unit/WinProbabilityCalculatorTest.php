<?php

namespace kickoff;

use Codeception\Test\Unit;
use humhub\modules\kickoff\services\WinProbabilityCalculator;

class WinProbabilityCalculatorTest extends Unit
{
    public function testEqualTeamsInGroupStage(): void
    {
        $p = WinProbabilityCalculator::compute(1700.0, 1700.0, true);
        $this->assertEqualsWithDelta(36.5, $p['home'], 0.5, 'home is ~36.5%');
        $this->assertEqualsWithDelta(36.5, $p['away'], 0.5, 'away is ~36.5%');
        $this->assertEqualsWithDelta(27.0, $p['draw'], 0.5, 'draw is ~27%');
        $this->assertEqualsWithDelta(100.0, $p['home'] + $p['draw'] + $p['away'], 0.5, 'sums to ~100%');
    }

    public function testFavoriteVsUnderdogInGroupStage(): void
    {
        $p = WinProbabilityCalculator::compute(2000.0, 1800.0, true);
        $this->assertGreaterThan($p['away'], $p['home'], 'stronger team has higher chance');
        $this->assertGreaterThan(50.0, $p['home'], 'favorite > 50%');
        $this->assertGreaterThan(0, $p['draw']);
        $this->assertLessThan(27.0, $p['draw'], 'draw shrinks as gap widens');
    }

    public function testLargeGapCapsDrawAtFloor(): void
    {
        $p = WinProbabilityCalculator::compute(2200.0, 1700.0, true);
        $this->assertGreaterThanOrEqual(5.0, $p['draw'], 'draw at or above 5% floor');
        $this->assertGreaterThan(85.0, $p['home'], 'big favorite gets > 85%');
    }

    public function testKnockoutCollapsesDrawShareToZero(): void
    {
        $p = WinProbabilityCalculator::compute(1800.0, 1700.0, false);
        $this->assertSame(0.0, $p['draw'], 'draw is exactly 0 in knockout');
        $this->assertGreaterThan($p['away'], $p['home']);
        $this->assertEqualsWithDelta(100.0, $p['home'] + $p['away'], 0.5, 'home + away ~ 100%');
    }

    public function testSymmetryFlipsHomeAndAway(): void
    {
        $a = WinProbabilityCalculator::compute(1900.0, 1700.0, true);
        $b = WinProbabilityCalculator::compute(1700.0, 1900.0, true);
        $this->assertEqualsWithDelta($a['home'], $b['away'], 0.1, 'a.home == b.away');
        $this->assertEqualsWithDelta($a['away'], $b['home'], 0.1, 'a.away == b.home');
        $this->assertEqualsWithDelta($a['draw'], $b['draw'], 0.1, 'draws match');
    }
}
