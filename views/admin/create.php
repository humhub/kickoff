<?php

use humhub\modules\kickoff\models\Competition;

/** @var Competition $competition */

?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Yii::t('KickoffModule.base', 'New competition') ?></div>
    <div class="panel-body">
        <?= $this->render('_form', ['competition' => $competition]) ?>
    </div>
</div>
