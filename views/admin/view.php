<?php

use humhub\modules\kickoff\models\Competition;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var \humhub\modules\kickoff\models\Game[] $games */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalCount */

$specialBetCount = (int) $competition->getSpecialBets()->count();

$gameLinkFor = fn(int $p): string => Url::to([
    'view',
    'id' => $competition->id,
    'page' => $p,
]);

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Html::encode($competition->name) ?>
        <?php if ($competition->isTest()): ?>
            <span class="badge bg-warning text-dark">TEST</span>
        <?php endif; ?>
        <span class="badge bg-secondary"><?= Html::encode(ucfirst($competition->status)) ?></span>
        <span class="float-end">
            <?= Button::light(Yii::t('KickoffModule.base', 'View as user'))
                ->link(Url::to(['/kickoff/competition/view', 'slug' => $competition->slug]))
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

        <?= Html::a(
            Yii::t('KickoffModule.base', 'Edit'),
            Url::to(['update', 'id' => $competition->id]),
            ['class' => 'btn btn-primary btn-sm me-2'],
        ) ?>

        <?= Html::a(
            Yii::t('KickoffModule.base', 'Bonus bets') . ' (' . $specialBetCount . ')',
            Url::to(['special-bets', 'id' => $competition->id]),
            ['class' => 'btn btn-light btn-sm me-2'],
        ) ?>

        <div class="btn-group">
            <button type="button" class="btn btn-light btn-sm dropdown-toggle"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <?= Yii::t('KickoffModule.base', 'More') ?>
            </button>
            <ul class="dropdown-menu">
                <li>
                    <?= Html::beginForm(['sync-fixtures', 'id' => $competition->id], 'post', ['class' => 'm-0']) ?>
                        <button type="submit" class="dropdown-item">
                            <?= Yii::t('KickoffModule.base', 'Check for schedule changes') ?>
                        </button>
                    <?= Html::endForm() ?>
                </li>
                <li>
                    <?= Html::beginForm(['sync-results', 'id' => $competition->id], 'post', ['class' => 'm-0']) ?>
                        <button type="submit" class="dropdown-item">
                            <?= Yii::t('KickoffModule.base', 'Sync results') ?>
                        </button>
                    <?= Html::endForm() ?>
                </li>
                <li>
                    <?= Html::beginForm(['recompute-points', 'id' => $competition->id], 'post', ['class' => 'm-0']) ?>
                        <button type="submit" class="dropdown-item">
                            <?= Yii::t('KickoffModule.base', 'Recompute points') ?>
                        </button>
                    <?= Html::endForm() ?>
                </li>
                <li>
                    <?= Html::beginForm(['apply-default-ratings', 'id' => $competition->id], 'post', ['class' => 'm-0']) ?>
                        <button type="submit" class="dropdown-item"
                                title="<?= Yii::t('KickoffModule.base', 'Fills in world-ranking points and Elo ratings on each team from a bundled WM 2026 snapshot (by ISO country code). Existing values are preserved.') ?>">
                            <?= Yii::t('KickoffModule.base', 'Apply default ratings') ?>
                        </button>
                    <?= Html::endForm() ?>
                </li>
                <?php if ($competition->isTest()): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <?= Html::beginForm(['fast-forward', 'id' => $competition->id], 'post', ['class' => 'm-0']) ?>
                            <button type="submit" class="dropdown-item text-warning">
                                <?= Yii::t('KickoffModule.base', 'Fast forward 1 matchday') ?>
                            </button>
                        <?= Html::endForm() ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <hr>
        <h5>
            <?= Yii::t('KickoffModule.base', 'Games') ?>
            <small class="text-muted">(<?= (int) $totalCount ?>)</small>
        </h5>

        <?php if ($totalCount === 0): ?>
            <p class="text-muted">
                <?= Yii::t('KickoffModule.base', 'No games yet. Run "Check for schedule changes" to import them.') ?>
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
                        <td class="text-end"><?= Html::encode($g->homeTeam ? $g->homeTeam->getDisplayName() : '?') ?></td>
                        <td class="text-center">
                            <?php if ($g->home_score !== null && $g->away_score !== null): ?>
                                <strong><?= (int) $g->home_score ?> : <?= (int) $g->away_score ?></strong>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td><?= Html::encode($g->awayTeam ? $g->awayTeam->getDisplayName() : '?') ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= Html::encode($g->status) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted">
                        <?= Yii::t('KickoffModule.base', 'Page {page} of {total}', [
                            'page' => $page,
                            'total' => $totalPages,
                        ]) ?>
                    </small>
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $window = 5;
                        $start = max(1, $page - intdiv($window, 2));
                        $end = min($totalPages, $start + $window - 1);
                        $start = max(1, $end - $window + 1);
                        ?>

                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= $gameLinkFor($page - 1) ?>">&laquo;</a></li>
                        <?php endif; ?>

                        <?php if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= $gameLinkFor(1) ?>">1</a></li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($p = $start; $p <= $end; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $gameLinkFor($p) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= $gameLinkFor($totalPages) ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item"><a class="page-link" href="<?= $gameLinkFor($page + 1) ?>">&raquo;</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>
