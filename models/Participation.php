<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * @property int $competition_id
 * @property int $user_id
 * @property string $joined_at
 * @property string|null $display_name
 */
class Participation extends ActiveRecord
{
    public static function tableName()
    {
        return 'kickoff_participation';
    }

    public static function primaryKey()
    {
        return ['competition_id', 'user_id'];
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'joined_at',
                'updatedAtAttribute' => false,
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function rules()
    {
        return [
            [['competition_id', 'user_id'], 'required'],
            [['competition_id', 'user_id'], 'integer'],
            [['display_name'], 'string', 'max' => 255],
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
