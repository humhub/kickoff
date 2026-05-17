<?php

namespace humhub\modules\kickoff\permissions;

use humhub\libs\BasePermission;
use humhub\modules\user\models\User;
use Yii;

class ManageKickoff extends BasePermission
{
    protected $moduleId = 'kickoff';

    public $defaultAllowedGroups = [
        User::USERGROUP_SELF,
    ];

    public function getTitle()
    {
        return Yii::t('KickoffModule.base', 'Manage Kickoff');
    }

    public function getDescription()
    {
        return Yii::t('KickoffModule.base', 'Allows creating and managing competitions, syncing data, and recomputing points.');
    }
}
