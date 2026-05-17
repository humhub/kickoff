<?php

use yii\helpers\Html;

/** @var \humhub\modules\kickoff\models\Competition $competition */
/** @var \humhub\modules\user\models\User $user */
/** @var \humhub\modules\kickoff\models\Tip[] $tips */
/** @var \humhub\modules\kickoff\models\SpecialBetTip[] $specialBetTips */

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
<p class="mb-3">
    <strong><?= Html::encode($user->displayName) ?></strong>
    · <?= Yii::t('KickoffModule.base', '{n} total points', ['n' => $totalPoints]) ?>
</p>

<?php if ($tips !== []): ?>
    <h6 class="mb-2"><?= Yii::t('KickoffModule.base', 'Match tips') ?></h6>
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
            $home = $game->homeTeam->name ?? '?';
            $away = $game->awayTeam->name ?? '?';
        ?>
            <tr>
                <td><?= Html::encode(substr($game->kickoff_at, 0, 16)) ?></td>
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
<?php else: ?>
    <p class="text-muted"><?= Yii::t('KickoffModule.base', 'No scored match tips yet.') ?></p>
<?php endif; ?>

<?php
$resolvedSpecialTips = array_filter($specialBetTips, fn($t) => $t->points !== null);
if ($resolvedSpecialTips !== []): ?>
    <h6 class="mt-3 mb-2"><?= Yii::t('KickoffModule.base', 'Special bet tips') ?></h6>
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
                <td><?= Html::encode($bet->question) ?></td>
                <td><?= Html::encode($tipLabel) ?></td>
                <td class="text-end"><strong><?= (int) $tip->points ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
