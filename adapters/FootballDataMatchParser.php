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
        'PAUSED' => Game::STATUS_LIVE,
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
}
