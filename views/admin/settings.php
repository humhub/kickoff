<?php

use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var string $footballDataToken */
/** @var string $humhubApiBaseUrl */
/** @var string $humhubApiLocalFixture */

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

        <h5><?= Yii::t('KickoffModule.base', 'Data source: HumHub data service') ?></h5>
        <p class="text-muted">
            <?= Yii::t('KickoffModule.base', 'Default zero-config source backed by api.humhub.com. No API key needed. The fields below are only required to test against a custom server (staging) or a local fixture during development.') ?>
        </p>

        <div class="mb-3">
            <label class="form-label"><?= Yii::t('KickoffModule.base', 'Base URL override (optional)') ?></label>
            <input type="text" name="humhub_api_base_url" class="form-control"
                   value="<?= Html::encode($humhubApiBaseUrl) ?>"
                   placeholder="https://api.humhub.com">
            <div class="form-text">
                <?= Yii::t('KickoffModule.base', 'Leave empty for production. Used to point the adapter at a staging server.') ?>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label"><?= Yii::t('KickoffModule.base', 'Local fixture path (development only)') ?></label>
            <input type="text" name="humhub_api_local_fixture" class="form-control"
                   value="<?= Html::encode($humhubApiLocalFixture) ?>"
                   placeholder="/path/to/kickoff/data/api_sample">
            <div class="form-text">
                <?= Yii::t('KickoffModule.base', 'If set, the adapter reads JSON files from this directory instead of making HTTP requests. Useful before the server endpoint is live.') ?>
            </div>
        </div>

        <hr>

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
