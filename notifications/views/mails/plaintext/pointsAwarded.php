<?php

use humhub\modules\kickoff\models\Competition;
use yii\helpers\Url;

/** @var \humhub\modules\kickoff\notifications\PointsAwarded $notification */

$competition = $notification->source instanceof Competition ? $notification->source : null;
$competitionName = $competition ? $competition->name : '';
$url = $competition
    ? Url::to(['/kickoff/competition/view', 'slug' => $competition->slug], true)
    : Url::to(['/kickoff'], true);

echo Yii::t(
    'KickoffModule.base',
    "Results came in for {competition} and your tips have been scored.\n\nSee your score: {url}\n",
    ['competition' => $competitionName, 'url' => $url],
);
