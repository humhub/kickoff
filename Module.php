<?php

namespace humhub\modules\kickoff;

use humhub\modules\kickoff\permissions\ManageKickoff;
use humhub\modules\kickoff\permissions\Participate;
use humhub\modules\kickoff\permissions\ViewLeaderboard;
use Yii;
use yii\helpers\Url;

class Module extends \humhub\components\Module
{
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

    public static function instance(): self
    {
        return Yii::$app->getModule('kickoff');
    }
}
