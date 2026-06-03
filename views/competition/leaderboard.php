<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\user\widgets\Image as UserImage;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var array<int, array{rank:int, user:?\humhub\modules\user\models\User, total:int, exact:int, diff:int, bonus?:int}> $rows */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalCount */
/** @var array<int, array{id:string, label:string, games:array, isPlaceholder:bool}> $matchdayOptions */
/** @var array{id:string, label:string}|null $selectedMatchday */

// Shared kickoff styles — needed here so the player-history modal (which uses
// the points badges) is styled, and on a direct/new-tab load of this page.
$this->registerAssetBundle(\humhub\modules\kickoff\assets\Assets::class);

$selectedMatchdayId = $selectedMatchday['id'] ?? '';

// Only render the matchday-bonus column when at least one participant has
// earned a bonus — keeps the table compact for early stages of a tournament
// where no matchday is complete yet. The matchday-filtered view never
// includes bonus points (special bets are their own scoreboard), so the
// column is suppressed there regardless.
$showBonusColumn = false;
if ($selectedMatchday === null) {
    foreach ($rows as $r) {
        if (!empty($r['bonus'])) {
            $showBonusColumn = true;
            break;
        }
    }
}

$linkFor = function (int $p) use ($competition, $selectedMatchdayId): string {
    $params = ['leaderboard', 'slug' => $competition->slug, 'page' => $p];
    if ($selectedMatchdayId !== '') {
        $params['matchday'] = $selectedMatchdayId;
    }
    return Url::to($params);
};

?>
<div class="container">
<?= $this->render('_banner', ['competition' => $competition]) ?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Leaderboard') ?>
    </div>
    <div class="panel-body">
        <?php if ($matchdayOptions !== []): ?>
            <form method="get"
                  action="<?= Url::to(['leaderboard', 'slug' => $competition->slug]) ?>"
                  class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                <label for="kickoff-leaderboard-matchday" class="form-label mb-0">
                    <?= Yii::t('KickoffModule.base', 'View') ?>:
                </label>
                <select name="matchday"
                        id="kickoff-leaderboard-matchday"
                        class="form-select form-select-sm w-auto"
                        onchange="this.form.submit()">
                    <option value=""<?= $selectedMatchdayId === '' ? ' selected' : '' ?>>
                        <?= Yii::t('KickoffModule.base', 'Overall') ?>
                    </option>
                    <?php foreach ($matchdayOptions as $entry): ?>
                        <option value="<?= Html::encode($entry['id']) ?>"
                                <?= $selectedMatchdayId === $entry['id'] ? 'selected' : '' ?>>
                            <?= Html::encode($entry['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <?= Yii::t('KickoffModule.base', 'Go') ?>
                    </button>
                </noscript>
            </form>
        <?php endif; ?>

        <?php if ($totalCount === 0): ?>
            <p class="text-muted">
                <?= $selectedMatchday !== null
                    ? Yii::t('KickoffModule.base', 'No tips scored yet for this matchday.')
                    : Yii::t('KickoffModule.base', 'No tips scored yet.') ?>
            </p>
        <?php else: ?>
            <div class="grid-view">
            <table class="table table-striped kickoff-leaderboard">
                <thead>
                <tr>
                    <th>#</th>
                    <th></th>
                    <th><?= Yii::t('KickoffModule.base', 'Player') ?></th>
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Total') ?></th>
                    <th class="text-end" title="<?= Yii::t('KickoffModule.base', 'Number of exact-score predictions') ?>">
                        <?= Yii::t('KickoffModule.base', 'Exact') ?>
                    </th>
                    <th class="text-end" title="<?= Yii::t('KickoffModule.base', 'Number of correct goal differences') ?>">
                        <?= Yii::t('KickoffModule.base', 'Goal diff') ?>
                    </th>
                    <th class="text-end" title="<?= Yii::t('KickoffModule.base', 'Number of correct winner/draw tendencies') ?>">
                        <?= Yii::t('KickoffModule.base', 'Tendency') ?>
                    </th>
                    <?php if ($showBonusColumn): ?>
                        <th class="text-end"
                            title="<?= Yii::t('KickoffModule.base', 'Bonus points awarded for being the matchday winner.') ?>">
                            <?= Yii::t('KickoffModule.base', 'Bonus') ?>
                        </th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= (int) $row['rank'] ?></td>
                        <td style="width:38px">
                            <?php if ($row['user']): ?>
                                <?= UserImage::widget(['user' => $row['user'], 'width' => 34]) ?>
                            <?php endif; ?>
                        </td>
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
                        <td class="text-end"><?= (int) $row['tendency'] ?></td>
                        <?php if ($showBonusColumn): ?>
                            <td class="text-end">
                                <?php if (!empty($row['bonus'])): ?>
                                    <span class="badge bg-success-subtle text-success">+<?= (int) $row['bonus'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

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
