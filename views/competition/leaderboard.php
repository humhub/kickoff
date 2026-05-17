<?php

use humhub\modules\kickoff\models\Competition;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var array<int, array{rank:int, user:?\humhub\modules\user\models\User, total:int, exact:int, diff:int}> $leaderboard */

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Leaderboard') ?>: <?= Html::encode($competition->name) ?>
        <a href="<?= Url::to(['/kickoff/competition/view', 'slug' => $competition->slug]) ?>"
           class="btn btn-sm btn-light float-end">
            <?= Yii::t('KickoffModule.base', 'Back to competition') ?>
        </a>
    </div>
    <div class="panel-body">
        <?php if ($leaderboard === []): ?>
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
                <?php foreach ($leaderboard as $row): ?>
                    <tr>
                        <td><?= (int) $row['rank'] ?></td>
                        <td>
                            <?= $row['user']
                                ? Html::encode($row['user']->displayName)
                                : Yii::t('KickoffModule.base', '(deleted user)') ?>
                        </td>
                        <td class="text-end"><strong><?= (int) $row['total'] ?></strong></td>
                        <td class="text-end"><?= (int) $row['exact'] ?></td>
                        <td class="text-end"><?= (int) $row['diff'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
