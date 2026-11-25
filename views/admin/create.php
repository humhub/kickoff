<?php

use humhub\modules\kickoff\models\Competition;

/** @var Competition $competition */

?>
<div class="card content-card">
    <div class="card-header"><div class="card-title"><?= Yii::t('KickoffModule.base', 'New competition') ?></div></div>
    <div class="card-body">
        <?= $this->render('_form', ['competition' => $competition]) ?>
    </div>
</div>
