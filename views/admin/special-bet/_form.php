<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\SpecialBet;
use humhub\modules\kickoff\Module;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var SpecialBet $bet */

$typeOptions = [];
foreach (Module::instance()->getSpecialBetTypeRegistry()->all() as $t) {
    $typeOptions[$t->getKey()] = $t->getLabel();
}

$groupWinnerHint = Yii::t(
    'KickoffModule.base',
    'For "Group winner": one bet is created per group automatically — no need to add them one by one.',
);

?>
<?php $form = ActiveForm::begin(); ?>

<?= $form->field($bet, 'type')->dropDownList($typeOptions)->hint($groupWinnerHint) ?>

<div class="row">
    <div class="col-md-6">
        <?= $form->field($bet, 'points')->input('number', ['min' => 0]) ?>
    </div>
    <div class="col-md-6">
        <?= $form->field($bet, 'deadline_at')->textInput(['placeholder' => '2026-06-11 18:00:00'])
            ->hint(Yii::t('KickoffModule.base', 'Format: YYYY-MM-DD HH:MM:SS')) ?>
    </div>
</div>

<hr>
<?= Button::save()->submit() ?>
<?= Button::light(Yii::t('KickoffModule.base', 'Cancel'))
    ->link(Url::to(['special-bets', 'id' => $competition->id]))
    ->cssClass('float-end') ?>

<?php $form::end(); ?>
