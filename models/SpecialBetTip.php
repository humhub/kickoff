<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * @property int $id
 * @property int $special_bet_id
 * @property int $user_id
 * @property string $value
 * @property string $submitted_at
 * @property int|null $points
 */
class SpecialBetTip extends ActiveRecord
{
    public static function tableName()
    {
        return 'kickoff_special_bet_tip';
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
            [['special_bet_id', 'user_id', 'value'], 'required'],
            [['special_bet_id', 'user_id', 'points'], 'integer'],
            [['value'], 'string', 'max' => 255],
            [['special_bet_id', 'user_id'], 'unique', 'targetAttribute' => ['special_bet_id', 'user_id']],
        ];
    }

    public function isScored(): bool
    {
        return $this->points !== null;
    }

    public function getSpecialBet()
    {
        return $this->hasOne(SpecialBet::class, ['id' => 'special_bet_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
