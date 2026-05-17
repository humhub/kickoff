<?php

/**
 * Standalone unit tests for GroupStandings.
 *
 * Run with: `php tests/GroupStandingsTest.php` from the module root.
 * Exit code 0 on success, 1 on any failure.
 */

declare(strict_types=1);

require __DIR__ . '/../services/GroupStandings.php';

use humhub\modules\kickoff\services\GroupStandings;

$failures = 0;
$passes = 0;

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

function r(int $h, int $a, int $hs, int $as): array
{
    return ['home' => $h, 'away' => $a, 'homeScore' => $hs, 'awayScore' => $as];
}

echo "GroupStandings — empty group:\n";
check(GroupStandings::winner([]) === null, 'winner() of [] is null');
check(GroupStandings::ranked([]) === [], 'ranked() of [] is []');

echo "\nGroupStandings — three-way group, clear winner by points:\n";
// Team 1 beats 2, 3 beats 1, 2 beats 3 → all on 3 points; ranked by GD then GF.
$results = [
    r(1, 2, 2, 0),
    r(3, 1, 1, 0),
    r(2, 3, 3, 1),
];
$ranked = GroupStandings::ranked($results);
check(count($ranked) === 3, 'three teams in standings');
foreach ($ranked as $row) {
    check($row['points'] === 3, "team {$row['teamId']} has 3 points");
}

echo "\nGroupStandings — clear leader by points:\n";
$results = [
    r(1, 2, 3, 0), // 1 wins
    r(1, 3, 2, 0), // 1 wins
    r(2, 3, 1, 1), // draw
];
$winner = GroupStandings::winner($results);
check($winner === 1, 'team 1 with 6 points wins');
$ranked = GroupStandings::ranked($results);
check($ranked[0]['points'] === 6, 'leader has 6 points');
check($ranked[0]['diff'] === 5, 'leader GD is +5');
check($ranked[0]['for'] === 5, 'leader scored 5');

echo "\nGroupStandings — tie on points, goal diff breaks it:\n";
$results = [
    r(1, 2, 3, 0),  // team 1 wins big
    r(3, 4, 1, 0),  // team 3 wins narrow
    r(1, 3, 0, 0),  // 1 and 3 each on 4 points
    r(2, 4, 2, 1),  // team 2 on 3 points
];
$winner = GroupStandings::winner($results);
check($winner === 1, 'team 1 wins on goal difference');

echo "\nGroupStandings — tie on points and GD, goals scored breaks it:\n";
$results = [
    r(1, 2, 4, 1),  // 1 wins +3, scored 4
    r(3, 4, 3, 0),  // 3 wins +3, scored 3
    r(1, 3, 0, 0),
    r(2, 4, 0, 0),
];
$winner = GroupStandings::winner($results);
check($winner === 1, 'team 1 wins on goals-for (4 > 3)');

echo "\nGroupStandings — all draws → leader by GD, then goals-for:\n";
$results = [
    r(1, 2, 2, 2),
    r(3, 4, 0, 0),
    r(1, 3, 1, 1),
];
$ranked = GroupStandings::ranked($results);
// Team 1 played 2 draws (2 points, GD 0, GF 3); team 2 played 1 (1 pt, GD 0, GF 2)
// team 3 played 2 (2 pts, GD 0, GF 1); team 4 played 1 (1 pt, GD 0, GF 0)
check($ranked[0]['teamId'] === 1, 'team 1 first (2 pts, GF 3)');
check($ranked[0]['points'] === 2, 'leader points');
check($ranked[1]['teamId'] === 3, 'team 3 second (2 pts, GF 1)');

echo "\n";
echo $passes . " passed, " . $failures . " failed.\n";
exit($failures === 0 ? 0 : 1);
