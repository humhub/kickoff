<?php

use humhub\modules\kickoff\models\Competition;
use yii\helpers\Url;

/** @var \humhub\modules\kickoff\notifications\TipDeadlineReminder $notification */

$competition = $notification->source instanceof Competition ? $notification->source : null;
$competitionName = $competition ? $competition->name : '';
$url = $competition
    ? Url::to(['/kickoff/competition/view', 'slug' => $competition->slug], true)
    : Url::to(['/kickoff'], true);

echo Yii::t(
    'KickoffModule.base',
    "You have pending tips in {competition} and at least one game kicks off within 24 hours.\n\nPlace your tips: {url}\n",
    ['competition' => $competitionName, 'url' => $url],
);
