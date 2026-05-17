<?php

/**
 * Standalone unit tests for TeamNameLocalizer.
 *
 * Run with: `php tests/TeamNameLocalizerTest.php` from the module root.
 * Exit code 0 on success, 1 on any failure.
 *
 * The `localize()` cases need the Intl extension; if it isn't loaded the
 * suite skips them rather than failing — but it always covers the pure
 * `normalizeToIso2()` helper.
 */

declare(strict_types=1);

require __DIR__ . '/../services/TeamNameLocalizer.php';

use humhub\modules\kickoff\services\TeamNameLocalizer;

$failures = 0;
$passes = 0;
$skipped = 0;

function eq(mixed $expected, mixed $actual, string $message): void
{
    global $failures, $passes;
    if ($expected === $actual) {
        echo "  ✓ {$message}\n";
        $passes++;
        return;
    }
    $e = var_export($expected, true);
    $a = var_export($actual, true);
    echo "  ✗ {$message}: expected {$e}, got {$a}\n";
    $failures++;
}

function skip(string $message): void
{
    global $skipped;
    echo "  ~ {$message} (skipped)\n";
    $skipped++;
}

echo "TeamNameLocalizer::normalizeToIso2() — passes alpha-2 through:\n";
eq('DE', TeamNameLocalizer::normalizeToIso2('DE'), 'DE');
eq('US', TeamNameLocalizer::normalizeToIso2('us'), 'lowercase us → US');
eq('BR', TeamNameLocalizer::normalizeToIso2('  BR  '), 'whitespace trimmed');

echo "\nTeamNameLocalizer::normalizeToIso2() — FIFA / ISO-3 mappings:\n";
eq('DE', TeamNameLocalizer::normalizeToIso2('GER'), 'GER → DE (FIFA)');
eq('DE', TeamNameLocalizer::normalizeToIso2('DEU'), 'DEU → DE (ISO-3)');
eq('CH', TeamNameLocalizer::normalizeToIso2('SUI'), 'SUI → CH');
eq('CH', TeamNameLocalizer::normalizeToIso2('CHE'), 'CHE → CH');
eq('NL', TeamNameLocalizer::normalizeToIso2('NED'), 'NED → NL');
eq('UY', TeamNameLocalizer::normalizeToIso2('URY'), 'URY → UY');
eq('UY', TeamNameLocalizer::normalizeToIso2('URU'), 'URU → UY');
eq('SA', TeamNameLocalizer::normalizeToIso2('KSA'), 'KSA → SA');
eq('CL', TeamNameLocalizer::normalizeToIso2('CHI'), 'CHI → CL');
eq('CD', TeamNameLocalizer::normalizeToIso2('COD'), 'COD (DR Congo) → CD');

echo "\nTeamNameLocalizer::normalizeToIso2() — unknown / empty inputs:\n";
eq(null, TeamNameLocalizer::normalizeToIso2(null), 'null → null');
eq(null, TeamNameLocalizer::normalizeToIso2(''), 'empty → null');
eq(null, TeamNameLocalizer::normalizeToIso2('   '), 'whitespace → null');
eq(null, TeamNameLocalizer::normalizeToIso2('XYZ'), 'unknown 3-letter → null');
eq(null, TeamNameLocalizer::normalizeToIso2('TOOLONG'), 'long string → null');

if (!class_exists(\Locale::class)) {
    echo "\nTeamNameLocalizer::localize() — Intl not available, skipping locale-aware cases\n";
    skip('localize() de — Germany');
    skip('localize() en — Germany');
    skip('localize() fr — Brazil');
    skip('localize() fallback for unknown code');
} else {
    echo "\nTeamNameLocalizer::localize() — Intl-backed translation:\n";
    eq('Deutschland', TeamNameLocalizer::localize('GER', 'Germany', 'de'), 'GER + de → Deutschland');
    eq('Germany', TeamNameLocalizer::localize('GER', 'Germany', 'en'), 'GER + en → Germany');
    eq('Brésil', TeamNameLocalizer::localize('BRA', 'Brazil', 'fr'), 'BRA + fr → Brésil');
    eq('Schweiz', TeamNameLocalizer::localize('CH', 'Switzerland', 'de'), 'ISO-2 CH + de → Schweiz');

    echo "\nTeamNameLocalizer::localize() — fallback to stored name:\n";
    eq('FC Bayern', TeamNameLocalizer::localize(null, 'FC Bayern', 'de'), 'no code → stored name');
    eq('FC Bayern', TeamNameLocalizer::localize('', 'FC Bayern', 'de'), 'empty code → stored name');
    eq('Mystery Club', TeamNameLocalizer::localize('XYZ', 'Mystery Club', 'de'), 'unknown code → stored name');
}

echo "\n";
$total = $passes + $failures + $skipped;
echo "{$passes} passed, {$failures} failed";
if ($skipped > 0) {
    echo ", {$skipped} skipped";
}
echo " (of {$total}).\n";
exit($failures === 0 ? 0 : 1);
