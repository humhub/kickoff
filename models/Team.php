<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * @property int $id
 * @property string $name
 * @property string|null $short_name
 * @property string|null $country_code
 * @property string|null $logo_url
 * @property string|null $external_ids
 * @property string|null $created_at
 */
class Team extends ActiveRecord
{
    public static function tableName()
    {
        return 'kickoff_team';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false,
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['short_name'], 'string', 'max' => 64],
            [['country_code'], 'string', 'max' => 8],
            [['logo_url'], 'string', 'max' => 500],
            [['external_ids'], 'string'],
        ];
    }

    public function getExternalIds(): array
    {
        return $this->external_ids ? (json_decode($this->external_ids, true) ?? []) : [];
    }

    public function setExternalIds(array $ids): void
    {
        $this->external_ids = $ids === [] ? null : json_encode($ids);
    }

    public function getExternalId(string $source): ?string
    {
        $ids = $this->getExternalIds();
        return isset($ids[$source]) ? (string) $ids[$source] : null;
    }

    public static function findByExternalId(string $source, string $id): ?self
    {
        return self::find()
            ->andWhere(['like', 'external_ids', '"' . $source . '":"' . $id . '"'])
            ->one();
    }
}
