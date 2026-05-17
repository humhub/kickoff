<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;

/**
 * @property int $competition_id
 * @property string $matchday_key
 * @property int $user_id
 * @property int $points
 * @property string $awarded_at
 */
class MatchdayBonus extends ActiveRecord
{
    public static function tableName()
    {
        return 'kickoff_matchday_bonus';
    }

    public static function primaryKey()
    {
        return ['competition_id', 'matchday_key', 'user_id'];
    }

    public function rules()
    {
        return [
            [['competition_id', 'matchday_key', 'user_id', 'points', 'awarded_at'], 'required'],
            [['competition_id', 'user_id', 'points'], 'integer'],
            [['matchday_key'], 'string', 'max' => 64],
            [['awarded_at'], 'safe'],
        ];
    }

    public function getCompetition()
    {
        return $this->hasOne(Competition::class, ['id' => 'competition_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
