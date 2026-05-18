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
 * @property int|null $fifa_points
 * @property int|null $elo_rating
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
            [['fifa_points', 'elo_rating'], 'integer', 'min' => 0],
            [['fifa_points', 'elo_rating'], 'default', 'value' => null],
        ];
    }

    /**
     * Returns the team's name in the current HumHub UI language when the team
     * is a nation (country code is set and resolvable via ICU/CLDR);
     * otherwise the stored name. Use this in views instead of `$team->name`
     * so participants see "Deutschland" on a German interface and "Germany"
     * on an English one without any translation file maintenance.
     */
    public function getDisplayName(): string
    {
        $lang = \Yii::$app->language ?? null;
        return \humhub\modules\kickoff\services\TeamNameLocalizer::localize(
            $this->country_code,
            (string) ($this->name ?? ''),
            $lang,
        );
    }

    /**
     * Combined strength rating for win-probability calculations. Averages
     * world-ranking points and Elo rating (both on a similar ~1000–2200 scale
     * for national teams), falls back to whichever is set, or null if neither.
     */
    public function getStrengthRating(): ?float
    {
        $values = array_values(array_filter(
            [$this->fifa_points, $this->elo_rating],
            fn($v) => $v !== null && (int) $v > 0,
        ));
        if ($values === []) {
            return null;
        }
        return array_sum($values) / count($values);
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
