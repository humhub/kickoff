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
    public const STATUS_FINISHED = 'finished';
    public const STATUS_POSTPONED = 'postponed';
    public const STATUS_CANCELLED = 'cancelled';

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
                self::STATUS_SCHEDULED, self::STATUS_LIVE, self::STATUS_FINISHED,
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
     * "Live" is true when the DB status is explicitly LIVE (set by the API/adapter),
     * or when the match is still SCHEDULED but kickoff has passed and we're within
     * roughly 115 minutes (90 + half-time + stoppage). After that we treat it as
     * past its live window — a subsequent adapter sync should flip it to FINISHED.
     */
    public function isLive(): bool
    {
        if ($this->status === self::STATUS_LIVE) {
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
            return (int) $this->current_minute;
        }
        $kickoff = KickoffTime::parse($this->kickoff_at);
        if ($kickoff === null) {
            return 0;
        }
        $elapsedSec = time() - $kickoff;
        return max(0, (int) floor($elapsedSec / 60));
    }

    /**
     * Formats the live minute with the customary stoppage / half-time conventions,
     * e.g. `34'`, `45+3'`, `HT`, `67'`, `90+5'`, `FT`.
     */
    public function getFormattedLiveMinute(): ?string
    {
        $m = $this->getLiveMinute();
        if ($m === null) {
            return null;
        }
        if ($m <= 45) {
            return $m . "'";
        }
        if ($m <= 50) {
            return "45+" . ($m - 45) . "'";
        }
        if ($m <= 65) {
            return 'HT';
        }
        if ($m <= 109) {
            return ($m - 19) . "'";
        }
        if ($m <= 114) {
            return "90+" . ($m - 109) . "'";
        }
        return 'FT';
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
