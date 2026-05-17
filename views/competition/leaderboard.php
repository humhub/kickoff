<?php

use humhub\helpers\ThemeHelper;
use humhub\modules\kickoff\models\Competition;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var array<int, array{rank:int, user:?\humhub\modules\user\models\User, total:int, exact:int, diff:int}> $rows */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalCount */

$containerClass = ThemeHelper::isFluid() ? 'container-fluid' : 'container';

$linkFor = fn(int $p): string => Url::to([
    'leaderboard',
    'slug' => $competition->slug,
    'page' => $p,
]);

?>
<div class="<?= $containerClass ?>">
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Leaderboard') ?>: <?= Html::encode($competition->name) ?>
        <a href="<?= Url::to(['/kickoff/competition/view', 'slug' => $competition->slug]) ?>"
           class="btn btn-sm btn-light float-end">
            <?= Yii::t('KickoffModule.base', 'Back to competition') ?>
        </a>
    </div>
    <div class="panel-body">
        <?php if ($totalCount === 0): ?>
            <p class="text-muted">
                <?= Yii::t('KickoffModule.base', 'No tips scored yet.') ?>
            </p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th><?= Yii::t('KickoffModule.base', 'Player') ?></th>
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Points') ?></th>
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Exact') ?></th>
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Diff') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= (int) $row['rank'] ?></td>
                        <td>
                            <?php if ($row['user']): ?>
                                <a href="#"
                                   data-kickoff-modal
                                   data-modal-url="<?= Url::to(['/kickoff/competition/user-history', 'slug' => $competition->slug, 'userId' => $row['user']->id]) ?>"
                                   data-modal-title="<?= Html::encode($row['user']->displayName) ?>">
                                    <?= Html::encode($row['user']->displayName) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted"><?= Yii::t('KickoffModule.base', '(deleted user)') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><strong><?= (int) $row['total'] ?></strong></td>
                        <td class="text-end"><?= (int) $row['exact'] ?></td>
                        <td class="text-end"><?= (int) $row['diff'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted">
                        <?= Yii::t('KickoffModule.base', 'Page {page} of {total} · {count} players', [
                            'page' => $page,
                            'total' => $totalPages,
                            'count' => $totalCount,
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
                            <li class="page-item">
                                <a class="page-link" href="<?= $linkFor($page - 1) ?>">&laquo;</a>
                            </li>
                        <?php endif; ?>

                        <?php if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= $linkFor(1) ?>">1</a></li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($p = $start; $p <= $end; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $linkFor($p) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $linkFor($totalPages) ?>"><?= $totalPages ?></a>
                            </li>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $linkFor($page + 1) ?>">&raquo;</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</div>

<?= $this->render('_detail_modal') ?>
