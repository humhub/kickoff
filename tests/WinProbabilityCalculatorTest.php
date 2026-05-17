<?php

/**
 * Standalone unit tests for WinProbabilityCalculator.
 *
 * Run with: `php tests/WinProbabilityCalculatorTest.php` from the module root.
 * Exit code 0 on success, 1 on any failure.
 */

declare(strict_types=1);

require __DIR__ . '/../services/WinProbabilityCalculator.php';

use humhub\modules\kickoff\services\WinProbabilityCalculator;

$failures = 0;
$passes = 0;

function approx(float $expected, float $actual, string $message, float $tolerance = 0.5): void
{
    global $failures, $passes;
    if (abs($expected - $actual) <= $tolerance) {
        echo "  ✓ {$message}\n";
        $passes++;
        return;
    }
    echo "  ✗ {$message}: expected ~{$expected}, got {$actual}\n";
    $failures++;
}

function check(bool $cond, string $message): void
{
    global $failures, $passes;
    if ($cond) {
        echo "  ✓ {$message}\n";
        $passes++;
        return;
    }
    echo "  ✗ {$message}\n";
    $failures++;
}

echo "WinProbabilityCalculator — equal teams in group stage:\n";
$p = WinProbabilityCalculator::compute(1700.0, 1700.0, true);
approx(36.5, $p['home'], 'home is ~36.5%');
approx(36.5, $p['away'], 'away is ~36.5%');
approx(27.0, $p['draw'], 'draw is ~27% at zero gap');
check(abs(($p['home'] + $p['draw'] + $p['away']) - 100.0) < 0.5, 'sums to ~100%');

echo "\nFavorite vs underdog in group stage (200 Elo gap):\n";
$p = WinProbabilityCalculator::compute(2000.0, 1800.0, true);
check($p['home'] > $p['away'], 'home (stronger) > away');
check($p['home'] > 50.0, 'home > 50%');
check($p['draw'] > 0 && $p['draw'] < 27.0, 'draw shrinks below base when gap widens');

echo "\nLarge gap in group stage (500 Elo) caps draw at floor:\n";
$p = WinProbabilityCalculator::compute(2200.0, 1700.0, true);
check($p['draw'] >= 5.0, 'draw at or above 5% floor');
check($p['home'] > 85.0, 'big favorite gets > 85%');

echo "\nKnockout collapses draw share to zero:\n";
$p = WinProbabilityCalculator::compute(1800.0, 1700.0, false);
check($p['draw'] === 0.0, 'draw is exactly 0 in knockout');
check($p['home'] > $p['away'], 'home (stronger) wins more often');
check(abs(($p['home'] + $p['away']) - 100.0) < 0.5, 'home + away ~ 100%');

echo "\nSymmetry — flipping inputs flips home/away:\n";
$a = WinProbabilityCalculator::compute(1900.0, 1700.0, true);
$b = WinProbabilityCalculator::compute(1700.0, 1900.0, true);
approx($a['home'], $b['away'], 'a.home == b.away', 0.1);
approx($a['away'], $b['home'], 'a.away == b.home', 0.1);
approx($a['draw'], $b['draw'], 'draws match', 0.1);

echo "\n";
echo $passes . " passed, " . $failures . " failed.\n";
exit($failures === 0 ? 0 : 1);
