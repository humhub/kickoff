<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\notification\widgets\NotificationMailLayout;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \humhub\modules\kickoff\notifications\TipDeadlineReminder $notification */

$competition = $notification->source instanceof Competition ? $notification->source : null;
$competitionName = $competition ? $competition->name : '';
$url = $competition
    ? Url::to(['/kickoff/competition/view', 'slug' => $competition->slug], true)
    : Url::to(['/kickoff'], true);

NotificationMailLayout::begin([
    'notification' => $notification,
    'subject' => Yii::t('KickoffModule.base', 'Tip deadline approaching: {competition}', [
        'competition' => $competitionName,
    ]),
]);
?>

<p>
    <?= Yii::t(
        'KickoffModule.base',
        'You have pending tips in <strong>{competition}</strong> and at least one game kicks off within the next 24 hours.',
        ['competition' => Html::encode($competitionName)],
    ) ?>
</p>

<p>
    <?= Yii::t('KickoffModule.base', 'Place your tips before kickoff:') ?>
    <br>
    <a href="<?= Html::encode($url) ?>"><?= Html::encode($url) ?></a>
</p>

<?php NotificationMailLayout::end(); ?>
