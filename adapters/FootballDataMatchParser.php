<?php

namespace humhub\modules\kickoff\adapters;

use humhub\modules\kickoff\models\Game;

/**
 * Pure helpers for normalizing football-data.org API match payloads into the
 * module's internal vocabulary. Kept dependency-free so unit tests can run
 * without bootstrapping HumHub.
 */
final class FootballDataMatchParser
{
    public const STAGE_MAP = [
        'GROUP_STAGE' => Game::STAGE_GROUP,
        'PRELIMINARY_ROUND' => Game::STAGE_GROUP,
        'FIRST_ROUND' => Game::STAGE_GROUP,
        'REGULAR_SEASON' => Game::STAGE_GROUP,
        'PLAYOFF_ROUND_1' => Game::STAGE_ROUND_OF_32,
        // football-data.org names the 48-team FWC 2026 first knockout round
        // LAST_32 (its enum uses LAST_n, not ROUND_OF_n). Without this entry the
        // round-of-32 fixtures fall through to the STAGE_GROUP default below and
        // pollute the group-stage matchday list. ROUND_OF_32 is kept as a
        // defensive alias even though the live API does not emit it.
        'LAST_32' => Game::STAGE_ROUND_OF_32,
        'ROUND_OF_32' => Game::STAGE_ROUND_OF_32,
        'LAST_16' => Game::STAGE_ROUND_OF_16,
        'ROUND_OF_16' => Game::STAGE_ROUND_OF_16,
        'QUARTER_FINALS' => Game::STAGE_QUARTER,
        'SEMI_FINALS' => Game::STAGE_SEMI,
        'THIRD_PLACE' => Game::STAGE_THIRD_PLACE,
        'THIRD_PLACE_FINAL' => Game::STAGE_THIRD_PLACE,
        'FINAL' => Game::STAGE_FINAL,
    ];

    public const STATUS_MAP = [
        'SCHEDULED' => Game::STATUS_SCHEDULED,
        'TIMED' => Game::STATUS_SCHEDULED,
        'IN_PLAY' => Game::STATUS_LIVE,
        'PAUSED' => Game::STATUS_PAUSED,
        'FINISHED' => Game::STATUS_FINISHED,
        'AWARDED' => Game::STATUS_FINISHED,
        'POSTPONED' => Game::STATUS_POSTPONED,
        'SUSPENDED' => Game::STATUS_POSTPONED,
        'CANCELLED' => Game::STATUS_CANCELLED,
        'CANCELED' => Game::STATUS_CANCELLED,
    ];

    public static function stage(?string $apiStage): string
    {
        return self::STAGE_MAP[$apiStage ?? ''] ?? Game::STAGE_GROUP;
    }

    public static function status(?string $apiStatus): string
    {
        return self::STATUS_MAP[$apiStatus ?? ''] ?? Game::STATUS_SCHEDULED;
    }

    /**
     * Strips football-data's "GROUP_" prefix; returns null for missing/blank
     * values so callers can leave a previously stored label intact.
     */
    public static function groupLabel(mixed $apiGroup): ?string
    {
        if (!is_string($apiGroup) || $apiGroup === '') {
            return null;
        }
        return preg_replace('/^GROUP_/', '', $apiGroup);
    }

    /**
     * Normalizes football-data.org's `score` node into the module's six score
     * fields.
     *
     * Crucially, football-data's `fullTime` is the *cumulative* final result
     * — it already includes extra-time goals AND the penalty shootout — so it
     * is NOT the score after 90 minutes. The 90-minute result lives in a
     * separate `regularTime` node that only appears once a match goes past 90'
     * (matches decided in regulation carry just `fullTime`). The `extraTime`
     * and `penalties` nodes each contain only the goals scored *within* that
     * period, so the cumulative score at the end of extra time is
     * `regularTime + extraTime`.
     *
     * Mapping the module expects:
     *   - home_score / away_score      → score after 90 min (regularTime ?? fullTime)
     *   - home_score_et / away_score_et → score at end of extra time (regularTime + extraTime)
     *   - home_score_pen / away_score_pen → penalty shootout goals
     *
     * @param array<string,mixed> $score the API match's `score` node
     * @return array{home_score:?int, away_score:?int, home_score_et:?int, away_score_et:?int, home_score_pen:?int, away_score_pen:?int}
     */
    public static function scores(array $score): array
    {
        $regular = is_array($score['regularTime'] ?? null) ? $score['regularTime'] : null;
        $full = is_array($score['fullTime'] ?? null) ? $score['fullTime'] : [];
        $extra = is_array($score['extraTime'] ?? null) ? $score['extraTime'] : null;
        $pens = is_array($score['penalties'] ?? null) ? $score['penalties'] : null;

        $int = static fn($v): ?int => is_numeric($v) ? (int) $v : null;

        // Score after 90 minutes: regularTime when present (ET/penalty matches),
        // otherwise fullTime (regular-time matches carry no regularTime node).
        $home90 = $int($regular['home'] ?? $full['home'] ?? null);
        $away90 = $int($regular['away'] ?? $full['away'] ?? null);

        // Cumulative score at the end of extra time = 90-min score + ET-only
        // goals. Requires an explicit regularTime base so we never add ET goals
        // onto a fullTime value that already contains them.
        $homeEt = $awayEt = null;
        if ($regular !== null && $extra !== null
            && is_numeric($regular['home'] ?? null) && is_numeric($regular['away'] ?? null)
            && is_numeric($extra['home'] ?? null) && is_numeric($extra['away'] ?? null)
        ) {
            $homeEt = (int) $regular['home'] + (int) $extra['home'];
            $awayEt = (int) $regular['away'] + (int) $extra['away'];
        }

        return [
            'home_score' => $home90,
            'away_score' => $away90,
            'home_score_et' => $homeEt,
            'away_score_et' => $awayEt,
            'home_score_pen' => $int($pens['home'] ?? null),
            'away_score_pen' => $int($pens['away'] ?? null),
        ];
    }
}
