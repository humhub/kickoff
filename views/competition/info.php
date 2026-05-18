<?php

use humhub\modules\kickoff\models\Competition;
use yii\helpers\Html;
use yii\helpers\Markdown;
use yii\helpers\Url;

/** @var Competition $competition */

?>
<div class="container">
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Html::encode($competition->info_page_title) ?>
        <small class="text-muted">— <?= Html::encode($competition->name) ?></small>
        <a href="<?= Url::to(['view', 'slug' => $competition->slug]) ?>"
           class="btn btn-sm btn-light float-end">
            <?= Yii::t('KickoffModule.base', 'Back to competition') ?>
        </a>
    </div>
    <div class="panel-body kickoff-info-page">
        <?= Markdown::process((string) $competition->info_page_content, 'gfm') ?>
    </div>
</div>
</div>
