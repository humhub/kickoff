<?php
/**
 * Runs every standalone unit-test script in this directory in a subprocess so
 * one failing suite doesn't take the others down. Exit code 0 means every
 * suite passed, 1 means at least one had failures.
 *
 * Run with: `php tests/run.php` from the module root.
 */

declare(strict_types=1);

$tests = [
    'PointCalculator' => __DIR__ . '/PointCalculatorTest.php',
    'WinProbabilityCalculator' => __DIR__ . '/WinProbabilityCalculatorTest.php',
    'GroupStandings' => __DIR__ . '/GroupStandingsTest.php',
    'FootballDataMatchParser' => __DIR__ . '/FootballDataMatchParserTest.php',
    'TeamNameLocalizer' => __DIR__ . '/TeamNameLocalizerTest.php',
];

$totalFailures = 0;
foreach ($tests as $name => $path) {
    echo "\n=== {$name} ===\n";
    $exitCode = 0;
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path), $exitCode);
    if ($exitCode !== 0) {
        $totalFailures++;
    }
}

echo "\n";
if ($totalFailures === 0) {
    echo "All suites passed.\n";
    exit(0);
}
echo "{$totalFailures} suite(s) failed.\n";
exit(1);
