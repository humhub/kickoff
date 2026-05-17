<?php

use humhub\modules\kickoff\models\Competition;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */

$games = $competition->getGames()
    ->with(['homeTeam', 'awayTeam'])
    ->orderBy(['kickoff_at' => SORT_ASC])
    ->all();

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Html::encode($competition->name) ?>
        <?php if ($competition->isTest()): ?>
            <span class="badge bg-warning text-dark">TEST</span>
        <?php endif; ?>
        <span class="badge bg-secondary"><?= Html::encode(ucfirst($competition->status)) ?></span>
        <span class="float-end">
            <?= Button::light(Yii::t('KickoffModule.base', 'Edit'))
                ->link(Url::to(['update', 'id' => $competition->id]))
                ->cssClass('btn-sm') ?>
            <?= Button::light(Yii::t('KickoffModule.base', 'Back to list'))
                ->link(Url::to(['index']))
                ->cssClass('btn-sm') ?>
        </span>
    </div>
    <div class="panel-body">

        <dl class="row mb-0">
            <dt class="col-sm-3"><?= Yii::t('KickoffModule.base', 'URL slug') ?></dt>
            <dd class="col-sm-9"><code><?= Html::encode($competition->slug) ?></code></dd>

            <dt class="col-sm-3"><?= Yii::t('KickoffModule.base', 'Type') ?></dt>
            <dd class="col-sm-9"><?= Html::encode(ucfirst($competition->type)) ?></dd>

            <dt class="col-sm-3"><?= Yii::t('KickoffModule.base', 'Season') ?></dt>
            <dd class="col-sm-9"><?= Html::encode($competition->season ?: '—') ?></dd>

            <dt class="col-sm-3"><?= Yii::t('KickoffModule.base', 'Period') ?></dt>
            <dd class="col-sm-9">
                <?= Html::encode($competition->starts_at ? substr($competition->starts_at, 0, 10) : '—') ?>
                –
                <?= Html::encode($competition->ends_at ? substr($competition->ends_at, 0, 10) : '—') ?>
            </dd>

            <dt class="col-sm-3"><?= Yii::t('KickoffModule.base', 'Data source') ?></dt>
            <dd class="col-sm-9"><code><?= Html::encode($competition->data_source) ?></code></dd>

            <dt class="col-sm-3"><?= Yii::t('KickoffModule.base', 'Knockout scoring') ?></dt>
            <dd class="col-sm-9"><?= Html::encode($competition->ko_scoring_mode) ?></dd>

            <dt class="col-sm-3"><?= Yii::t('KickoffModule.base', 'Last synced') ?></dt>
            <dd class="col-sm-9"><?= Html::encode($competition->last_synced_at ?: '—') ?></dd>
        </dl>

        <hr>
        <h5><?= Yii::t('KickoffModule.base', 'Actions') ?></h5>

        <?= Html::beginForm(['sync-fixtures', 'id' => $competition->id], 'post', ['class' => 'd-inline me-2']) ?>
            <button type="submit" class="btn btn-primary btn-sm">
                <?= Yii::t('KickoffModule.base', 'Sync fixtures') ?>
            </button>
        <?= Html::endForm() ?>

        <?= Html::beginForm(['sync-results', 'id' => $competition->id], 'post', ['class' => 'd-inline me-2']) ?>
            <button type="submit" class="btn btn-primary btn-sm">
                <?= Yii::t('KickoffModule.base', 'Sync results') ?>
            </button>
        <?= Html::endForm() ?>

        <?= Html::beginForm(['recompute-points', 'id' => $competition->id], 'post', ['class' => 'd-inline me-2']) ?>
            <button type="submit" class="btn btn-secondary btn-sm">
                <?= Yii::t('KickoffModule.base', 'Recompute points') ?>
            </button>
        <?= Html::endForm() ?>

        <?php if ($competition->isTest()): ?>
            <?= Html::beginForm(['delete', 'id' => $competition->id], 'post', [
                'class' => 'd-inline float-end',
                'onsubmit' => "return confirm('"
                    . Yii::t('KickoffModule.base', 'Delete this test competition and all its data?')
                    . "');",
            ]) ?>
                <button type="submit" class="btn btn-danger btn-sm">
                    <?= Yii::t('KickoffModule.base', 'Delete test competition') ?>
                </button>
            <?= Html::endForm() ?>
        <?php endif; ?>

        <hr>
        <h5>
            <?= Yii::t('KickoffModule.base', 'Games') ?>
            <small class="text-muted">(<?= count($games) ?>)</small>
        </h5>

        <?php if ($games === []): ?>
            <p class="text-muted">
                <?= Yii::t('KickoffModule.base', 'No games yet. Run "Sync fixtures" to import them.') ?>
            </p>
        <?php else: ?>
            <table class="table table-sm">
                <thead>
                <tr>
                    <th><?= Yii::t('KickoffModule.base', 'Kickoff') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Stage') ?></th>
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Home') ?></th>
                    <th class="text-center"></th>
                    <th><?= Yii::t('KickoffModule.base', 'Away') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Status') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($games as $g): ?>
                    <tr>
                        <td><?= Html::encode($g->kickoff_at) ?></td>
                        <td>
                            <?= Html::encode($g->stage) ?>
                            <?php if ($g->group_label !== null): ?>
                                <span class="text-muted"><?= Html::encode($g->group_label) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= Html::encode($g->homeTeam->name ?? '?') ?></td>
                        <td class="text-center">
                            <?php if ($g->home_score !== null && $g->away_score !== null): ?>
                                <strong><?= (int) $g->home_score ?> : <?= (int) $g->away_score ?></strong>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td><?= Html::encode($g->awayTeam->name ?? '?') ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= Html::encode($g->status) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</div>
