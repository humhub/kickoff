<?php

use humhub\commands\CronController;
use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\kickoff\Events;
use humhub\widgets\TopMenu;

return [
    'id' => 'kickoff',
    'class' => 'humhub\modules\kickoff\Module',
    'namespace' => 'humhub\modules\kickoff',
    'events' => [
        ['class' => TopMenu::class, 'event' => TopMenu::EVENT_INIT, 'callback' => [Events::class, 'onTopMenuInit']],
        ['class' => AdminMenu::class, 'event' => AdminMenu::EVENT_INIT, 'callback' => [Events::class, 'onAdminMenuInit']],
        ['class' => CronController::class, 'event' => CronController::EVENT_ON_HOURLY_RUN, 'callback' => [Events::class, 'onCronHourly']],
        ['class' => CronController::class, 'event' => CronController::EVENT_ON_DAILY_RUN, 'callback' => [Events::class, 'onCronDaily']],
        ['class' => CronController::class, 'event' => CronController::EVENT_BEFORE_ACTION, 'callback' => [Events::class, 'onCronBeforeAction']],
    ],
    // Pretty URLs for front-end competition pages — slug lives in the path
    // instead of as a `?slug=` query param. The `c/` prefix keeps the
    // namespace clean from `kickoff/admin/...` and `kickoff/dashboard`,
    // and any unmatched route (POST endpoints, sub-params like matchday or
    // page) falls back to the default `controller/action?...` behavior so
    // nothing needs to be enumerated exhaustively.
    'urlManagerRules' => [
        'kickoff/c/<slug:[a-z0-9][a-z0-9\-]*>' => 'kickoff/competition/view',
        'kickoff/c/<slug:[a-z0-9][a-z0-9\-]*>/info' => 'kickoff/competition/info',
        'kickoff/c/<slug:[a-z0-9][a-z0-9\-]*>/rules' => 'kickoff/competition/rules',
        'kickoff/c/<slug:[a-z0-9][a-z0-9\-]*>/leaderboard' => 'kickoff/competition/leaderboard',
        'kickoff/c/<slug:[a-z0-9][a-z0-9\-]*>/match/<gameId:\d+>' => 'kickoff/competition/match-tips',
        'kickoff/c/<slug:[a-z0-9][a-z0-9\-]*>/user/<userId:\d+>' => 'kickoff/competition/user-history',
    ],
];
