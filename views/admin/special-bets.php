<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\Module;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var \humhub\modules\kickoff\models\SpecialBet[] $specialBets */

$registry = Module::instance()->getSpecialBetTypeRegistry();

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Special bets') ?>
        <small class="text-muted">— <?= Html::encode($competition->name) ?></small>
        <span class="float-end">
            <?= Html::beginForm(['special-bet-auto-resolve', 'competitionId' => $competition->id], 'post', ['class' => 'd-inline me-2']) ?>
                <button type="submit" class="btn btn-light btn-sm"
                        title="<?= Yii::t('KickoffModule.base', 'Tries to determine resolved values for open bets from finished games (group winners, tournament winner).') ?>">
                    ⚡ <?= Yii::t('KickoffModule.base', 'Auto-resolve') ?>
                </button>
            <?= Html::endForm() ?>
            <?= Button::primary(Yii::t('KickoffModule.base', 'New special bet'))
                ->link(Url::to(['special-bet-create', 'competitionId' => $competition->id]))
                ->cssClass('btn-sm') ?>
            <?= Button::light(Yii::t('KickoffModule.base', 'Back to competition'))
                ->link(Url::to(['view', 'id' => $competition->id]))
                ->cssClass('btn-sm') ?>
        </span>
    </div>
    <div class="panel-body">
        <?php if ($specialBets === []): ?>
            <p class="text-muted">
                <?= Yii::t('KickoffModule.base', 'No special bets yet.') ?>
            </p>
        <?php else: ?>
            <table class="table table-sm">
                <thead>
                <tr>
                    <th><?= Yii::t('KickoffModule.base', 'Type') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Question') ?></th>
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Points') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Deadline') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Resolved') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($specialBets as $bet): ?>
                    <?php $type = $registry->get($bet->type); ?>
                    <tr>
                        <td><?= Html::encode($type !== null ? $type->getLabel() : $bet->type) ?></td>
                        <td><?= Html::encode($bet->getDisplayQuestion()) ?></td>
                        <td class="text-end"><?= (int) $bet->points ?></td>
                        <td><?= Html::encode($bet->deadline_at) ?></td>
                        <td>
                            <?php if ($bet->isResolved()): ?>
                                <span class="badge bg-success">
                                    <?= Html::encode((string) $bet->resolved_value) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($type === null || $type->isManualResolveOnly()): ?>
                                <?= Html::a(
                                    Yii::t('KickoffModule.base', 'Resolve'),
                                    Url::to(['special-bet-resolve', 'id' => $bet->id]),
                                    ['class' => 'btn btn-sm btn-light'],
                                ) ?>
                            <?php endif; ?>
                            <?= Html::a(
                                Yii::t('KickoffModule.base', 'Edit'),
                                Url::to(['special-bet-update', 'id' => $bet->id]),
                                ['class' => 'btn btn-sm btn-light'],
                            ) ?>
                            <?= Html::beginForm(['special-bet-delete', 'id' => $bet->id], 'post', [
                                'class' => 'd-inline',
                                'onsubmit' => "return confirm('"
                                    . Yii::t('KickoffModule.base', 'Delete this special bet and all its tips?')
                                    . "');",
                            ]) ?>
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <?= Yii::t('KickoffModule.base', 'Delete') ?>
                                </button>
                            <?= Html::endForm() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
