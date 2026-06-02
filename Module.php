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
     * Single source of truth for the module's permissions. Holding any one of
     * these grants front-end access (see {@see canAccess()}); the same list is
     * used by the controllers' access rules.
     */
    public const ACCESS_PERMISSIONS = [
        ManageKickoff::class,
        Participate::class,
        ViewLeaderboard::class,
    ];

    public function getPermissions($contentContainer = null)
    {
        return array_map(static fn(string $class) => new $class(), self::ACCESS_PERMISSIONS);
    }

    /**
     * Front-end access gate. A user may see Kickoff in the main menu and open
     * competition pages only if at least one of the module's permissions is
     * granted to them (managing, participating or viewing). Passing an array of
     * permissions to `can()` uses OR semantics. Site admins always pass, to
     * match the controller access rules — `can()` itself has no admin bypass.
     */
    public static function canAccess(): bool
    {
        if (Yii::$app->user->isAdmin()) {
            return true;
        }

        return Yii::$app->user->can(
            array_map(static fn(string $class) => new $class(), self::ACCESS_PERMISSIONS),
        );
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
