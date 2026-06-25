<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;
use humhub\modules\kickoff\services\KickoffTime;

/**
 * @property int $id
 * @property int $competition_id
 * @property int $home_team_id
 * @property int $away_team_id
 * @property string $kickoff_at
 * @property string $stage
 * @property string|null $round_label
 * @property string|null $group_label
 * @property string $status
 * @property int|null $home_score
 * @property int|null $away_score
 * @property int|null $home_score_et
 * @property int|null $away_score_et
 * @property int|null $home_score_pen
 * @property int|null $away_score_pen
 * @property string|null $external_id
 * @property string|null $last_synced_at
 * @property string|null $venue
 * @property int|null $current_minute
 * @property int|null $matchday_number
 */
class Game extends ActiveRecord
{
    public const STAGE_GROUP = 'group';
    public const STAGE_ROUND_OF_32 = 'round_of_32';
    public const STAGE_ROUND_OF_16 = 'round_of_16';
    public const STAGE_QUARTER = 'quarter';
    public const STAGE_SEMI = 'semi';
    public const STAGE_THIRD_PLACE = 'third_place';
    public const STAGE_FINAL = 'final';

    public const STAGES_KNOCKOUT = [
        self::STAGE_ROUND_OF_32,
        self::STAGE_ROUND_OF_16,
        self::STAGE_QUARTER,
        self::STAGE_SEMI,
        self::STAGE_THIRD_PLACE,
        self::STAGE_FINAL,
    ];

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_LIVE = 'live';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_POSTPONED = 'postponed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Translated display label for a stage slug ('semi' → "Semi-finals" /
     * "Halbfinale"). Shared by every renderer that shows a stage (matchday
     * dropdown, match cards) so labels can't drift apart. Unknown slugs fall
     * back to ucfirst so future stages degrade readably instead of breaking.
     */
    public static function stageLabel(string $stage): string
    {
        return match ($stage) {
            self::STAGE_ROUND_OF_32 => \Yii::t('KickoffModule.base', 'Round of 32'),
            self::STAGE_ROUND_OF_16 => \Yii::t('KickoffModule.base', 'Round of 16'),
            self::STAGE_QUARTER => \Yii::t('KickoffModule.base', 'Quarter-finals'),
            self::STAGE_SEMI => \Yii::t('KickoffModule.base', 'Semi-finals'),
            self::STAGE_THIRD_PLACE => \Yii::t('KickoffModule.base', 'Third place'),
            self::STAGE_FINAL => \Yii::t('KickoffModule.base', 'Final'),
            default => ucfirst($stage),
        };
    }

    public static function tableName()
    {
        return 'kickoff_game';
    }

    public function rules()
    {
        return [
            [['competition_id', 'home_team_id', 'away_team_id', 'kickoff_at', 'stage'], 'required'],
            [['competition_id', 'home_team_id', 'away_team_id'], 'integer'],
            [['home_score', 'away_score', 'home_score_et', 'away_score_et', 'home_score_pen', 'away_score_pen'], 'integer', 'min' => 0],
            [['home_score', 'away_score', 'home_score_et', 'away_score_et', 'home_score_pen', 'away_score_pen'], 'default', 'value' => null],
            [['kickoff_at', 'last_synced_at'], 'safe'],
            [['stage'], 'in', 'range' => [
                self::STAGE_GROUP, self::STAGE_ROUND_OF_32, self::STAGE_ROUND_OF_16,
                self::STAGE_QUARTER, self::STAGE_SEMI, self::STAGE_THIRD_PLACE, self::STAGE_FINAL,
            ]],
            [['status'], 'in', 'range' => [
                self::STATUS_SCHEDULED, self::STATUS_LIVE, self::STATUS_PAUSED, self::STATUS_FINISHED,
                self::STATUS_POSTPONED, self::STATUS_CANCELLED,
            ]],
            [['round_label'], 'string', 'max' => 64],
            [['group_label', 'external_id'], 'string', 'max' => 64],
            [['venue'], 'string', 'max' => 255],
            [['home_team_id'], 'compare', 'compareAttribute' => 'away_team_id', 'operator' => '!=',
                'message' => 'Home and away team must differ.'],
        ];
    }

    public function isKickoffPassed(): bool
    {
        $kickoff = KickoffTime::parse($this->kickoff_at);
        return $kickoff !== null && $kickoff <= time();
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    /**
     * "Live" is true when the DB status is explicitly LIVE or PAUSED (the
     * half-time break, set by the API/adapter), or when the match is still
     * SCHEDULED but kickoff has passed and we're within roughly 115 minutes
     * (90 + half-time + stoppage). After that we treat it as past its live
     * window — a subsequent adapter sync should flip it to FINISHED.
     */
    public function isLive(): bool
    {
        if ($this->status === self::STATUS_LIVE || $this->status === self::STATUS_PAUSED) {
            return true;
        }
        if ($this->status !== self::STATUS_SCHEDULED) {
            return false;
        }
        $kickoff = KickoffTime::parse($this->kickoff_at);
        if ($kickoff === null) {
            return false;
        }
        $elapsedSec = time() - $kickoff;
        return $elapsedSec >= 0 && $elapsedSec < 115 * 60;
    }

    public function getLiveMinute(): ?int
    {
        if (!$this->isLive()) {
            return null;
        }
        if ($this->current_minute !== null) {
            // The live-data adapters store the true match minute (the football
            // clock) exactly as reported — return it verbatim.
            return (int) $this->current_minute;
        }
        // No live minute from the data source (e.g. the manual adapter, or an
        // API response without a minute field): estimate it from wall-clock
        // time since kickoff.
        $kickoff = KickoffTime::parse($this->kickoff_at);
        if ($kickoff === null) {
            return 0;
        }
        return self::estimateMatchMinute(time() - $kickoff);
    }

    /**
     * Formats the live minute the way broadcasters do, e.g. `34'`, `90'`,
     * `90+3'` — or "HT" during the half-time break. Returns null when the
     * match is not live.
     */
    public function getFormattedLiveMinute(): ?string
    {
        $m = $this->getLiveMinute();
        if ($m === null) {
            return null;
        }
        if ($this->status === self::STATUS_PAUSED) {
            return \Yii::t('KickoffModule.base', 'HT');
        }
        return self::formatMatchMinute($m);
    }

    /**
     * Renders a true match minute (the football clock) with the customary
     * stoppage convention: `34'`, `90'`, and added time past 90 as `90+3'`.
     * First-half stoppage isn't distinguishable from a plain minute here, so
     * 46'–50' simply render as themselves.
     */
    public static function formatMatchMinute(int $minute): string
    {
        if ($minute > 90) {
            return '90+' . ($minute - 90) . "'";
        }
        return max(0, $minute) . "'";
    }

    /**
     * Estimates the match minute from wall-clock seconds since kickoff, used
     * only when the data source supplies no live minute. A ~15-minute
     * half-time break is discounted so the estimate tracks the match clock
     * rather than real elapsed time.
     */
    public static function estimateMatchMinute(int $elapsedSec): int
    {
        $elapsedMin = (int) floor($elapsedSec / 60);
        if ($elapsedMin <= 45) {
            return max(0, $elapsedMin);
        }
        if ($elapsedMin <= 60) {
            // Half-time break: hold the clock at 45'.
            return 45;
        }
        return $elapsedMin - 15;
    }

    public function isKnockout(): bool
    {
        return in_array($this->stage, self::STAGES_KNOCKOUT, true);
    }

    public function getCompetition()
    {
        return $this->hasOne(Competition::class, ['id' => 'competition_id']);
    }

    public function getHomeTeam()
    {
        return $this->hasOne(Team::class, ['id' => 'home_team_id']);
    }

    public function getAwayTeam()
    {
        return $this->hasOne(Team::class, ['id' => 'away_team_id']);
    }

    public function getTips()
    {
        return $this->hasMany(Tip::class, ['game_id' => 'id']);
    }
}
