<?php

use humhub\modules\kickoff\models\Game;
use yii\helpers\Html;

/** @var Game $game */

$home = $game->homeTeam ? $game->homeTeam->getDisplayName() : '?';
$away = $game->awayTeam ? $game->awayTeam->getDisplayName() : '?';

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
            'time' => Html::encode(substr($game->kickoff_at, 0, 16)),
        ]) ?>
    </small>
</div>
