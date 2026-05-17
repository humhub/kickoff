<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * @property int $id
 * @property int $game_id
 * @property int $user_id
 * @property int $home_score
 * @property int $away_score
 * @property string $submitted_at
 * @property int $locked
 * @property int|null $points
 */
class Tip extends ActiveRecord
{
    public static function tableName()
    {
        return 'kickoff_tip';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'submitted_at',
                'updatedAtAttribute' => 'submitted_at',
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function rules()
    {
        return [
            [['game_id', 'user_id', 'home_score', 'away_score'], 'required'],
            [['game_id', 'user_id'], 'integer'],
            [['home_score', 'away_score'], 'integer', 'min' => 0, 'max' => 99],
            [['locked', 'points'], 'integer'],
            [['game_id', 'user_id'], 'unique', 'targetAttribute' => ['game_id', 'user_id']],
        ];
    }

    public function isLocked(): bool
    {
        return (bool) $this->locked;
    }

    public function isScored(): bool
    {
        return $this->points !== null;
    }

    public function getGame()
    {
        return $this->hasOne(Game::class, ['id' => 'game_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
