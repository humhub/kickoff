<?php

namespace humhub\modules\kickoff\notifications;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\notification\components\BaseNotification;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

class TipDeadlineReminder extends BaseNotification
{
    public $moduleId = 'kickoff';
    public $viewName = 'tipDeadlineReminder';

    public function category()
    {
        return new KickoffNotificationCategory();
    }

    public function html()
    {
        $name = $this->source instanceof Competition ? $this->source->name : '';
        return Yii::t(
            'KickoffModule.base',
            '<strong>Kickoff:</strong> Don\'t forget to place your tips in {competition} — kickoff in less than 24 hours.',
            ['competition' => Html::encode($name)],
        );
    }

    public function getUrl()
    {
        if (!$this->source instanceof Competition) {
            return Url::to(['/kickoff']);
        }
        return Url::to(['/kickoff/competition/view', 'slug' => $this->source->slug]);
    }
}
