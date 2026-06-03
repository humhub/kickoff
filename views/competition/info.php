<?php

use humhub\modules\kickoff\models\Competition;
use yii\helpers\Html;
use yii\helpers\Markdown;

/** @var Competition $competition */

$this->registerAssetBundle(\humhub\modules\kickoff\assets\Assets::class);

?>
<div class="container">
<?= $this->render('_banner', ['competition' => $competition]) ?>
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Html::encode($competition->info_page_title) ?>
    </div>
    <div class="panel-body kickoff-info-page">
        <?= Markdown::process((string) $competition->info_page_content, 'gfm') ?>
    </div>
</div>
</div>
