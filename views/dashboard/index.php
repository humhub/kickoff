<?php

use humhub\modules\kickoff\models\Competition;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition[] $competitions */

?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Yii::t('KickoffModule.base', 'Kickoff') ?></div>
    <div class="panel-body">

        <?php if ($competitions === []): ?>
            <p class="text-muted">
                <?= Yii::t('KickoffModule.base', 'No active competitions right now.') ?>
            </p>
        <?php else: ?>
            <p class="text-muted">
                <?= Yii::t('KickoffModule.base', 'Pick a competition to view fixtures, place tips and see the leaderboard.') ?>
            </p>

            <div class="list-group">
                <?php foreach ($competitions as $c): ?>
                    <a class="list-group-item" href="<?= Url::to(['/kickoff/competition/view', 'slug' => $c->slug]) ?>">
                        <strong><?= Html::encode($c->name) ?></strong>
                        <?php if ($c->isTest()): ?>
                            <span class="badge bg-warning text-dark">TEST</span>
                        <?php endif; ?>
                        <span class="text-muted ms-2">
                            <?= Html::encode($c->starts_at ? substr($c->starts_at, 0, 10) : '') ?>
                            <?php if ($c->ends_at): ?>
                                – <?= Html::encode(substr($c->ends_at, 0, 10)) ?>
                            <?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
