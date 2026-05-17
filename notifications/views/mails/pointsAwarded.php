<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\notification\widgets\NotificationMailLayout;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \humhub\modules\kickoff\notifications\PointsAwarded $notification */

$competition = $notification->source instanceof Competition ? $notification->source : null;
$competitionName = $competition ? $competition->name : '';
$url = $competition
    ? Url::to(['/kickoff/competition/view', 'slug' => $competition->slug], true)
    : Url::to(['/kickoff'], true);

NotificationMailLayout::begin([
    'notification' => $notification,
    'subject' => Yii::t('KickoffModule.base', 'Your tips were scored: {competition}', [
        'competition' => $competitionName,
    ]),
]);
?>

<p>
    <?= Yii::t(
        'KickoffModule.base',
        'Results came in for <strong>{competition}</strong> and your tips have been scored.',
        ['competition' => Html::encode($competitionName)],
    ) ?>
</p>

<p>
    <?= Yii::t('KickoffModule.base', 'See your score and the leaderboard:') ?>
    <br>
    <a href="<?= Html::encode($url) ?>"><?= Html::encode($url) ?></a>
</p>

<?php NotificationMailLayout::end(); ?>
