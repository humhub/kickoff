<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\ScoringScheme;
use humhub\modules\kickoff\Module;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;
use yii\helpers\Url;

/** @var Competition $competition */

$schemeOptions = [];
foreach (ScoringScheme::find()->all() as $s) {
    $schemeOptions[$s->id] = $s->name;
}

$adapterOptions = [];
foreach (Module::instance()->getAdapterRegistry()->all() as $a) {
    $adapterOptions[$a->getKey()] = $a->getLabel();
}

?>
<?php $form = ActiveForm::begin(); ?>

<?= $form->field($competition, 'name')->textInput(['maxlength' => 255]) ?>

<?= $form->field($competition, 'slug')->textInput(['maxlength' => 100])
    ->hint(Yii::t('KickoffModule.base', 'Lowercase letters, numbers, hyphens. Auto-generated from the name if left empty.')) ?>

<div class="row">
    <div class="col-md-6">
        <?= $form->field($competition, 'type')->dropDownList([
            Competition::TYPE_TOURNAMENT => Yii::t('KickoffModule.base', 'Tournament'),
            Competition::TYPE_LEAGUE => Yii::t('KickoffModule.base', 'League'),
        ]) ?>
    </div>
    <div class="col-md-6">
        <?= $form->field($competition, 'season')->textInput(['maxlength' => 32, 'placeholder' => '2026']) ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <?= $form->field($competition, 'starts_at')->input('date') ?>
    </div>
    <div class="col-md-6">
        <?= $form->field($competition, 'ends_at')->input('date') ?>
    </div>
</div>

<?= $form->field($competition, 'scoring_scheme_id')->dropDownList($schemeOptions) ?>

<?= $form->field($competition, 'ko_scoring_mode')->dropDownList([
    Competition::KO_REGULAR_TIME => Yii::t('KickoffModule.base', 'After 90 minutes (regular time)'),
    Competition::KO_FULL_TIME => Yii::t('KickoffModule.base', 'Final score incl. extra time'),
]) ?>

<?= $form->field($competition, 'data_source')->dropDownList($adapterOptions) ?>

<?= $form->field($competition, 'data_source_config')->textarea(['rows' => 3, 'placeholder' => '{"external_competition_id": "2000"}'])
    ->hint(Yii::t('KickoffModule.base', 'Optional JSON object with adapter-specific config. For football-data.org: {"external_competition_id": "..."}.')) ?>

<?= $form->field($competition, 'status')->dropDownList([
    Competition::STATUS_DRAFT => Yii::t('KickoffModule.base', 'Draft'),
    Competition::STATUS_ACTIVE => Yii::t('KickoffModule.base', 'Active'),
    Competition::STATUS_FINISHED => Yii::t('KickoffModule.base', 'Finished'),
    Competition::STATUS_ARCHIVED => Yii::t('KickoffModule.base', 'Archived'),
]) ?>

<?= $form->field($competition, 'is_test')->checkbox() ?>

<?= $form->field($competition, 'tips_visible_before_kickoff')->checkbox()
    ->hint(Yii::t('KickoffModule.base', 'Leave off to hide individual tips until each kickoff. Turn on for casual/educational competitions where participants may peek.')) ?>

<hr>
<?= Button::save()->submit() ?>
<?= Button::light(Yii::t('KickoffModule.base', 'Cancel'))
    ->link(Url::to(['index']))
    ->cssClass('float-end') ?>

<?php $form::end(); ?>
