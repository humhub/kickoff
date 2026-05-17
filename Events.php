<?php

namespace humhub\modules\kickoff;

use humhub\modules\kickoff\models\Competition;
use Yii;
use yii\helpers\Url;

class Events
{
    public static function onTopMenuInit($event): void
    {
        try {
            $hasVisible = Competition::find()
                ->where(['status' => Competition::STATUS_ACTIVE])
                ->exists();
            if (!$hasVisible) {
                return;
            }
            $event->sender->addItem([
                'label' => Yii::t('KickoffModule.base', 'Kickoff'),
                'url' => Url::to(['/kickoff']),
                'icon' => '<i class="fa fa-futbol-o"></i>',
                'isActive' => Yii::$app->controller && Yii::$app->controller->module
                    && Yii::$app->controller->module->id === 'kickoff'
                    && Yii::$app->controller->id !== 'admin',
                'sortOrder' => 300,
            ]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
