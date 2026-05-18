<?php

use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\services\KickoffTime;
use yii\helpers\Html;

/** @var Game $game */
/** @var \humhub\modules\kickoff\models\Tip|null $tip */
/** @var bool $editable */
/** @var bool $showOtherTipsLink */
/** @var \humhub\modules\kickoff\models\Competition $competition */

$home = $game->homeTeam;
$away = $game->awayTeam;
$isFinished = $game->isFinished();
$isLive = $game->isLive();
$canTip = $editable && !$game->isKickoffPassed();
$isTipped = $tip !== null;
$showInputs = $canTip;
// Live games with no goals yet still show a 0:0 so the prominent score
// block stays visible the moment the match goes live.
$displayHomeScore = $game->home_score;
$displayAwayScore = $game->away_score;
if ($isLive) {
    $displayHomeScore = $displayHomeScore ?? 0;
    $displayAwayScore = $displayAwayScore ?? 0;
}
$hasDisplayScore = $displayHomeScore !== null && $displayAwayScore !== null;
// Live and finished games both render the score in a big, prominent block
// below the team row instead of squeezed inline between the names. The
// inline slot keeps a small separator so the team-name row stays balanced.
$showLargeScoreBlock = ($isLive || $isFinished) && $hasDisplayScore;

$kickoffEpoch = KickoffTime::parse($game->kickoff_at);
$kickoffTime = $kickoffEpoch !== null
    ? Yii::$app->formatter->asTime($kickoffEpoch, 'short')
    : '';
$relativeTime = $kickoffEpoch !== null
    ? Yii::$app->formatter->asRelativeTime($kickoffEpoch)
    : '';

$stageBadge = null;
if ($game->stage !== Game::STAGE_GROUP) {
    $stageBadge = $game->stage;
} elseif (!empty($game->group_label)) {
    $stageBadge = Yii::t('KickoffModule.base', 'Group') . ' ' . $game->group_label;
}

// Only show the probability hint while a tip is still possible — once the
// match is in progress or scored, the actual outcome is more interesting.
// The competition admin can also turn the hint off entirely.
$probabilities = null;
if ($canTip && (bool) $competition->show_probabilities) {
    $probabilities = (new \humhub\modules\kickoff\services\WinProbabilityService())->forGame($game);
}

