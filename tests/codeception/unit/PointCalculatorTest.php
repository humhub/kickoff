<?php

namespace kickoff;

use Codeception\Test\Unit;
use humhub\modules\kickoff\services\PointCalculator;

class PointCalculatorTest extends Unit
{
    private const E = 4;
    private const D = 3;
    private const T = 2;

    public function testExactMatch(): void
    {
        $this->assertSame(self::E, PointCalculator::compute(self::E, self::D, self::T, 2, 1, 2, 1), 'exact 2:1');
        $this->assertSame(self::E, PointCalculator::compute(self::E, self::D, self::T, 0, 0, 0, 0), 'exact 0:0');
        $this->assertSame(self::E, PointCalculator::compute(self::E, self::D, self::T, 3, 3, 3, 3), 'exact 3:3 draw');
    }

    public function testGoalDifference(): void
    {
        $this->assertSame(self::D, PointCalculator::compute(self::E, self::D, self::T, 3, 2, 2, 1), 'same diff +1 (home wins)');
        $this->assertSame(self::D, PointCalculator::compute(self::E, self::D, self::T, 0, 1, 1, 2), 'same diff -1 (away wins)');
        $this->assertSame(self::D, PointCalculator::compute(self::E, self::D, self::T, 0, 0, 1, 1), 'both draws diff 0');
        $this->assertSame(self::D, PointCalculator::compute(self::E, self::D, self::T, 2, 2, 3, 3), 'both draws diff 0, higher scores');
    }

    public function testTendencyOnly(): void
    {
        $this->assertSame(self::T, PointCalculator::compute(self::E, self::D, self::T, 2, 0, 4, 1), 'home wins +2 vs +3');
        $this->assertSame(self::T, PointCalculator::compute(self::E, self::D, self::T, 1, 0, 3, 1), 'home wins +1 vs +2');
        $this->assertSame(self::T, PointCalculator::compute(self::E, self::D, self::T, 0, 1, 0, 3), 'away wins -1 vs -3');
    }

    public function testNoPointsForWrongTendency(): void
    {
        $this->assertSame(0, PointCalculator::compute(self::E, self::D, self::T, 1, 2, 2, 1), 'wrong winner');
        $this->assertSame(0, PointCalculator::compute(self::E, self::D, self::T, 2, 1, 1, 2), 'wrong winner inverted');
        $this->assertSame(0, PointCalculator::compute(self::E, self::D, self::T, 1, 0, 1, 1), 'tipped home win, actual draw');
        $this->assertSame(0, PointCalculator::compute(self::E, self::D, self::T, 1, 1, 2, 1), 'tipped draw, actual home win');
        $this->assertSame(0, PointCalculator::compute(self::E, self::D, self::T, 0, 0, 0, 1), 'tipped draw, actual away win');
    }

    public function testAlternativeScheme(): void
    {
        $this->assertSame(10, PointCalculator::compute(10, 5, 2, 1, 0, 1, 0), 'custom scheme exact');
        $this->assertSame(5, PointCalculator::compute(10, 5, 2, 2, 1, 3, 2), 'custom scheme diff');
        $this->assertSame(2, PointCalculator::compute(10, 5, 2, 1, 0, 4, 0), 'custom scheme tendency');
        $this->assertSame(0, PointCalculator::compute(10, 5, 2, 0, 2, 1, 0), 'custom scheme wrong tendency');
    }
}
