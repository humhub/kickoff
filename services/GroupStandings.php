<?php

namespace humhub\modules\kickoff\services;

/**
 * Pure group-standings math, dependency-free for unit testing.
 *
 * Computes 3-1-0 points, goal difference, and goals scored per team from a
 * list of finished match results, then ranks by (points DESC, GD DESC,
 * goals-for DESC). Real WM tie-breakers include head-to-head, which this
 * helper skips on purpose — for a tippspiel-grade group winner the simple
 * triad is enough, and equal-stat ties are extremely rare in practice.
 */
final class GroupStandings
{
    /**
     * @param array<int, array{home: int, away: int, homeScore: int, awayScore: int}> $results
     * @return list<array{teamId: int, points: int, diff: int, for: int}> sorted by rank
     */
    public static function ranked(array $results): array
    {
        $stats = [];
        foreach ($results as $r) {
            foreach ([$r['home'], $r['away']] as $tid) {
                $stats[$tid] ??= ['teamId' => $tid, 'points' => 0, 'diff' => 0, 'for' => 0];
            }
            $hs = (int) $r['homeScore'];
            $as = (int) $r['awayScore'];
            $stats[$r['home']]['for'] += $hs;
            $stats[$r['home']]['diff'] += $hs - $as;
            $stats[$r['away']]['for'] += $as;
            $stats[$r['away']]['diff'] += $as - $hs;
            if ($hs > $as) {
                $stats[$r['home']]['points'] += 3;
            } elseif ($hs < $as) {
                $stats[$r['away']]['points'] += 3;
            } else {
                $stats[$r['home']]['points'] += 1;
                $stats[$r['away']]['points'] += 1;
            }
        }
        $ranked = array_values($stats);
        usort($ranked, fn($a, $b) => [$b['points'], $b['diff'], $b['for']]
            <=> [$a['points'], $a['diff'], $a['for']]);
        return $ranked;
    }

    /**
     * @param array<int, array{home: int, away: int, homeScore: int, awayScore: int}> $results
     * @return int|null team id of the leader, or null on empty input
     */
    public static function winner(array $results): ?int
    {
        $ranked = self::ranked($results);
        return $ranked === [] ? null : $ranked[0]['teamId'];
    }
}
