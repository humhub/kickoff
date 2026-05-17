<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\CompetitionTeam;
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

$groups = CompetitionTeam::find()
    ->select('group_label')
    ->where(['competition_id' => $competition->id])
    ->andWhere(['IS NOT', 'group_label', null])
    ->andWhere(['<>', 'group_label', ''])
    ->distinct()
    ->column();
sort($groups);
$groupOptions = ['' => Yii::t('KickoffModule.base', '— none —')] + array_combine($groups, $groups);

?>
<?php $form = ActiveForm::begin(); ?>

<?= $form->field($bet, 'type')->dropDownList($typeOptions) ?>

<?= $form->field($bet, 'group_label')->dropDownList($groupOptions)
    ->hint(Yii::t('KickoffModule.base', 'Only used by "Group winner" bets.')) ?>

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
