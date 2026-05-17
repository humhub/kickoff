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

    public function getPermissions($contentContainer = null)
    {
        return [
            new ManageKickoff(),
            new Participate(),
            new ViewLeaderboard(),
        ];
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
