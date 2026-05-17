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

$groupWinnerCreateHint = Yii::t(
    'KickoffModule.base',
    'For "Group winner": one bet is created per group automatically — no need to add them one by one.',
);
$groupWinnerEditHint = Yii::t(
    'KickoffModule.base',
    'Group-winner bets share their points across all groups — editing here updates every group.',
);
$pointsHint = !$bet->isNewRecord && $bet->type === SpecialBet::TYPE_GROUP_WINNER
    ? $groupWinnerEditHint
    : null;

?>
<?php $form = ActiveForm::begin(); ?>

<?= $form->field($bet, 'type')->dropDownList($typeOptions)->hint($groupWinnerCreateHint) ?>

<div class="row">
    <div class="col-md-6">
        <?php $pointsField = $form->field($bet, 'points')->input('number', ['min' => 0]); ?>
        <?= $pointsHint !== null ? $pointsField->hint($pointsHint) : $pointsField ?>
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
