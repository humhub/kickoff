<?php

use humhub\modules\kickoff\models\Game;
use yii\helpers\Html;

/** @var \humhub\modules\kickoff\models\Competition $competition */
/** @var Game $game */
/** @var \humhub\modules\kickoff\models\Tip[] $tips */

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

<?php if ($tips === []): ?>
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
<?php endif; ?>
