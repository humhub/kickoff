<?php

namespace humhub\modules\kickoff\permissions;

use humhub\libs\BasePermission;
use humhub\modules\user\models\User;
use Yii;

class ViewLeaderboard extends BasePermission
{
    protected $moduleId = 'kickoff';

    public $defaultAllowedGroups = [
        User::USERGROUP_USER,
        User::USERGROUP_SELF,
    ];

    public function getTitle()
    {
        return Yii::t('KickoffModule.base', 'View Kickoff Leaderboard');
    }

    public function getDescription()
    {
        return Yii::t('KickoffModule.base', 'Allows viewing the competition leaderboard and tips from other participants once games have kicked off.');
    }
}
