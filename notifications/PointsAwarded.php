<?php

namespace humhub\modules\kickoff\notifications;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\notification\components\BaseNotification;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

class PointsAwarded extends BaseNotification
{
    public $moduleId = 'kickoff';
    public $viewName = 'pointsAwarded';

    public function category()
    {
        return new KickoffNotificationCategory();
    }

    public function html()
    {
        $name = $this->source instanceof Competition ? $this->source->name : '';
        return Yii::t(
            'KickoffModule.base',
            '<strong>Kickoff:</strong> Your tips in {competition} were scored — check the leaderboard.',
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
