<?php

use humhub\modules\kickoff\models\Competition;
use yii\helpers\Html;
use yii\helpers\HtmlPurifier;
use yii\helpers\Markdown;

/** @var Competition $competition */

$this->registerAssetBundle(\humhub\modules\kickoff\assets\Assets::class);

?>
<div class="container">
<?= $this->render('_banner', ['competition' => $competition]) ?>
<div class="card content-card">
    <div class="card-header">
        <div class="card-title"><?= Html::encode($competition->info_page_title) ?></div>
    </div>
    <div class="card-body kickoff-info-page">
        <?= HtmlPurifier::process(Markdown::process((string) $competition->info_page_content, 'gfm')) ?>
    </div>
</div>
</div>
