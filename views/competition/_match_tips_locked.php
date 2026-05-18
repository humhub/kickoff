<?php

use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\services\KickoffTime;
use yii\helpers\Html;

/** @var Game $game */

$home = $game->homeTeam ? $game->homeTeam->getDisplayName() : '?';
$away = $game->awayTeam ? $game->awayTeam->getDisplayName() : '?';
$kickoffEpoch = KickoffTime::parse($game->kickoff_at);
$kickoffDisplay = $kickoffEpoch !== null
    ? Yii::$app->formatter->asDatetime($kickoffEpoch, 'short')
    : '';

?>
<p class="mb-2">
    <strong><?= Html::encode($home) ?></strong>
    <span class="text-muted">vs</span>
    <strong><?= Html::encode($away) ?></strong>
</p>

<div class="alert alert-info mb-0">
    <?= Yii::t('KickoffModule.base', 'Other tips are hidden until kickoff to keep things fair.') ?>
    <br>
    <small class="text-muted">
        <?= Yii::t('KickoffModule.base', 'Kickoff: {time}', [
            'time' => Html::encode($kickoffDisplay),
        ]) ?>
    </small>
</div>
