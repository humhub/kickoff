<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string|null $season
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property string $status
 * @property int $is_test
 * @property int $scoring_scheme_id
 * @property string $ko_scoring_mode
 * @property string $data_source
 * @property string|null $data_source_config
 * @property string|null $last_synced_at
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property int|null $created_by
 */
class Competition extends ActiveRecord
{
    public const TYPE_TOURNAMENT = 'tournament';
    public const TYPE_LEAGUE = 'league';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_ARCHIVED = 'archived';

    public const KO_REGULAR_TIME = 'regular_time';
    public const KO_FULL_TIME = 'full_time';

    public const DATA_SOURCE_MANUAL = 'manual';
    public const DATA_SOURCE_MOCK = 'mock';
    public const DATA_SOURCE_FOOTBALL_DATA = 'football-data';
    public const DATA_SOURCE_OPENLIGA = 'openliga';

    public static function tableName()
    {
        return 'kickoff_competition';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function rules()
    {
        return [
            [['name', 'slug', 'type', 'scoring_scheme_id'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['slug'], 'string', 'max' => 100],
            [['slug'], 'unique'],
            [['slug'], 'match', 'pattern' => '/^[a-z0-9][a-z0-9\-]*$/'],
            [['type'], 'in', 'range' => [self::TYPE_TOURNAMENT, self::TYPE_LEAGUE]],
            [['status'], 'in', 'range' => [
                self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_FINISHED, self::STATUS_ARCHIVED,
            ]],
            [['ko_scoring_mode'], 'in', 'range' => [self::KO_REGULAR_TIME, self::KO_FULL_TIME]],
            [['data_source'], 'string', 'max' => 64],
            [['data_source_config'], 'string'],
            [['scoring_scheme_id', 'is_test', 'created_by'], 'integer'],
            [['season'], 'string', 'max' => 32],
            [['starts_at', 'ends_at', 'last_synced_at'], 'safe'],
            [['is_test'], 'boolean'],
        ];
    }

    public function isTest(): bool
    {
        return (bool) $this->is_test;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getDataSourceConfig(): array
    {
        return $this->data_source_config ? (json_decode($this->data_source_config, true) ?? []) : [];
    }

    public function setDataSourceConfig(array $config): void
    {
        $this->data_source_config = $config === [] ? null : json_encode($config);
    }

    public function getScoringScheme()
    {
        return $this->hasOne(ScoringScheme::class, ['id' => 'scoring_scheme_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    public function getGames()
    {
        return $this->hasMany(Game::class, ['competition_id' => 'id']);
    }

    public function getTeams()
    {
        return $this->hasMany(Team::class, ['id' => 'team_id'])
            ->viaTable('kickoff_competition_team', ['competition_id' => 'id']);
    }

    public function getParticipations()
    {
        return $this->hasMany(Participation::class, ['competition_id' => 'id']);
    }

    public function getSpecialBets()
    {
        return $this->hasMany(SpecialBet::class, ['competition_id' => 'id']);
    }
}
