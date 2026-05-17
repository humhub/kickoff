<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\SpecialBet;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var SpecialBet $bet */

$options = $bet->getOptions();

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('KickoffModule.base', 'Resolve special bet') ?>
        <small class="text-muted">— <?= Html::encode($competition->name) ?></small>
    </div>
    <div class="panel-body">

        <p><strong><?= Html::encode($bet->question) ?></strong></p>
        <p class="text-muted">
            <?= Yii::t('KickoffModule.base', 'Worth {points} point(s).', ['points' => (int) $bet->points]) ?>
        </p>

        <?= Html::beginForm(['special-bet-resolve', 'id' => $bet->id], 'post') ?>

        <div class="mb-3">
            <label class="form-label"><?= Yii::t('KickoffModule.base', 'Result') ?></label>
            <?php if ($options !== []): ?>
                <select name="resolved_value" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($options as $value => $label): ?>
                        <option value="<?= Html::encode((string) $value) ?>"
                            <?= $bet->resolved_value === (string) $value ? 'selected' : '' ?>>
                            <?= Html::encode($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" name="resolved_value" class="form-control"
                       value="<?= Html::encode((string) ($bet->resolved_value ?? '')) ?>">
            <?php endif; ?>
        </div>

        <?= Button::save(Yii::t('KickoffModule.base', 'Save and score'))->submit() ?>
        <?= Button::light(Yii::t('KickoffModule.base', 'Cancel'))
            ->link(Url::to(['special-bets', 'id' => $competition->id]))
            ->cssClass('float-end') ?>

        <?= Html::endForm() ?>

    </div>
</div>
