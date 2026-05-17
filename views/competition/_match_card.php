<?php

use humhub\modules\kickoff\models\Game;
use yii\helpers\Html;

/** @var Game $game */
/** @var \humhub\modules\kickoff\models\Tip|null $tip */
/** @var bool $editable */

$home = $game->homeTeam;
$away = $game->awayTeam;
$isTipped = $tip !== null;

$kickoffTime = substr($game->kickoff_at, 11, 5);
$relativeTime = Yii::$app->formatter->asRelativeTime($game->kickoff_at);

$stageBadge = null;
if ($game->stage !== Game::STAGE_GROUP) {
    $stageBadge = $game->stage;
} elseif (!empty($game->group_label)) {
    $stageBadge = Yii::t('KickoffModule.base', 'Group') . ' ' . $game->group_label;
}

?>
<div class="kickoff-match-card<?= $isTipped ? ' is-tipped' : '' ?>" data-game-id="<?= (int) $game->id ?>">
    <div class="kickoff-match-card-meta">
        <span>
            <?= Html::encode($kickoffTime) ?>
            <?php if ($stageBadge !== null): ?>
                · <span class="text-muted"><?= Html::encode($stageBadge) ?></span>
            <?php endif; ?>
        </span>
        <span class="text-muted"><?= Html::encode($relativeTime) ?></span>
    </div>
    <div class="kickoff-match-card-row">
        <div class="kickoff-match-team kickoff-match-team-home">
            <?= $this->render('_team_badge', ['team' => $home]) ?>
            <span class="kickoff-match-team-name"><?= Html::encode($home->name ?? '?') ?></span>
        </div>
        <div class="kickoff-match-score">
            <?php if ($editable): ?>
                <input type="number" min="0" max="99"
                       class="form-control form-control-sm text-center kickoff-score-input"
                       name="tips[<?= (int) $game->id ?>][home]"
                       data-kickoff-tip-input
                       value="<?= $tip ? (int) $tip->home_score : '' ?>">
                <span class="text-muted">:</span>
                <input type="number" min="0" max="99"
                       class="form-control form-control-sm text-center kickoff-score-input"
                       name="tips[<?= (int) $game->id ?>][away]"
                       data-kickoff-tip-input
                       value="<?= $tip ? (int) $tip->away_score : '' ?>">
            <?php else: ?>
                <strong><?= $tip ? (int) $tip->home_score : '–' ?></strong>
                <span class="text-muted">:</span>
                <strong><?= $tip ? (int) $tip->away_score : '–' ?></strong>
            <?php endif; ?>
        </div>
        <div class="kickoff-match-team kickoff-match-team-away">
            <span class="kickoff-match-team-name"><?= Html::encode($away->name ?? '?') ?></span>
            <?= $this->render('_team_badge', ['team' => $away]) ?>
        </div>
    </div>
</div>
