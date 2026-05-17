<?php

use humhub\modules\kickoff\models\Game;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \humhub\modules\kickoff\models\Competition $competition */
/** @var Game $game */
/** @var \humhub\modules\kickoff\models\Tip[] $tips */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalCount */

$scheme = $competition->scoringScheme;
$pointsClass = function (int $points) use ($scheme): string {
    if ($scheme === null) {
        return 'points-zero';
    }
    if ($points === $scheme->points_exact) {
        return 'points-exact';
    }
    if ($points === $scheme->points_goal_diff) {
        return 'points-diff';
    }
    if ($points === $scheme->points_tendency) {
        return 'points-tendency';
    }
    return 'points-zero';
};

$home = $game->homeTeam->name ?? '?';
$away = $game->awayTeam->name ?? '?';
$isFinished = $game->isFinished() && $game->home_score !== null && $game->away_score !== null;

?>
<p class="mb-2">
    <strong><?= Html::encode($home) ?></strong>
    <?php if ($isFinished): ?>
        <span class="text-muted">
            <?= (int) $game->home_score ?>:<?= (int) $game->away_score ?>
        </span>
    <?php else: ?>
        <span class="text-muted">–</span>
    <?php endif; ?>
    <strong><?= Html::encode($away) ?></strong>
    <small class="text-muted"><?= Html::encode(substr($game->kickoff_at, 0, 16)) ?></small>
</p>

<?php if ($totalCount === 0): ?>
    <p class="text-muted"><?= Yii::t('KickoffModule.base', 'No tips placed on this match.') ?></p>
<?php else: ?>
    <table class="table table-sm">
        <thead>
        <tr>
            <th><?= Yii::t('KickoffModule.base', 'Player') ?></th>
            <th class="text-center"><?= Yii::t('KickoffModule.base', 'Tip') ?></th>
            <th class="text-end"><?= Yii::t('KickoffModule.base', 'Points') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tips as $tip):
            $user = $tip->user;
        ?>
            <tr>
                <td><?= $user ? Html::encode($user->displayName) : '<span class="text-muted">' . Yii::t('KickoffModule.base', '(deleted user)') . '</span>' ?></td>
                <td class="text-center"><?= (int) $tip->home_score ?>:<?= (int) $tip->away_score ?></td>
                <td class="text-end">
                    <?php if ($tip->points !== null): ?>
                        <span class="kickoff-points-badge <?= $pointsClass((int) $tip->points) ?>">
                            <?= (int) $tip->points ?>
                        </span>
                    <?php else: ?>
                        <span class="text-muted">–</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
        <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                <?= Yii::t('KickoffModule.base', 'Page {page} of {total} · {count} tips', [
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

                $linkFor = fn(int $p): string => Url::to([
                    '/kickoff/competition/match-tips',
                    'slug' => $competition->slug,
                    'gameId' => $game->id,
                    'page' => $p,
                ]);
                ?>

                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="#" data-kickoff-modal
                           data-modal-url="<?= $linkFor($page - 1) ?>">&laquo;</a>
                    </li>
                <?php endif; ?>

                <?php if ($start > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="#" data-kickoff-modal data-modal-url="<?= $linkFor(1) ?>">1</a>
                    </li>
                    <?php if ($start > 2): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $start; $p <= $end; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="#" data-kickoff-modal data-modal-url="<?= $linkFor($p) ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="#" data-kickoff-modal data-modal-url="<?= $linkFor($totalPages) ?>">
                            <?= $totalPages ?>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="#" data-kickoff-modal
                           data-modal-url="<?= $linkFor($page + 1) ?>">&raquo;</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>
