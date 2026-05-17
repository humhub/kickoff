<?php

use humhub\modules\kickoff\models\Competition;
use yii\helpers\Html;

/** @var Competition $competition */

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Edit competition') ?>: <?= Html::encode($competition->name) ?>
    </div>
    <div class="panel-body">
        <?= $this->render('_form', ['competition' => $competition]) ?>
    </div>
</div>
