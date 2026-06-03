<?php

use humhub\modules\kickoff\Module;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \humhub\modules\kickoff\models\Competition $competition */

$navButtons = function () use ($competition): void {
    ?>
    <div class="kickoff-banner-actions">
        <a href="<?= Url::to(['/kickoff/competition/view', 'slug' => $competition->slug]) ?>"
           class="btn btn-sm kickoff-banner-action">
            <?= Yii::t('KickoffModule.base', 'Competition') ?>
        </a>
        <a href="<?= Url::to(['/kickoff/competition/leaderboard', 'slug' => $competition->slug]) ?>"
           class="btn btn-sm kickoff-banner-action">
            <?= Yii::t('KickoffModule.base', 'Leaderboard') ?>
        </a>
        <a href="<?= Url::to(['/kickoff/competition/rules', 'slug' => $competition->slug]) ?>"
           class="btn btn-sm kickoff-banner-action">
            <?= Yii::t('KickoffModule.base', 'Rules') ?>
        </a>
        <?php if ($competition->hasInfoPage()): ?>
            <a href="<?= Url::to(['/kickoff/competition/info', 'slug' => $competition->slug]) ?>"
               class="btn btn-sm kickoff-banner-action">
                <?= Html::encode($competition->info_page_title) ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
};

$adminButton = function () use ($competition): void {
    if (!Module::canManage()) {
        return;
    }
    ?>
    <div class="kickoff-banner-admin">
        <a href="<?= Url::to(['/kickoff/admin/view', 'id' => $competition->id]) ?>"
           class="btn btn-sm kickoff-banner-action"
           title="<?= Yii::t('KickoffModule.base', 'Open admin view') ?>">
            <i class="fa fa-cog"></i> <?= Yii::t('KickoffModule.base', 'Admin') ?>
        </a>
    </div>
    <?php
};

if (!empty($competition->banner_image_url)) {
    ?>
    <div class="kickoff-banner kickoff-banner--image" role="img"
         aria-label="<?= Html::encode($competition->name) ?>">
        <img src="<?= Html::encode($competition->banner_image_url) ?>"
             alt="<?= Html::encode($competition->name) ?>">
        <?php $navButtons(); ?>
        <?php $adminButton(); ?>
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
    <?php $navButtons(); ?>
    <?php $adminButton(); ?>
    <div class="kickoff-banner-content">
        <span class="kickoff-banner-pretitle"><?= Html::encode($pretitle) ?></span>
        <span class="kickoff-banner-title"><?= Html::encode($competition->name) ?></span>
        <?php if (!empty($competition->season)): ?>
            <span class="kickoff-banner-season"><?= Html::encode($competition->season) ?></span>
        <?php endif; ?>
    </div>
</div>
