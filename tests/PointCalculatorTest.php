<?php

/**
 * Standalone unit tests for PointCalculator.
 *
 * Run with: `php tests/PointCalculatorTest.php` from the module root.
 * Exit code 0 on success, 1 on any failure.
 */

declare(strict_types=1);

require __DIR__ . '/../services/PointCalculator.php';

use humhub\modules\kickoff\services\PointCalculator;

$failures = 0;
$passes = 0;

function check(int $expected, int $actual, string $message): void
{
    global $failures, $passes;
    if ($expected === $actual) {
        echo "  ✓ {$message}\n";
        $passes++;
        return;
    }
    echo "  ✗ {$message}: expected {$expected}, got {$actual}\n";
    $failures++;
}

// Classic 4/3/2 scheme
$E = 4;
$D = 3;
$T = 2;

echo "PointCalculator (classic 4/3/2):\n";

check($E, PointCalculator::compute($E, $D, $T, 2, 1, 2, 1), 'exact 2:1');
check($E, PointCalculator::compute($E, $D, $T, 0, 0, 0, 0), 'exact 0:0');
check($E, PointCalculator::compute($E, $D, $T, 3, 3, 3, 3), 'exact 3:3 draw');

check($D, PointCalculator::compute($E, $D, $T, 3, 2, 2, 1), 'same diff +1 (home wins)');
check($D, PointCalculator::compute($E, $D, $T, 0, 1, 1, 2), 'same diff -1 (away wins)');
check($D, PointCalculator::compute($E, $D, $T, 0, 0, 1, 1), 'both draws diff 0');
check($D, PointCalculator::compute($E, $D, $T, 2, 2, 3, 3), 'both draws diff 0, higher scores');

check($T, PointCalculator::compute($E, $D, $T, 2, 0, 4, 1), 'home wins +2 vs +3 — tendency only');
check($T, PointCalculator::compute($E, $D, $T, 1, 0, 3, 1), 'home wins +1 vs +2 — tendency only');
check($T, PointCalculator::compute($E, $D, $T, 0, 1, 0, 3), 'away wins -1 vs -3 — tendency only');

check(0, PointCalculator::compute($E, $D, $T, 1, 2, 2, 1), 'wrong winner');
check(0, PointCalculator::compute($E, $D, $T, 2, 1, 1, 2), 'wrong winner inverted');
check(0, PointCalculator::compute($E, $D, $T, 1, 0, 1, 1), 'tipped home win, actual draw');
check(0, PointCalculator::compute($E, $D, $T, 1, 1, 2, 1), 'tipped draw, actual home win');
check(0, PointCalculator::compute($E, $D, $T, 0, 0, 0, 1), 'tipped draw, actual away win');

echo "\nAlternative scheme (10/5/2):\n";
$E2 = 10;
$D2 = 5;
$T2 = 2;

check($E2, PointCalculator::compute($E2, $D2, $T2, 1, 0, 1, 0), 'custom scheme exact');
check($D2, PointCalculator::compute($E2, $D2, $T2, 2, 1, 3, 2), 'custom scheme diff');
check($T2, PointCalculator::compute($E2, $D2, $T2, 1, 0, 4, 0), 'custom scheme tendency');
check(0, PointCalculator::compute($E2, $D2, $T2, 0, 2, 1, 0), 'custom scheme wrong tendency');

echo "\n";
echo $passes . " passed, " . $failures . " failed.\n";
exit($failures === 0 ? 0 : 1);
