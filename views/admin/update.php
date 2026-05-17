<?php

use humhub\modules\kickoff\models\Competition;
use yii\helpers\Html;

/** @var Competition $competition */

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Edit competition') ?>: <?= Html::encode($competition->name) ?>
    </div>
    <div class="panel-body">
        <?= $this->render('_form', ['competition' => $competition]) ?>
    </div>
</div>

<div class="panel panel-default mt-3" style="border-color: #dc3545;">
    <div class="panel-heading" style="background-color: #f8d7da; color: #842029;">
        <strong><?= Yii::t('KickoffModule.base', 'Danger zone') ?></strong>
    </div>
    <div class="panel-body">
        <p class="mb-3">
            <?= Yii::t(
                'KickoffModule.base',
                'Deleting this competition cascades to all associated games, teams, tips, and special bets. There is no undo. Archive non-test competitions instead unless you really need them gone.',
            ) ?>
        </p>
        <button type="button" class="btn btn-danger btn-sm"
                data-bs-toggle="modal" data-bs-target="#kickoff-delete-modal">
            <?= $competition->isTest()
                ? Yii::t('KickoffModule.base', 'Delete test competition')
                : Yii::t('KickoffModule.base', 'Delete this competition') ?>
        </button>

        <div class="modal fade" id="kickoff-delete-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">
                            <?= $competition->isTest()
                                ? Yii::t('KickoffModule.base', 'Delete test competition?')
                                : Yii::t('KickoffModule.base', 'Delete this competition?') ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            <?= Yii::t(
                                'KickoffModule.base',
                                'You are about to permanently delete <strong>{name}</strong> including:',
                                ['name' => Html::encode($competition->name)],
                            ) ?>
                        </p>
                        <ul>
                            <li><?= Yii::t('KickoffModule.base', 'all games and their results') ?></li>
                            <li><?= Yii::t('KickoffModule.base', 'all tips placed by participants') ?></li>
                            <li><?= Yii::t('KickoffModule.base', 'all special bets and their tips') ?></li>
                            <li><?= Yii::t('KickoffModule.base', 'all teams created for this competition') ?></li>
                        </ul>
                        <?php if (!$competition->isTest()): ?>
                            <p>
                                <?= Yii::t(
                                    'KickoffModule.base',
                                    'This is <strong>not</strong> a test competition. Real participants\' tips will be lost.',
                                ) ?>
                            </p>
                        <?php endif; ?>
                        <p class="text-danger mb-0">
                            <strong><?= Yii::t('KickoffModule.base', 'This action cannot be undone.') ?></strong>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <?= Yii::t('KickoffModule.base', 'Cancel') ?>
                        </button>
                        <?= Html::beginForm(['delete', 'id' => $competition->id], 'post', ['class' => 'd-inline']) ?>
                            <button type="submit" class="btn btn-danger">
                                <?= Yii::t('KickoffModule.base', 'Yes, delete permanently') ?>
                            </button>
                        <?= Html::endForm() ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
