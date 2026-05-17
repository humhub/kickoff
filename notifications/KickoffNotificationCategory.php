<?php

namespace humhub\modules\kickoff\notifications;

use humhub\modules\notification\components\NotificationCategory;
use Yii;

class KickoffNotificationCategory extends NotificationCategory
{
    public $id = 'kickoff';

    public function getTitle()
    {
        return Yii::t('KickoffModule.base', 'Kickoff');
    }

    public function getDescription()
    {
        return Yii::t('KickoffModule.base', 'Tip deadline reminders and points-awarded digests from Kickoff competitions.');
    }
}
