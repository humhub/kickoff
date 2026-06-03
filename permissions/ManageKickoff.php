<?php

namespace humhub\modules\kickoff\permissions;

use humhub\modules\admin\components\BaseAdminPermission;
use Yii;

class ManageKickoff extends BaseAdminPermission
{
    // Admin-area permission (like the limited-user-admin module): the site
    // Administrator group always holds it, other groups are deny-by-default and
    // granted explicitly. Kept under the kickoff module so it stays listed with
    // the other Kickoff permissions.
    protected $moduleId = 'kickoff';

    public function getTitle()
    {
        return Yii::t('KickoffModule.base', 'Manage Kickoff');
    }

    public function getDescription()
    {
        return Yii::t('KickoffModule.base', 'Allows creating and managing competitions, syncing data, and recomputing points.');
    }
}
