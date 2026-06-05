<?php

use humhub\modules\kickoff\models\Competition;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var \humhub\modules\kickoff\models\CompetitionTeam[] $rows */

// The team badge styles (flags) live in the shared front-end bundle.
$this->registerAssetBundle(\humhub\modules\kickoff\assets\Assets::class);

$hasGroups = false;
foreach ($rows as $row) {
    if ($row->group_label !== null && $row->group_label !== '') {
        $hasGroups = true;
        break;
    }
}

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Teams') ?>
        <small class="text-muted">— <?= Html::encode($competition->name) ?> (<?= count($rows) ?>)</small>
        <span class="float-end">
            <?= Button::light(Yii::t('KickoffModule.base', 'Back to competition'))
                ->link(Url::to(['view', 'id' => $competition->id]))
                ->cssClass('btn-sm') ?>
        </span>
    </div>
    <div class="panel-body">
        <?php if ($rows === []): ?>
            <p class="text-muted">
                <?= Yii::t('KickoffModule.base', 'No teams yet. Run "Check for schedule changes" to import them.') ?>
            </p>
        <?php else: ?>
            <div class="grid-view">
            <table class="table table-sm">
                <thead>
                <tr>
                    <th style="width: 44px;"></th>
                    <th><?= Yii::t('KickoffModule.base', 'Team') ?></th>
                    <?php if ($hasGroups): ?>
                        <th><?= Yii::t('KickoffModule.base', 'Group') ?></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $team = $row->team; ?>
                    <tr>
                        <td><?= $this->render('@kickoff/views/competition/_team_badge', ['team' => $team]) ?></td>
                        <td><?= Html::encode($team ? $team->getDisplayName() : '?') ?></td>
                        <?php if ($hasGroups): ?>
                            <td><?= Html::encode((string) ($row->group_label ?? '—')) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>
