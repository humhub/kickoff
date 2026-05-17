<?php

namespace humhub\modules\kickoff\services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Single source of truth for time handling inside the Kickoff module.
 *
 * All DB datetime columns (`kickoff_at`, `last_synced_at`, `deadline_at`,
 * `resolved_at`, `created_at`, ...) are written and read as UTC, independent
 * of `Yii::$app->timeZone` or PHP's default timezone. Display-layer
 * conversion to the viewer's timezone is HumHub's job (via the Yii
 * formatter); this helper makes sure the underlying epoch math stays
 * correct regardless of server configuration.
 *
 * Why this exists: bare `strtotime($dbString)` interprets the string in
 * PHP's default timezone. If the server isn't UTC, a row written with
 * `gmdate()` (UTC) gets parsed back as if it were local, shifting the
 * epoch by however many hours the offset is. `isLive()`, `isKickoffPassed()`
 * and friends would silently lie. Always go through the helpers below.
 */
final class KickoffTime
{
    /**
     * Parses a DB datetime string as UTC and returns the epoch seconds,
     * or null if the input is empty / malformed.
     *
     * Strings that already carry a timezone (ISO-8601 with `Z` or `±HH:MM`)
     * are respected — only naive strings are forced to UTC.
     */
    public static function parse(?string $dbDateTime): ?int
    {
        if ($dbDateTime === null || trim($dbDateTime) === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($dbDateTime, new DateTimeZone('UTC')))->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Current time as a `Y-m-d H:i:s` UTC string, suitable for direct DB write.
     */
    public static function nowDb(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Formats an epoch timestamp as a `Y-m-d H:i:s` UTC string.
     */
    public static function dbAt(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
