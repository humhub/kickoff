<?php

use humhub\modules\kickoff\Events;
use humhub\widgets\TopMenu;

return [
    'id' => 'kickoff',
    'class' => 'humhub\modules\kickoff\Module',
    'namespace' => 'humhub\modules\kickoff',
    'events' => [
        ['class' => TopMenu::class, 'event' => TopMenu::EVENT_INIT, 'callback' => [Events::class, 'onTopMenuInit']],
    ],
];
