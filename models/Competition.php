<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;
use humhub\modules\kickoff\Module;
use humhub\modules\user\models\User;
use Yii;
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
 * @property int $tips_visible_before_kickoff
 * @property string|null $info_page_title
 * @property string|null $info_page_content
 * @property int $show_in_main_menu
 * @property string|null $menu_title
 * @property int $show_probabilities
 * @property string|null $banner_image_url
 * @property int $is_restricted
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

    public function beforeValidate()
    {
        if (empty($this->slug) && !empty($this->name)) {
            $this->slug = $this->generateSlug($this->name);
        }
        return parent::beforeValidate();
    }

    public function attributeLabels()
    {
        return [
            'name' => Yii::t('KickoffModule.base', 'Name'),
            'slug' => Yii::t('KickoffModule.base', 'URL slug'),
            'type' => Yii::t('KickoffModule.base', 'Type'),
            'season' => Yii::t('KickoffModule.base', 'Season'),
            'starts_at' => Yii::t('KickoffModule.base', 'Starts at'),
            'ends_at' => Yii::t('KickoffModule.base', 'Ends at'),
            'status' => Yii::t('KickoffModule.base', 'Status'),
            'is_test' => Yii::t('KickoffModule.base', 'Test competition (sandbox)'),
            'scoring_scheme_id' => Yii::t('KickoffModule.base', 'Scoring scheme'),
            'ko_scoring_mode' => Yii::t('KickoffModule.base', 'Knockout scoring'),
            'data_source' => Yii::t('KickoffModule.base', 'Data source'),
            'tips_visible_before_kickoff' => Yii::t(
                'KickoffModule.base',
                'Show other users\' tips before kickoff',
            ),
            'info_page_title' => Yii::t('KickoffModule.base', 'Info page title'),
            'info_page_content' => Yii::t('KickoffModule.base', 'Info page content (Markdown)'),
            'is_restricted' => Yii::t('KickoffModule.base', 'Restricted access'),
            'show_in_main_menu' => Yii::t('KickoffModule.base', 'Show as own entry in the main menu'),
            'menu_title' => Yii::t('KickoffModule.base', 'Main-menu label (optional)'),
            'show_probabilities' => Yii::t('KickoffModule.base', 'Show win probabilities on match cards'),
            'banner_image_url' => Yii::t('KickoffModule.base', 'Banner image URL'),
        ];
    }

    public function getMenuLabel(): string
    {
        return $this->menu_title !== null && trim((string) $this->menu_title) !== ''
            ? $this->menu_title
            : $this->name;
    }

    public function hasInfoPage(): bool
    {
        return !empty($this->info_page_title) && !empty(trim((string) $this->info_page_content));
    }

    private function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return $slug !== '' ? substr($slug, 0, 100) : 'competition-' . time();
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
            [['data_source'], 'in', 'range' => fn() => array_map(
                static fn($adapter) => $adapter->getKey(),
                Module::instance()->getAdapterRegistry()->all(),
            )],
            [['data_source_config', 'info_page_content'], 'string'],
            [['data_source_config'], 'validateDataSourceConfig'],
            [['info_page_title'], 'string', 'max' => 255],
            [['banner_image_url'], 'string', 'max' => 500],
            [['banner_image_url'], 'url', 'defaultScheme' => 'https'],
            [['scoring_scheme_id', 'is_test', 'created_by'], 'integer'],
            [['scoring_scheme_id'], 'exist', 'targetClass' => ScoringScheme::class, 'targetAttribute' => 'id'],
            [['season'], 'string', 'max' => 32],
            [['last_synced_at'], 'safe'],
            [['starts_at', 'ends_at'], 'filter', 'filter' => static fn($value) => $value === '' ? null : $value],
            [['starts_at', 'ends_at'], 'validateDate'],
            [['ends_at'], 'validateEndsAfterStart'],
            [['is_test', 'tips_visible_before_kickoff', 'show_in_main_menu', 'show_probabilities', 'is_restricted'], 'boolean'],
            [['is_restricted'], 'default', 'value' => 0],
            [['menu_title'], 'string', 'max' => 255],
        ];
    }

    /**
     * Accepts a plain date (the admin form) or a full database datetime.
     */
    public function validateDate($attribute): void
    {
        $value = $this->$attribute;
        if ($value === null || $this->hasErrors($attribute)) {
            return;
        }
        if (!is_string($value) || $this->parseDate($value) === null) {
            $this->addError($attribute, Yii::t('KickoffModule.base', '{attribute} is not a valid date.', [
                'attribute' => $this->getAttributeLabel($attribute),
            ]));
        }
    }

    public function validateEndsAfterStart($attribute): void
    {
        if ($this->hasErrors('starts_at') || $this->hasErrors($attribute)) {
            return;
        }
        $start = is_string($this->starts_at) ? $this->parseDate($this->starts_at) : null;
        $end = is_string($this->ends_at) ? $this->parseDate($this->ends_at) : null;
        if ($start !== null && $end !== null && $end < $start) {
            $this->addError($attribute, Yii::t('KickoffModule.base', '{attribute} must not be before "{start}".', [
                'attribute' => $this->getAttributeLabel($attribute),
                'start' => $this->getAttributeLabel('starts_at'),
            ]));
        }
    }

    public function validateDataSourceConfig($attribute): void
    {
        $value = $this->$attribute;
        if ($value === null || trim((string) $value) === '') {
            $this->$attribute = null;
            return;
        }
        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            $this->addError($attribute, Yii::t('KickoffModule.base', 'The data source config must be a valid JSON object.'));
        }
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        foreach (['!Y-m-d', '!Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date;
            }
        }
        return null;
    }

    public function tipsVisibleForGame(Game $game): bool
    {
        return (bool) $this->tips_visible_before_kickoff || $game->isKickoffPassed();
    }

    public function isTest(): bool
    {
        return (bool) $this->is_test;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRestricted(): bool
    {
        return (bool) $this->is_restricted;
    }

    public function isPublic(): bool
    {
        return !$this->isRestricted();
    }

    /**
     * Whether the current user may view this competition. Public competitions
     * are open to every logged-in member; restricted ones require a Kickoff
     * view-tier permission ({@see Module::canView()}). A draft is not published
     * yet and is only visible to those who manage Kickoff. Login itself is
     * enforced by the controller access rules.
     *
     * Evaluates the current web user via Yii::$app->user — call only in a
     * request context, never from console/cron (no current user there), and
     * not to test a *specific* user (it always checks the logged-in one).
     */
    public function canView(): bool
    {
        if ($this->status === self::STATUS_DRAFT) {
            return Module::canManage();
        }

        return $this->isPublic() || Module::canView();
    }

    /**
     * Whether the current user may place tips/bets in this competition. Only
     * active competitions accept tips. Public competitions are open to every
     * logged-in member; restricted ones require the participate tier
     * ({@see Module::canParticipate()}).
     *
     * Like {@see canView()}, this evaluates the current web user — request
     * context only, not console/cron, and not for an arbitrary target user.
     */
    public function canParticipate(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return $this->isPublic() || Module::canParticipate();
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
