<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\SpecialBet;
use yii\helpers\Html;

/** @var Competition $competition */
/** @var SpecialBet $bet */

?>
<div class="card content-card">
    <div class="card-header">
        <div class="card-title">
            <?= Yii::t('KickoffModule.base', 'Edit special bet') ?>
            <small class="text-muted">— <?= Html::encode($competition->name) ?></small>
        </div>
    </div>
    <div class="card-body">
        <?= $this->render('_form', ['bet' => $bet, 'competition' => $competition]) ?>
    </div>
</div>
