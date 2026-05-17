<?php

use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var string $footballDataToken */

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Kickoff settings') ?>
        <?= Button::light(Yii::t('KickoffModule.base', 'Back to list'))
            ->link(Url::to(['index']))
            ->cssClass('btn-sm float-end') ?>
    </div>
    <div class="panel-body">

        <?= Html::beginForm() ?>

        <h5><?= Yii::t('KickoffModule.base', 'Data source: football-data.org') ?></h5>
        <p class="text-muted">
            <?= Yii::t('KickoffModule.base', 'Register a free API token at {url} and paste it here.', [
                'url' => '<a href="https://www.football-data.org/client/register" target="_blank" rel="noopener">football-data.org</a>',
            ]) ?>
        </p>

        <div class="mb-3">
            <label class="form-label"><?= Yii::t('KickoffModule.base', 'API token') ?></label>
            <input type="password" name="football_data_token" class="form-control"
                   value="<?= Html::encode($footballDataToken) ?>"
                   autocomplete="off">
        </div>

        <hr>
        <?= Button::save()->submit() ?>

        <?= Html::endForm() ?>

    </div>
</div>
