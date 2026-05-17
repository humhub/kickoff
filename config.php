<?php

use humhub\commands\CronController;
use humhub\modules\kickoff\Events;
use humhub\widgets\TopMenu;

return [
    'id' => 'kickoff',
    'class' => 'humhub\modules\kickoff\Module',
    'namespace' => 'humhub\modules\kickoff',
    'events' => [
        ['class' => TopMenu::class, 'event' => TopMenu::EVENT_INIT, 'callback' => [Events::class, 'onTopMenuInit']],
        ['class' => CronController::class, 'event' => CronController::EVENT_ON_HOURLY_RUN, 'callback' => [Events::class, 'onCronHourly']],
        ['class' => CronController::class, 'event' => CronController::EVENT_ON_DAILY_RUN, 'callback' => [Events::class, 'onCronDaily']],
        ['class' => CronController::class, 'event' => CronController::EVENT_BEFORE_ACTION, 'callback' => [Events::class, 'onCronBeforeAction']],
    ],
];
