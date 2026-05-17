<?php
/**
 * Standalone unit tests for FootballDataMatchParser.
 *
 * Run with: `php tests/FootballDataMatchParserTest.php` from the module root.
 * Exit code 0 on success, 1 on any failure.
 *
 * Stubs the Game model just enough to expose the constants the parser refers
 * to — avoids pulling in HumHub's ActiveRecord stack just for unit tests.
 */

declare(strict_types=1);

// Provide a lightweight stand-in for humhub\modules\kickoff\models\Game
// purely for its STAGE_* / STATUS_* constants, before we load the parser.
namespace humhub\modules\kickoff\models {
    if (!class_exists(Game::class, false)) {
        class Game
        {
            public const STAGE_GROUP = 'group';
            public const STAGE_ROUND_OF_32 = 'round_of_32';
            public const STAGE_ROUND_OF_16 = 'round_of_16';
            public const STAGE_QUARTER = 'quarter';
            public const STAGE_SEMI = 'semi';
            public const STAGE_THIRD_PLACE = 'third_place';
            public const STAGE_FINAL = 'final';

            public const STATUS_SCHEDULED = 'scheduled';
            public const STATUS_LIVE = 'live';
            public const STATUS_FINISHED = 'finished';
            public const STATUS_POSTPONED = 'postponed';
            public const STATUS_CANCELLED = 'cancelled';
        }
    }
}

namespace {
    require __DIR__ . '/../adapters/FootballDataMatchParser.php';

    use humhub\modules\kickoff\adapters\FootballDataMatchParser;
    use humhub\modules\kickoff\models\Game;

    $failures = 0;
    $passes = 0;

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

    echo "FootballDataMatchParser::stage():\n";
    eq(Game::STAGE_GROUP, FootballDataMatchParser::stage('GROUP_STAGE'), 'GROUP_STAGE');
    eq(Game::STAGE_ROUND_OF_16, FootballDataMatchParser::stage('LAST_16'), 'LAST_16');
    eq(Game::STAGE_ROUND_OF_32, FootballDataMatchParser::stage('ROUND_OF_32'), 'ROUND_OF_32');
    eq(Game::STAGE_QUARTER, FootballDataMatchParser::stage('QUARTER_FINALS'), 'QUARTER_FINALS');
    eq(Game::STAGE_SEMI, FootballDataMatchParser::stage('SEMI_FINALS'), 'SEMI_FINALS');
    eq(Game::STAGE_THIRD_PLACE, FootballDataMatchParser::stage('THIRD_PLACE'), 'THIRD_PLACE');
    eq(Game::STAGE_THIRD_PLACE, FootballDataMatchParser::stage('THIRD_PLACE_FINAL'), 'THIRD_PLACE_FINAL alias');
    eq(Game::STAGE_FINAL, FootballDataMatchParser::stage('FINAL'), 'FINAL');
    eq(Game::STAGE_GROUP, FootballDataMatchParser::stage(null), 'null falls back to GROUP');
    eq(Game::STAGE_GROUP, FootballDataMatchParser::stage('UNKNOWN_STAGE_X'), 'unknown stage falls back');

    echo "\nFootballDataMatchParser::status():\n";
    eq(Game::STATUS_SCHEDULED, FootballDataMatchParser::status('SCHEDULED'), 'SCHEDULED');
    eq(Game::STATUS_SCHEDULED, FootballDataMatchParser::status('TIMED'), 'TIMED alias');
    eq(Game::STATUS_LIVE, FootballDataMatchParser::status('IN_PLAY'), 'IN_PLAY');
    eq(Game::STATUS_LIVE, FootballDataMatchParser::status('PAUSED'), 'PAUSED');
    eq(Game::STATUS_FINISHED, FootballDataMatchParser::status('FINISHED'), 'FINISHED');
    eq(Game::STATUS_FINISHED, FootballDataMatchParser::status('AWARDED'), 'AWARDED');
    eq(Game::STATUS_POSTPONED, FootballDataMatchParser::status('POSTPONED'), 'POSTPONED');
    eq(Game::STATUS_POSTPONED, FootballDataMatchParser::status('SUSPENDED'), 'SUSPENDED');
    eq(Game::STATUS_CANCELLED, FootballDataMatchParser::status('CANCELLED'), 'CANCELLED');
    eq(Game::STATUS_CANCELLED, FootballDataMatchParser::status('CANCELED'), 'US spelling');
    eq(Game::STATUS_SCHEDULED, FootballDataMatchParser::status(null), 'null falls back');
    eq(Game::STATUS_SCHEDULED, FootballDataMatchParser::status('UNKNOWN'), 'unknown falls back');

    echo "\nFootballDataMatchParser::groupLabel():\n";
    eq('A', FootballDataMatchParser::groupLabel('GROUP_A'), 'GROUP_A → A');
    eq('D', FootballDataMatchParser::groupLabel('GROUP_D'), 'GROUP_D → D');
    eq('L', FootballDataMatchParser::groupLabel('GROUP_L'), 'GROUP_L → L');
    eq('A', FootballDataMatchParser::groupLabel('A'), 'plain "A" preserved');
    eq(null, FootballDataMatchParser::groupLabel(null), 'null → null');
    eq(null, FootballDataMatchParser::groupLabel(''), 'empty → null');
    eq(null, FootballDataMatchParser::groupLabel(42), 'non-string → null');

    echo "\n";
    echo $passes . " passed, " . $failures . " failed.\n";
    exit($failures === 0 ? 0 : 1);
}
