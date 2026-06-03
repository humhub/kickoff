<?php

namespace humhub\modules\kickoff;

use humhub\modules\kickoff\adapters\AdapterRegistry;
use humhub\modules\kickoff\permissions\ManageKickoff;
use humhub\modules\kickoff\permissions\Participate;
use humhub\modules\kickoff\permissions\ViewLeaderboard;
use humhub\modules\kickoff\specialbets\SpecialBetTypeRegistry;
use Yii;
use yii\helpers\Url;

class Module extends \humhub\components\Module
{
    public $defaultRoute = 'dashboard';

    /**
     * Base URL of the HumHub data service consumed by the `humhub-api`
     * adapter. Override in the host application's config to point at a
     * staging server or local development instance, e.g.:
     *
     *     'modules' => [
     *         'kickoff' => [
     *             'class' => \humhub\modules\kickoff\Module::class,
     *             'apiBaseUrl' => 'http://localhost:8080',
     *         ],
     *     ],
     */
    public string $apiBaseUrl = 'https://api.humhub.com';

    private ?AdapterRegistry $adapterRegistry = null;
    private ?SpecialBetTypeRegistry $specialBetTypeRegistry = null;

    public function getConfigUrl()
    {
        return Url::to(['/kickoff/admin']);
    }

    /**
     * Single source of truth for the module's permissions, ordered from the
     * widest to the narrowest capability. Holding any one of these grants
     * front-end (view) access — see {@see canView()}; the controllers' read
     * access rules reuse this list.
     */
    public const ACCESS_PERMISSIONS = [
        ManageKickoff::class,
        Participate::class,
        ViewLeaderboard::class,
    ];

    public function getPermissions($contentContainer = null)
    {
        return self::permissionInstances(self::ACCESS_PERMISSIONS);
    }

    /**
     * The capabilities form a hierarchy — Manage ⊇ Participate ⊇ View. Each
     * tier helper below is the single source of truth for that level; site
     * admins always pass (so menu/banner gating matches the controller access
     * rules, whose validator has its own admin bypass that `can()` lacks).
     */

    /** View tier: see competitions, leaderboards and other users' tips. */
    public static function canView(): bool
    {
        return Yii::$app->user->isAdmin()
            || Yii::$app->user->can(self::permissionInstances(self::ACCESS_PERMISSIONS));
    }

    /** Participate tier: place and edit own match tips and special bets. */
    public static function canParticipate(): bool
    {
        return Yii::$app->user->isAdmin()
            || Yii::$app->user->can(self::permissionInstances([Participate::class, ManageKickoff::class]));
    }

    /** Manage tier: the admin area — competitions, sync, scoring, special bets. */
    public static function canManage(): bool
    {
        return Yii::$app->user->isAdmin()
            || Yii::$app->user->can(new ManageKickoff());
    }

    /**
     * @param class-string<\humhub\libs\BasePermission>[] $classes
     * @return \humhub\libs\BasePermission[]
     */
    private static function permissionInstances(array $classes): array
    {
        return array_map(static fn(string $class) => new $class(), $classes);
    }

    public function getAdapterRegistry(): AdapterRegistry
    {
        if ($this->adapterRegistry === null) {
            $this->adapterRegistry = AdapterRegistry::createDefault();
        }
        return $this->adapterRegistry;
    }

    public function getSpecialBetTypeRegistry(): SpecialBetTypeRegistry
    {
        if ($this->specialBetTypeRegistry === null) {
            $this->specialBetTypeRegistry = SpecialBetTypeRegistry::createDefault();
        }
        return $this->specialBetTypeRegistry;
    }

    public static function instance(): self
    {
        return Yii::$app->getModule('kickoff');
    }
}
