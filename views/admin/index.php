<?php

use humhub\modules\kickoff\models\Competition;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition[] $competitions */
/** @var Competition|null $wm2026Competition */

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Kickoff Competitions') ?>
        <span class="float-end">
            <?= Button::light(Yii::t('KickoffModule.base', 'Settings'))
                ->link(Url::to(['settings']))
                ->cssClass('btn-sm') ?>
            <?= Button::primary(Yii::t('KickoffModule.base', 'New competition'))
                ->link(Url::to(['create']))
                ->cssClass('btn-sm') ?>
        </span>
    </div>
    <div class="panel-body">
        <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div class="me-3">
                <strong>⚽ <?= Yii::t('KickoffModule.base', 'FIFA World Cup 2026') ?></strong><br>
                <span class="text-muted small">
                    <?php if ($wm2026Competition === null): ?>
                        <?= Yii::t('KickoffModule.base', 'One-click setup: teams, fixtures, ratings and default special bets are pulled from the HumHub data service. No API key needed.') ?>
                    <?php else: ?>
                        <?= Yii::t('KickoffModule.base', 'Already set up. Re-running pulls fresh fixtures and tops up any missing ratings or default special bets.') ?>
                    <?php endif; ?>
                </span>
            </div>
            <?= Html::beginForm(Url::to(['setup-wm2026']), 'post', ['class' => 'm-0']) ?>
                <?php if ($wm2026Competition === null): ?>
                    <?= Button::primary(Yii::t('KickoffModule.base', 'Set up WM 2026'))
                        ->submit()
                        ->cssClass('btn-sm') ?>
                <?php else: ?>
                    <?= Button::light(Yii::t('KickoffModule.base', 'Re-run WM 2026 setup'))
                        ->submit()
                        ->cssClass('btn-sm') ?>
                <?php endif; ?>
            <?= Html::endForm() ?>
        </div>

        <?php if ($competitions === []): ?>
            <p class="text-muted">
                <?= Yii::t('KickoffModule.base', 'No competitions yet. Create one to get started.') ?>
            </p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th><?= Yii::t('KickoffModule.base', 'Name') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Type') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Status') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Data source') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Period') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($competitions as $c): ?>
                    <tr>
                        <td>
                            <?= Html::a(Html::encode($c->name), Url::to(['view', 'id' => $c->id])) ?>
                            <?php if ($c->isTest()): ?>
                                <span class="badge bg-warning text-dark">TEST</span>
                            <?php endif; ?>
                        </td>
                        <td><?= Html::encode(ucfirst($c->type)) ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= Html::encode(ucfirst($c->status)) ?></span>
                        </td>
                        <td><code><?= Html::encode($c->data_source) ?></code></td>
                        <td>
                            <?= Html::encode($c->starts_at ? substr($c->starts_at, 0, 10) : '—') ?>
                            –
                            <?= Html::encode($c->ends_at ? substr($c->ends_at, 0, 10) : '—') ?>
                        </td>
                        <td class="text-end">
                            <?= Html::a(
                                Yii::t('KickoffModule.base', 'Open'),
                                Url::to(['view', 'id' => $c->id]),
                                ['class' => 'btn btn-sm btn-light'],
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
