<?php

use humhub\modules\kickoff\services\KickoffTime;
use humhub\modules\user\widgets\Image as UserImage;
use yii\helpers\Html;

/** @var \humhub\modules\kickoff\models\Competition $competition */
/** @var \humhub\modules\user\models\User $user */
/** @var \humhub\modules\kickoff\models\Tip[] $tips */
/** @var \humhub\modules\kickoff\models\SpecialBetTip[] $specialBetTips */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalTipCount */

$linkFor = fn(int $p): string => yii\helpers\Url::to([
    '/kickoff/competition/user-history',
    'slug' => $competition->slug,
    'userId' => $user->id,
    'page' => $p,
]);

$totalPoints = 0;
foreach ($tips as $t) {
    $totalPoints += (int) $t->points;
}
foreach ($specialBetTips as $t) {
    if ($t->points !== null) {
        $totalPoints += (int) $t->points;
    }
}

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

?>
<div class="d-flex align-items-center gap-2 mb-3">
    <?= UserImage::widget(['user' => $user, 'width' => 50]) ?>
    <div>
        <strong><?= Html::encode($user->displayName) ?></strong>
        · <?= Yii::t('KickoffModule.base', '{n} total points', ['n' => $totalPoints]) ?>
    </div>
</div>

<?php if ($totalTipCount > 0): ?>
    <h6 class="mb-2">
        <?= Yii::t('KickoffModule.base', 'Match tips') ?>
        <small class="text-muted">(<?= (int) $totalTipCount ?>)</small>
    </h6>
    <div class="grid-view">
    <table class="table table-sm">
        <thead>
        <tr>
            <th><?= Yii::t('KickoffModule.base', 'Kickoff') ?></th>
            <th><?= Yii::t('KickoffModule.base', 'Match') ?></th>
            <th class="text-center"><?= Yii::t('KickoffModule.base', 'Tip') ?></th>
            <th class="text-center"><?= Yii::t('KickoffModule.base', 'Result') ?></th>
            <th class="text-end"><?= Yii::t('KickoffModule.base', 'Points') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tips as $tip):
            $game = $tip->game;
            if ($game === null) {
                continue;
            }
            $home = $game->homeTeam ? $game->homeTeam->getDisplayName() : '?';
            $away = $game->awayTeam ? $game->awayTeam->getDisplayName() : '?';
        ?>
            <tr>
                <td><?php $epoch = KickoffTime::parse($game->kickoff_at); ?>
                    <?= $epoch !== null ? Yii::$app->formatter->asDatetime($epoch, 'short') : '' ?></td>
                <td><?= Html::encode($home . ' – ' . $away) ?></td>
                <td class="text-center"><?= (int) $tip->home_score ?>:<?= (int) $tip->away_score ?></td>
                <td class="text-center">
                    <?php if ($game->home_score !== null && $game->away_score !== null): ?>
                        <?= (int) $game->home_score ?>:<?= (int) $game->away_score ?>
                    <?php else: ?>
                        –
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <span class="kickoff-points-badge <?= $pointsClass((int) $tip->points) ?>">
                        <?= (int) $tip->points ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
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
                    <li class="page-item">
                        <a class="page-link" href="#" data-kickoff-modal data-modal-url="<?= $linkFor($page - 1) ?>">&laquo;</a>
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
                        <a class="page-link" href="#" data-kickoff-modal data-modal-url="<?= $linkFor($totalPages) ?>"><?= $totalPages ?></a>
                    </li>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="#" data-kickoff-modal data-modal-url="<?= $linkFor($page + 1) ?>">&raquo;</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php else: ?>
    <p class="text-muted"><?= Yii::t('KickoffModule.base', 'No scored match tips yet.') ?></p>
<?php endif; ?>

<?php
$resolvedSpecialTips = array_filter($specialBetTips, fn($t) => $t->points !== null);
if ($resolvedSpecialTips !== []): ?>
    <h6 class="mt-3 mb-2"><?= Yii::t('KickoffModule.base', 'Special bet tips') ?></h6>
    <div class="grid-view">
    <table class="table table-sm">
        <thead>
        <tr>
            <th><?= Yii::t('KickoffModule.base', 'Question') ?></th>
            <th><?= Yii::t('KickoffModule.base', 'Tip') ?></th>
            <th class="text-end"><?= Yii::t('KickoffModule.base', 'Points') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($resolvedSpecialTips as $tip):
            $bet = $tip->specialBet;
            if ($bet === null) {
                continue;
            }
            $options = $bet->getOptions();
            $tipLabel = $options !== [] && isset($options[$tip->value])
                ? $options[$tip->value]
                : $tip->value;
        ?>
            <tr>
                <td><?= Html::encode($bet->getDisplayQuestion()) ?></td>
                <td><?= Html::encode($tipLabel) ?></td>
                <td class="text-end"><strong><?= (int) $tip->points ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