?>
<div class="kickoff-match-card<?= $isTipped && $canTip ? ' is-tipped' : '' ?><?= $isLive ? ' is-live' : '' ?>" data-game-id="<?= (int) $game->id ?>">
    <div class="kickoff-match-card-meta">
        <span>
            <?= Html::encode($kickoffTime) ?>
            <?php if ($stageBadge !== null): ?>
                · <span class="text-muted"><?= Html::encode($stageBadge) ?></span>
            <?php endif; ?>
        </span>
        <?php if ($isLive): ?>
            <span class="kickoff-live-badge">
                <?= Yii::t('KickoffModule.base', 'LIVE') ?>
                <?php $liveMinute = $game->getFormattedLiveMinute(); ?>
                <?php if ($liveMinute !== null): ?>
                    · <?= Html::encode($liveMinute) ?>
                <?php endif; ?>
            </span>
        <?php elseif ($isFinished): ?>
            <span class="text-success"><?= Yii::t('KickoffModule.base', 'Finished') ?></span>
        <?php elseif (!$canTip): ?>
            <span class="text-muted"><?= Yii::t('KickoffModule.base', 'Awaiting result') ?></span>
        <?php else: ?>
            <span class="text-muted"><?= Html::encode($relativeTime) ?></span>
        <?php endif; ?>
    </div>
    <div class="kickoff-match-card-row">
        <div class="kickoff-match-team kickoff-match-team-home">
            <span class="kickoff-match-team-name"><?= Html::encode($home ? $home->getDisplayName() : '?') ?></span>
            <?= $this->render('_team_badge', ['team' => $home]) ?>
        </div>
        <div class="kickoff-match-score">
            <?php if ($showLargeScoreBlock): ?>
                <span class="text-muted">·</span>
            <?php elseif ($showInputs): ?>
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
                <span class="text-muted">–</span>
            <?php endif; ?>
        </div>
        <div class="kickoff-match-team kickoff-match-team-away">
            <?= $this->render('_team_badge', ['team' => $away]) ?>
            <span class="kickoff-match-team-name"><?= Html::encode($away ? $away->getDisplayName() : '?') ?></span>
        </div>
    </div>
    <?php if ($showLargeScoreBlock): ?>
        <div class="kickoff-match-card-large-score">
            <?= (int) $displayHomeScore ?>
            <span class="kickoff-match-card-large-score-sep">:</span>
            <?= (int) $displayAwayScore ?>
        </div>
    <?php endif; ?>
    <?php if ($probabilities !== null): ?>
        <div class="kickoff-match-card-probabilities" title="<?= Html::encode(Yii::t('KickoffModule.base', 'Estimated chances based on team strength — for orientation only, not betting odds.')) ?>">
            <span><?= number_format($probabilities['home'], 0) ?>%</span>
            <?php if ($probabilities['draw'] > 0): ?>
                <span class="text-muted">·</span>
                <span><?= number_format($probabilities['draw'], 0) ?>%</span>
            <?php endif; ?>
            <span class="text-muted">·</span>
            <span><?= number_format($probabilities['away'], 0) ?>%</span>
        </div>
    <?php endif; ?>
    <?php
    $hasFooterTip = !$showInputs;
    $hasFooterVenue = !empty($game->venue);
    $hasFooterActions = !empty($showOtherTipsLink);
    ?>
    <?php if ($hasFooterTip || $hasFooterVenue || $hasFooterActions): ?>
        <div class="kickoff-match-card-footer">
            <div class="kickoff-match-card-footer-tip">
                <?php if ($hasFooterTip): ?>
                    <?php if ($tip !== null): ?>
                        <?= Yii::t('KickoffModule.base', 'Your tip:') ?>
                        <strong><?= (int) $tip->home_score ?>:<?= (int) $tip->away_score ?></strong>
                        <?php if ($isFinished && $tip->points !== null): ?>
                            <?php
                            $scheme = $game->competition->scoringScheme ?? null;
                            $pointsClass = 'points-zero';
                            if ($scheme !== null) {
                                if ($tip->points === $scheme->points_exact) {
                                    $pointsClass = 'points-exact';
                                } elseif ($tip->points === $scheme->points_goal_diff) {
                                    $pointsClass = 'points-diff';
                                } elseif ($tip->points === $scheme->points_tendency) {
                                    $pointsClass = 'points-tendency';
                                }
                            }
                            ?>
                            · <span class="kickoff-points-badge <?= $pointsClass ?>">
                                <?= Yii::t('KickoffModule.base', '{n} pts', ['n' => (int) $tip->points]) ?>
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted"><?= Yii::t('KickoffModule.base', 'No tip placed.') ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="kickoff-match-card-footer-venue">
                <?php if ($hasFooterVenue): ?>
                    <i class="fa fa-home" aria-hidden="true"></i> <?= Html::encode($game->venue) ?>
                <?php endif; ?>
            </div>
            <div class="kickoff-match-card-footer-actions">
                <?php if ($hasFooterActions): ?>
                    <a href="#"
                       data-kickoff-modal
                       data-modal-url="<?= \yii\helpers\Url::to(['/kickoff/competition/match-tips', 'slug' => $competition->slug, 'gameId' => $game->id]) ?>"
                       data-modal-title="<?= Html::encode(($home ? $home->getDisplayName() : '?') . ' – ' . ($away ? $away->getDisplayName() : '?')) ?>">
                        <?= Yii::t('KickoffModule.base', 'Show all tips') ?> →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
