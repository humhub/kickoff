<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\SpecialBet;
use yii\helpers\Html;

/** @var Competition $competition */
/** @var SpecialBet $bet */

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Edit special bet') ?>
        <small class="text-muted">— <?= Html::encode($competition->name) ?></small>
    </div>
    <div class="panel-body">
        <?= $this->render('_form', ['bet' => $bet, 'competition' => $competition]) ?>
    </div>
</div>
