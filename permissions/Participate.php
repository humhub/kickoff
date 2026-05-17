<?php

namespace humhub\modules\kickoff\permissions;

use humhub\libs\BasePermission;
use humhub\modules\user\models\User;
use Yii;

class Participate extends BasePermission
{
    protected $moduleId = 'kickoff';

    public $defaultAllowedGroups = [
        User::USERGROUP_USER,
        User::USERGROUP_SELF,
    ];

    public function getTitle()
    {
        return Yii::t('KickoffModule.base', 'Participate in Kickoff');
    }

    public function getDescription()
    {
        return Yii::t('KickoffModule.base', 'Allows placing tips and special bets in active competitions.');
    }
}
