<?php

use humhub\helpers\ThemeHelper;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\ScoringScheme;
use humhub\modules\kickoff\models\SpecialBet;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var ScoringScheme|null $scheme */
/** @var SpecialBet[] $specialBets */

$containerClass = ThemeHelper::isFluid() ? 'container-fluid' : 'container';

$specialBetTypeLabel = function (string $type): string {
    return match ($type) {
        SpecialBet::TYPE_WINNER => Yii::t('KickoffModule.base', 'Tournament winner'),
        SpecialBet::TYPE_TOP_SCORER => Yii::t('KickoffModule.base', 'Top scorer'),
        SpecialBet::TYPE_GROUP_WINNER => Yii::t('KickoffModule.base', 'Group winner'),
        default => ucfirst($type),
    };
};

?>
<div class="<?= $containerClass ?>">
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Rules') ?>: <?= Html::encode($competition->name) ?>
        <a href="<?= Url::to(['view', 'slug' => $competition->slug]) ?>"
           class="btn btn-sm btn-light float-end">
            <?= Yii::t('KickoffModule.base', 'Back to competition') ?>
        </a>
    </div>
    <div class="panel-body">

        <h5><?= Yii::t('KickoffModule.base', 'Visibility of tips') ?></h5>
        <p>
            <?php if ($competition->tips_visible_before_kickoff): ?>
                <?= Yii::t('KickoffModule.base', 'Other participants\' tips are visible at any time, including before kickoff.') ?>
            <?php else: ?>
                <?= Yii::t('KickoffModule.base', 'Other participants\' tips are hidden until the kickoff of the respective match.') ?>
            <?php endif; ?>
        </p>

        <hr>
        <h5><?= Yii::t('KickoffModule.base', 'Tip mode') ?></h5>
        <p><?= Yii::t('KickoffModule.base', 'The exact final score is tipped.') ?></p>
        <p>
            <?php if ($competition->ko_scoring_mode === Competition::KO_REGULAR_TIME): ?>
                <?= Yii::t(
                    'KickoffModule.base',
                    'Tips are scored against the result after 90 minutes. Extra time and penalty shootouts are ignored.',
                ) ?>
            <?php else: ?>
                <?= Yii::t(
                    'KickoffModule.base',
                    'In knockout stages, tips are scored against the final score including extra time. Penalty shootouts are ignored.',
                ) ?>
            <?php endif; ?>
        </p>

        <hr>
        <h5><?= Yii::t('KickoffModule.base', 'Tip submission deadline') ?></h5>
        <p>
            <?= Yii::t(
                'KickoffModule.base',
                'Tips must be submitted before the kickoff of the respective match. Once kickoff has passed, the tip is locked.',
            ) ?>
        </p>
        <p class="text-muted">
            <?= Yii::t(
                'KickoffModule.base',
                'Tips save automatically as you type — no submit button needed.',
            ) ?>
        </p>

        <hr>
        <h5><?= Yii::t('KickoffModule.base', 'Scoring — match tips') ?></h5>
        <?php if ($scheme === null): ?>
            <p class="text-muted"><?= Yii::t('KickoffModule.base', 'No scoring scheme configured.') ?></p>
        <?php else: ?>
            <p>
                <?= Yii::t(
                    'KickoffModule.base',
                    'For each match, the highest applicable tier of points is awarded:',
                ) ?>
            </p>
            <table class="table table-sm" style="max-width: 480px;">
                <tbody>
                <tr>
                    <td><?= Yii::t('KickoffModule.base', 'Exact score') ?></td>
                    <td class="text-end">
                        <span class="kickoff-points-badge points-exact"><?= (int) $scheme->points_exact ?></span>
                    </td>
                </tr>
                <tr>
                    <td><?= Yii::t('KickoffModule.base', 'Correct goal difference') ?></td>
                    <td class="text-end">
                        <span class="kickoff-points-badge points-diff"><?= (int) $scheme->points_goal_diff ?></span>
                    </td>
                </tr>
                <tr>
                    <td><?= Yii::t('KickoffModule.base', 'Correct tendency (winner side)') ?></td>
                    <td class="text-end">
                        <span class="kickoff-points-badge points-tendency"><?= (int) $scheme->points_tendency ?></span>
                    </td>
                </tr>
                <tr>
                    <td><?= Yii::t('KickoffModule.base', 'Otherwise') ?></td>
                    <td class="text-end">
                        <span class="kickoff-points-badge points-zero">0</span>
                    </td>
                </tr>
                </tbody>
            </table>
            <p class="text-muted small">
                <?= Yii::t(
                    'KickoffModule.base',
                    'Tendency-only points apply when the winning side is right but the goal difference is wrong. A draw tip on a non-draw result scores 0 (and vice versa).',
                ) ?>
            </p>
        <?php endif; ?>

        <hr>
        <h5><?= Yii::t('KickoffModule.base', 'Scoring — special bets') ?></h5>
        <?php if ($specialBets === []): ?>
            <p class="text-muted"><?= Yii::t('KickoffModule.base', 'No special bets in this competition.') ?></p>
        <?php else: ?>
            <p>
                <?= Yii::t(
                    'KickoffModule.base',
                    'Each special bet is worth the configured points if the answer matches the resolved value, otherwise 0.',
                ) ?>
            </p>
            <table class="table table-sm">
                <thead>
                <tr>
                    <th><?= Yii::t('KickoffModule.base', 'Question') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Type') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Deadline') ?></th>
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Points') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($specialBets as $bet): ?>
                    <tr>
                        <td><?= Html::encode($bet->getDisplayQuestion()) ?></td>
                        <td>
                            <?= Html::encode($specialBetTypeLabel($bet->type)) ?>
                            <?php if (!empty($bet->group_label)): ?>
                                <small class="text-muted">(<?= Html::encode($bet->group_label) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td><?= Html::encode($bet->deadline_at) ?></td>
                        <td class="text-end">
                            <strong><?= (int) $bet->points ?></strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</div>
</div>
