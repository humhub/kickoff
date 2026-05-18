<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var \humhub\modules\kickoff\models\Competition $competition */

$actions = function () use ($competition): void {
    ?>
    <div class="kickoff-banner-actions">
        <?php if ($competition->hasInfoPage()): ?>
            <a href="<?= Url::to(['/kickoff/competition/info', 'slug' => $competition->slug]) ?>"
               class="btn btn-sm kickoff-banner-action">
                <?= Html::encode($competition->info_page_title) ?>
            </a>
        <?php endif; ?>
        <a href="<?= Url::to(['/kickoff/competition/rules', 'slug' => $competition->slug]) ?>"
           class="btn btn-sm kickoff-banner-action">
            <?= Yii::t('KickoffModule.base', 'Rules') ?>
        </a>
        <a href="<?= Url::to(['/kickoff/competition/leaderboard', 'slug' => $competition->slug]) ?>"
           class="btn btn-sm kickoff-banner-action">
            <?= Yii::t('KickoffModule.base', 'Leaderboard') ?>
        </a>
        <?php if (Yii::$app->user->isAdmin()): ?>
            <a href="<?= Url::to(['/kickoff/admin/view', 'id' => $competition->id]) ?>"
               class="btn btn-sm kickoff-banner-action"
               title="<?= Yii::t('KickoffModule.base', 'Open admin view') ?>">
                <i class="fa fa-cog"></i> <?= Yii::t('KickoffModule.base', 'Admin') ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
};

if (!empty($competition->banner_image_url)) {
    ?>
    <div class="kickoff-banner kickoff-banner--image" role="img"
         aria-label="<?= Html::encode($competition->name) ?>">
        <img src="<?= Html::encode($competition->banner_image_url) ?>"
             alt="<?= Html::encode($competition->name) ?>">
        <?php $actions(); ?>
    </div>
    <?php
    return;
}

$pretitle = $competition->isTest()
    ? Yii::t('KickoffModule.base', 'Test sandbox')
    : Yii::t('KickoffModule.base', 'Prediction game');
?>
<div class="kickoff-banner kickoff-banner--default"
     role="img" aria-label="<?= Html::encode($competition->name) ?>">
    <span class="kickoff-banner-ball" aria-hidden="true">⚽</span>
    <?php $actions(); ?>
    <div class="kickoff-banner-content">
        <span class="kickoff-banner-pretitle"><?= Html::encode($pretitle) ?></span>
        <span class="kickoff-banner-title"><?= Html::encode($competition->name) ?></span>
        <?php if (!empty($competition->season)): ?>
            <span class="kickoff-banner-season"><?= Html::encode($competition->season) ?></span>
        <?php endif; ?>
    </div>
</div>
