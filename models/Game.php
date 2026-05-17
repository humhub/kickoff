<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;

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
        return strtotime($this->kickoff_at) <= time();
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
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
