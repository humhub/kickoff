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

$section = function (string $id, string $title, bool $open, callable $body): void {
    $collapseClass = $open ? 'accordion-collapse collapse show' : 'accordion-collapse collapse';
    $btnClass = $open ? 'accordion-button' : 'accordion-button collapsed';
    ?>
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="<?= $btnClass ?>" type="button"
                    data-bs-toggle="collapse" data-bs-target="#<?= $id ?>"
                    aria-expanded="<?= $open ? 'true' : 'false' ?>">
                <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
            </button>
        </h2>
        <div id="<?= $id ?>" class="<?= $collapseClass ?>">
            <div class="accordion-body">
                <?php $body(); ?>
            </div>
        </div>
    </div>
    <?php
};

?>
<?php $form = ActiveForm::begin(); ?>

<div class="accordion mb-3" id="kickoff-form-accordion">

    <?php $section('kickoff-form-basics', Yii::t('KickoffModule.base', 'Basics'), true, function () use ($form, $competition): void { ?>
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
    <?php }); ?>

    <?php $section('kickoff-form-scoring', Yii::t('KickoffModule.base', 'Scoring & rules'), false, function () use ($form, $competition, $schemeOptions): void { ?>
        <?= $form->field($competition, 'scoring_scheme_id')->dropDownList($schemeOptions) ?>
        <?= $form->field($competition, 'ko_scoring_mode')->dropDownList([
            Competition::KO_REGULAR_TIME => Yii::t('KickoffModule.base', 'After 90 minutes (regular time)'),
            Competition::KO_FULL_TIME => Yii::t('KickoffModule.base', 'Final score incl. extra time'),
        ]) ?>
        <?= $form->field($competition, 'tips_visible_before_kickoff')->checkbox()
            ->hint(Yii::t('KickoffModule.base', 'Leave off to hide individual tips until each kickoff. Turn on for casual/educational competitions where participants may peek.')) ?>
    <?php }); ?>

    <?php $section('kickoff-form-visibility', Yii::t('KickoffModule.base', 'Status & visibility'), false, function () use ($form, $competition): void { ?>
        <?= $form->field($competition, 'status')->dropDownList([
            Competition::STATUS_DRAFT => Yii::t('KickoffModule.base', 'Draft'),
            Competition::STATUS_ACTIVE => Yii::t('KickoffModule.base', 'Active'),
            Competition::STATUS_FINISHED => Yii::t('KickoffModule.base', 'Finished'),
            Competition::STATUS_ARCHIVED => Yii::t('KickoffModule.base', 'Archived'),
        ]) ?>
        <?= $form->field($competition, 'is_test')->checkbox() ?>
        <?= $form->field($competition, 'show_in_main_menu')->checkbox()
            ->hint(Yii::t('KickoffModule.base', 'Adds its own entry to HumHub\'s main top menu pointing directly at this competition. When at least one competition is flagged, the generic "Kickoff" entry is replaced by these specific entries.')) ?>
        <?= $form->field($competition, 'menu_title')->textInput(['maxlength' => 255])
            ->hint(Yii::t('KickoffModule.base', 'Optional override for the menu label. Defaults to the competition name.')) ?>
    <?php }); ?>

    <?php $section('kickoff-form-source', Yii::t('KickoffModule.base', 'Data source'), false, function () use ($form, $competition, $adapterOptions): void { ?>
        <?= $form->field($competition, 'data_source')->dropDownList($adapterOptions) ?>
        <?= $form->field($competition, 'data_source_config')->textarea(['rows' => 3, 'placeholder' => '{"external_competition_id": "2000"}'])
            ->hint(Yii::t('KickoffModule.base', 'Optional JSON object with adapter-specific config. For football-data.org: {"external_competition_id": "..."}.')) ?>
    <?php }); ?>

    <?php $section('kickoff-form-info', Yii::t('KickoffModule.base', 'Info page'), false, function () use ($form, $competition): void { ?>
        <p class="text-muted small">
            <?= Yii::t('KickoffModule.base', 'Optional custom page shown via a header link on the competition view. Use it for prizes, sponsors, house rules, anything. Markdown is supported.') ?>
        </p>
        <?= $form->field($competition, 'info_page_title')->textInput(['maxlength' => 255, 'placeholder' => Yii::t('KickoffModule.base', 'e.g. Prizes')]) ?>
        <?= $form->field($competition, 'info_page_content')->textarea(['rows' => 10, 'placeholder' => "# Prizes\n\n- 1st: …\n- 2nd: …"]) ?>
    <?php }); ?>

</div>

<?= Button::save()->submit() ?>
<?= Button::light(Yii::t('KickoffModule.base', 'Cancel'))
    ->link(Url::to(['index']))
    ->cssClass('float-end') ?>

<?php $form::end(); ?>
