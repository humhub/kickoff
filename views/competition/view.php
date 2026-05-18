<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Tip;
use humhub\modules\kickoff\services\KickoffTime;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var Game[] $matchdayGames */
/** @var Game[] $finishedGames */
/** @var array<int, Tip> $tipsByGame */
/** @var \humhub\modules\kickoff\models\SpecialBet[] $openSpecialBets */
/** @var \humhub\modules\kickoff\models\SpecialBet[] $awaitingSpecialBets */
/** @var \humhub\modules\kickoff\models\SpecialBet[] $resolvedSpecialBets */
/** @var array<int, \humhub\modules\kickoff\models\SpecialBetTip> $specialBetTipsByBet */
/** @var array<int, array{rank:int, user:?\humhub\modules\user\models\User, total:int, exact:int, diff:int}> $matchdayLeaderboard */
/** @var array<int, array{rank:int, user:?\humhub\modules\user\models\User, total:int}> $bonusLeaderboard */
/** @var array<int, array{rank:int, user:?\humhub\modules\user\models\User, total:int, exact:int, diff:int}> $overallTop */
/** @var array{rank:int, user:?\humhub\modules\user\models\User, total:int, exact:int, diff:int}|null $userOverallRow */
/** @var bool $isParticipating */
/** @var list<array{id:string,label:string,games:Game[],isPlaceholder:bool}> $matchdayEntries */
/** @var string $selectedMatchday */
/** @var array{id:string,label:string,games:Game[],isPlaceholder:bool}|null $selectedEntry */
/** @var bool $selectedIsPlaceholder */
/** @var bool $selectedIsBonus */
/** @var array{id:string,label:string,games:Game[],isPlaceholder:bool}|null $prevEntry */
/** @var array{id:string,label:string,games:Game[],isPlaceholder:bool}|null $nextEntry */

$tippedCount = 0;
$hasEditableGame = false;
foreach ($matchdayGames as $g) {
    if (isset($tipsByGame[$g->id])) {
        $tippedCount++;
    }
    if (!$g->isKickoffPassed()) {
        $hasEditableGame = true;
    }
}

$css = <<<CSS
.kickoff-matchday-nav {
    display: flex; align-items: center; justify-content: space-between;
    gap: 8px; margin: 4px 0 6px;
}
.kickoff-matchday-nav .btn.disabled { pointer-events: none; opacity: 0.45; }
.kickoff-matchday-nav-current { flex: 1; text-align: center; }
.kickoff-matchday-headline {
    font-size: 1.35rem; font-weight: 600;
    background: transparent; border: 1px solid transparent;
    padding: 6px 14px; color: inherit;
}
.kickoff-matchday-headline:hover, .kickoff-matchday-headline:focus {
    background: #f1f3f5; border-color: #dee2e6; color: inherit;
}
.kickoff-matchday-nav-current .dropdown-menu {
    max-height: 60vh; overflow-y: auto;
    min-width: 280px;
}
.kickoff-matchday-progress {
    font-size: 12px; color: #888; text-align: center; margin-bottom: 14px;
}

.kickoff-match-card {
    border: 1px solid #e5e5e5; border-radius: 6px;
    padding: 10px 14px; margin-bottom: 8px;
    background: #fff;
    transition: border-color 0.2s;
}
.kickoff-match-card.is-tipped { border-left: 3px solid #28a745; }
.kickoff-match-card-meta {
    display: flex; justify-content: space-between;
    font-size: 12px; margin-bottom: 8px;
}
.kickoff-match-card-row {
    display: flex; align-items: center; gap: 12px;
}
.kickoff-match-team {
    display: flex; align-items: center; gap: 8px;
    flex: 1 1 0; min-width: 0;
}
.kickoff-match-team-home { justify-content: flex-end; text-align: right; }
.kickoff-match-team-away { justify-content: flex-start; text-align: left; }
.kickoff-match-team-name {
    font-weight: 500;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.kickoff-team-badge {
    width: 28px; height: 28px; flex-shrink: 0;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 600; color: #fff;
    overflow: hidden;
}
.kickoff-team-badge img {
    width: 100%; height: 100%; object-fit: contain;
    background: #fff;
}
.kickoff-team-badge--flag {
    background: transparent !important;
    font-size: 22px;
    line-height: 28px;
    text-align: center;
}
.kickoff-match-score {
    display: flex; align-items: center; gap: 4px;
    flex-shrink: 0;
}
.kickoff-score-input { width: 50px !important; }

.kickoff-result-row td { vertical-align: middle; }
.kickoff-result-row .kickoff-team-badge {
    width: 20px; height: 20px; font-size: 9px;
    vertical-align: middle;
}
.kickoff-result-row .points-exact { color: #155724; font-weight: 700; }
.kickoff-result-row .points-diff { color: #856404; font-weight: 600; }
.kickoff-result-row .points-tendency { color: #383d41; }
.kickoff-result-row .points-zero { color: #adb5bd; }

.kickoff-match-card-venue {
    margin-top: 6px; font-size: 12px; color: #777;
    text-align: center;
}
.kickoff-match-card-probabilities {
    margin-top: 4px; font-size: 11px; color: #6c757d;
    text-align: center; letter-spacing: 0.02em;
    cursor: help;
}
.kickoff-match-card-probabilities span { display: inline-block; padding: 0 2px; }
.kickoff-live-badge {
    color: #dc3545; font-weight: 700;
    letter-spacing: 0.04em;
}
.kickoff-live-badge::before {
    content: '●';
    display: inline-block;
    margin-right: 4px;
    animation: kickoff-live-pulse 1.2s ease-in-out infinite;
}
@keyframes kickoff-live-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
.kickoff-match-card.is-live {
    border-left: 3px solid #dc3545;
    background: #fff7f7;
}
.kickoff-match-card-live-score {
    margin: 4px 0 2px;
    text-align: center;
    font-size: 2rem; font-weight: 700;
    color: #212529; line-height: 1.1;
    letter-spacing: 0.04em;
}
.kickoff-match-card-live-score-sep {
    color: #adb5bd;
    margin: 0 4px;
}
.kickoff-match-card-actions {
    margin-top: 6px; font-size: 12px; text-align: right;
}
.kickoff-match-card-actions a { color: #6c757d; }
.kickoff-match-card-actions a:hover { color: #0d6efd; text-decoration: none; }
.kickoff-match-card-footer {
    margin-top: 6px; padding-top: 6px;
    border-top: 1px dashed #eee;
    font-size: 12px; color: #666;
}
.kickoff-points-badge {
    display: inline-block;
    padding: 1px 6px; border-radius: 8px;
    font-weight: 600;
}
.kickoff-points-badge.points-exact { background: #d4edda; color: #155724; }
.kickoff-points-badge.points-diff { background: #fff3cd; color: #856404; }
.kickoff-points-badge.points-tendency { background: #e2e3e5; color: #383d41; }
.kickoff-points-badge.points-zero { background: #f1f1f1; color: #adb5bd; }

@media (max-width: 576px) {
    .kickoff-match-team-name { font-size: 13px; }
}
CSS;
$this->registerCss($css);

$autosaveJs = <<<JS
(function (\$) {
    \$(function () {
        var \$form = \$('[data-kickoff-tip-form]');
        if (!\$form.length) return;
        var url = \$form.data('save-url');
        if (!url) return;
        var csrfParam = (typeof yii !== 'undefined') ? yii.getCsrfParam() : '_csrf';
        var csrfToken = (typeof yii !== 'undefined') ? yii.getCsrfToken() : '';
        var timers = {};

        \$form.on('input', '[data-kickoff-tip-input]', function () {
            var \$card = \$(this).closest('[data-game-id]');
            var gameId = \$card.data('game-id');
            if (!gameId) return;
            clearTimeout(timers[gameId]);
            timers[gameId] = setTimeout(function () { saveTip(\$card, gameId); }, 600);
        });

        function saveTip(\$card, gameId) {
            var \$inputs = \$card.find('[data-kickoff-tip-input]');
            var home = \$inputs.eq(0).val();
            var away = \$inputs.eq(1).val();
            if (home === '' || away === '') return;
            flash(\$inputs, '#fff3cd');
            var data = { game_id: gameId, home_score: home, away_score: away };
            data[csrfParam] = csrfToken;
            \$.ajax({ url: url, method: 'POST', data: data, dataType: 'json' })
                .done(function (resp) {
                    if (resp && resp.ok) {
                        \$card.addClass('is-tipped');
                        flash(\$inputs, '#d4edda');
                    } else {
                        flash(\$inputs, '#f8d7da');
                    }
                })
                .fail(function () { flash(\$inputs, '#f8d7da'); });
        }

        function flash(\$inputs, color) {
            \$inputs.css({ 'transition': 'background-color 0.2s', 'background-color': color });
            setTimeout(function () { \$inputs.css('background-color', ''); }, 900);
        }
    });
})(jQuery);
JS;
$this->registerJs($autosaveJs);

$specialBetAutosaveJs = <<<JS
(function (\$) {
    \$(function () {
        var \$form = \$('[data-kickoff-special-bet-form]');
        if (!\$form.length) return;
        var url = \$form.data('save-url');
        if (!url) return;
        var csrfParam = (typeof yii !== 'undefined') ? yii.getCsrfParam() : '_csrf';
        var csrfToken = (typeof yii !== 'undefined') ? yii.getCsrfToken() : '';
        var timers = {};

        function saveBet(\$row, betId, debounceMs) {
            clearTimeout(timers[betId]);
            timers[betId] = setTimeout(function () {
                var \$input = \$row.find('[data-kickoff-special-bet-input]');
                var value = \$input.val();
                flash(\$input, '#fff3cd');
                var data = { bet_id: betId, value: value };
                data[csrfParam] = csrfToken;
                \$.ajax({ url: url, method: 'POST', data: data, dataType: 'json' })
                    .done(function (resp) {
                        flash(\$input, resp && resp.ok ? '#d4edda' : '#f8d7da');
                    })
                    .fail(function () { flash(\$input, '#f8d7da'); });
            }, debounceMs);
        }

        \$form.on('change', '[data-kickoff-special-bet-input]', function () {
            // Selects fire change on commit — save immediately
            var \$row = \$(this).closest('[data-bet-id]');
            var betId = \$row.data('bet-id');
            if (!betId) return;
            saveBet(\$row, betId, this.tagName === 'SELECT' ? 0 : 600);
        });
        \$form.on('input', 'input[data-kickoff-special-bet-input]', function () {
            // Free-text inputs — debounce
            var \$row = \$(this).closest('[data-bet-id]');
            var betId = \$row.data('bet-id');
            if (!betId) return;
            saveBet(\$row, betId, 600);
        });

        function flash(\$inputs, color) {
            \$inputs.css({ 'transition': 'background-color 0.2s', 'background-color': color });
            setTimeout(function () { \$inputs.css('background-color', ''); }, 900);
        }
    });
})(jQuery);
JS;
$this->registerJs($specialBetAutosaveJs, \yii\web\View::POS_END, 'kickoff-special-bet-autosave');


?>
<div class="container">
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Html::encode($competition->name) ?>
        <?php if ($competition->isTest()): ?>
            <span class="badge bg-warning text-dark">TEST</span>
        <?php endif; ?>
        <span class="float-end">
            <?php if ($competition->hasInfoPage()): ?>
                <a href="<?= Url::to(['/kickoff/competition/info', 'slug' => $competition->slug]) ?>"
                   class="btn btn-sm btn-light">
                    <?= Html::encode($competition->info_page_title) ?>
                </a>
            <?php endif; ?>
            <a href="<?= Url::to(['/kickoff/competition/rules', 'slug' => $competition->slug]) ?>"
               class="btn btn-sm btn-light">
                <?= Yii::t('KickoffModule.base', 'Rules') ?>
            </a>
            <a href="<?= Url::to(['/kickoff/competition/leaderboard', 'slug' => $competition->slug]) ?>"
               class="btn btn-sm btn-light">
                <?= Yii::t('KickoffModule.base', 'Leaderboard') ?>
            </a>
            <?php if (Yii::$app->user->isAdmin()): ?>
                <a href="<?= Url::to(['/kickoff/admin/view', 'id' => $competition->id]) ?>"
                   class="btn btn-sm btn-light"
                   title="<?= Yii::t('KickoffModule.base', 'Open admin view') ?>">
                    <i class="fa fa-cog"></i> <?= Yii::t('KickoffModule.base', 'Admin') ?>
                </a>
            <?php endif; ?>
        </span>
    </div>
    <div class="panel-body">

        <?php if ($matchdayEntries === []): ?>
            <p class="text-muted">
                <?= Yii::t('KickoffModule.base', 'No upcoming games to tip on.') ?>
            </p>
        <?php else: ?>
            <?php if (count($matchdayEntries) > 1): ?>
                <div class="kickoff-matchday-nav">
                    <?php if ($prevEntry !== null): ?>
                        <a href="<?= Url::to(['view', 'slug' => $competition->slug, 'matchday' => $prevEntry['id']]) ?>"
                           class="btn btn-light btn-sm" title="<?= Html::encode($prevEntry['label']) ?>">
                            ←
                        </a>
                    <?php else: ?>
                        <span class="btn btn-light btn-sm disabled">←</span>
                    <?php endif; ?>

                    <div class="dropdown kickoff-matchday-nav-current">
                        <button type="button"
                                class="btn dropdown-toggle kickoff-matchday-headline"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
                            <?= Html::encode($selectedEntry['label']) ?>
                        </button>
                        <ul class="dropdown-menu">
                            <?php foreach ($matchdayEntries as $entry): ?>
                                <li>
                                    <a class="dropdown-item <?= $entry['id'] === $selectedMatchday ? 'active' : '' ?>"
                                       href="<?= Url::to(['view', 'slug' => $competition->slug, 'matchday' => $entry['id']]) ?>">
                                        <?= Html::encode($entry['label']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if ($nextEntry !== null): ?>
                        <a href="<?= Url::to(['view', 'slug' => $competition->slug, 'matchday' => $nextEntry['id']]) ?>"
                           class="btn btn-light btn-sm" title="<?= Html::encode($nextEntry['label']) ?>">
                            →
                        </a>
                    <?php else: ?>
                        <span class="btn btn-light btn-sm disabled">→</span>
                    <?php endif; ?>
                </div>
            <?php elseif ($selectedEntry !== null): ?>
                <h5 class="text-center mb-2 kickoff-matchday-headline" style="border: none;">
                    <?= Html::encode($selectedEntry['label']) ?>
                </h5>
            <?php endif; ?>

            <?php if ($selectedIsPlaceholder): ?>
                <div class="alert alert-info text-center my-3">
                    <?= Yii::t(
                        'KickoffModule.base',
                        'Pairings for this round are not decided yet — they will appear once the preceding round is finished.',
                    ) ?>
                </div>
            <?php elseif ($selectedIsBonus): ?>
                <div class="kickoff-matchday-progress">
                    <?= Yii::t('KickoffModule.base', 'Bonus bets for the whole tournament.') ?>
                    <?php if ($openSpecialBets !== []): ?>
                        · <?= Yii::t('KickoffModule.base', 'Tips save automatically as you type.') ?>
                    <?php endif; ?>
                </div>
                <?php if ($openSpecialBets !== []): ?>
                    <?= Html::beginForm(['/kickoff/competition/special-bet-tips', 'slug' => $competition->slug], 'post', [
                        'data-kickoff-special-bet-form' => '1',
                        'data-save-url' => Url::to(['/kickoff/competition/special-bet-tip', 'slug' => $competition->slug]),
                    ]) ?>
                        <input type="hidden" name="matchday" value="bonus">
                        <?php foreach ($openSpecialBets as $bet):
                            $existing = $specialBetTipsByBet[$bet->id] ?? null;
                            $options = $bet->getOptions();
                        ?>
                            <div class="mb-3" data-bet-id="<?= (int) $bet->id ?>">
                                <label class="form-label">
                                    <strong><?= Html::encode($bet->getDisplayQuestion()) ?></strong>
                                    <small class="text-muted">
                                        (<?= (int) $bet->points ?> <?= Yii::t('KickoffModule.base', 'pts') ?>,
                                        <?= Yii::t('KickoffModule.base', 'until') ?>
                                        <?php $deadlineEpoch = KickoffTime::parse($bet->deadline_at); ?>
                                        <?= $deadlineEpoch !== null
                                            ? Html::encode(Yii::$app->formatter->asDatetime($deadlineEpoch, 'short'))
                                            : Html::encode($bet->deadline_at) ?>)
                                    </small>
                                </label>
                                <?php if ($options !== []): ?>
                                    <select name="special_bets[<?= (int) $bet->id ?>]"
                                            class="form-select"
                                            data-kickoff-special-bet-input>
                                        <option value="">—</option>
                                        <?php foreach ($options as $value => $label): ?>
                                            <option value="<?= Html::encode((string) $value) ?>"
                                                <?= $existing && $existing->value === (string) $value ? 'selected' : '' ?>>
                                                <?= Html::encode($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text"
                                           name="special_bets[<?= (int) $bet->id ?>]"
                                           class="form-control"
                                           data-kickoff-special-bet-input
                                           value="<?= Html::encode($existing->value ?? '') ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <noscript>
                            <button type="submit" class="btn btn-primary">
                                <?= Yii::t('KickoffModule.base', 'Save special bet tips') ?>
                            </button>
                        </noscript>
                    <?= Html::endForm() ?>
                <?php else: ?>
                    <p class="text-muted"><?= Yii::t('KickoffModule.base', 'No open special bets right now.') ?></p>
                <?php endif; ?>

                <?php
                $awaitingWithMyTip = array_values(array_filter(
                    $awaitingSpecialBets,
                    fn($b) => isset($specialBetTipsByBet[$b->id]),
                ));
                ?>
                <?php if ($awaitingWithMyTip !== []): ?>
                    <hr>
                    <h6><?= Yii::t('KickoffModule.base', 'Awaiting resolution') ?></h6>
                    <ul class="list-unstyled">
                        <?php foreach ($awaitingWithMyTip as $bet):
                            $tip = $specialBetTipsByBet[$bet->id];
                            $options = $bet->getOptions();
                            $tipLabel = $options !== [] && isset($options[$tip->value])
                                ? $options[$tip->value]
                                : $tip->value;
                        ?>
                            <li class="mb-2">
                                <strong><?= Html::encode($bet->getDisplayQuestion()) ?></strong>
                                <small class="text-muted">
                                    (<?= (int) $bet->points ?> <?= Yii::t('KickoffModule.base', 'pts') ?>)
                                </small>
                                <br>
                                <?= Yii::t('KickoffModule.base', 'Your tip:') ?>
                                <strong><?= Html::encode($tipLabel) ?></strong>
                                <span class="text-muted">·
                                    <?= Yii::t('KickoffModule.base', 'Awaiting admin resolution.') ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($resolvedSpecialBets !== []): ?>
                    <hr>
                    <h6><?= Yii::t('KickoffModule.base', 'Resolved special bets') ?></h6>
                    <table class="table table-sm">
                        <thead>
                        <tr>
                            <th><?= Yii::t('KickoffModule.base', 'Question') ?></th>
                            <th><?= Yii::t('KickoffModule.base', 'Result') ?></th>
                            <th><?= Yii::t('KickoffModule.base', 'Your tip') ?></th>
                            <th class="text-end"><?= Yii::t('KickoffModule.base', 'Points') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($resolvedSpecialBets as $bet):
                            $tip = $specialBetTipsByBet[$bet->id] ?? null;
                            $options = $bet->getOptions();
                            $resultLabel = $options !== [] && isset($options[(string) $bet->resolved_value])
                                ? $options[(string) $bet->resolved_value]
                                : (string) $bet->resolved_value;
                            $tipLabel = $tip && $options !== [] && isset($options[$tip->value])
                                ? $options[$tip->value]
                                : ($tip ? $tip->value : null);
                        ?>
                            <tr>
                                <td><?= Html::encode($bet->getDisplayQuestion()) ?></td>
                                <td><?= Html::encode($resultLabel) ?></td>
                                <td>
                                    <?= $tipLabel !== null ? Html::encode($tipLabel) : '<span class="text-muted">–</span>' ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($tip && $tip->points !== null): ?>
                                        <strong><?= (int) $tip->points ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">–</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php else: ?>
                <div class="kickoff-matchday-progress">
                    <?= Yii::t('KickoffModule.base', '{tipped} of {total} tipped on this matchday', [
                        'tipped' => $tippedCount,
                        'total' => count($matchdayGames),
                    ]) ?>
                    <?php if ($hasEditableGame): ?>
                        · <?= Yii::t('KickoffModule.base', 'Tips save automatically as you type.') ?>
                    <?php endif; ?>
                </div>

                <?= Html::beginForm(['/kickoff/competition/tips', 'slug' => $competition->slug], 'post', [
                    'data-kickoff-tip-form' => '1',
                    'data-save-url' => Url::to(['/kickoff/competition/tip', 'slug' => $competition->slug]),
                ]) ?>
                    <input type="hidden" name="matchday" value="<?= Html::encode((string) $selectedMatchday) ?>">
                    <?php foreach ($matchdayGames as $g): ?>
                        <?= $this->render('_match_card', [
                            'game' => $g,
                            'tip' => $tipsByGame[$g->id] ?? null,
                            'editable' => !$g->isKickoffPassed(),
                            'showOtherTipsLink' => $competition->tipsVisibleForGame($g),
                            'competition' => $competition,
                        ]) ?>
                    <?php endforeach; ?>
                    <noscript>
                        <?php if ($hasEditableGame): ?>
                            <button type="submit" class="btn btn-primary">
                                <?= Yii::t('KickoffModule.base', 'Save tips') ?>
                            </button>
                        <?php endif; ?>
                    </noscript>
                <?= Html::endForm() ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$selectedIsBonus && !$selectedIsPlaceholder && $matchdayLeaderboard !== [] && $finishedGames !== []): ?>
            <hr>
            <h5><?= Yii::t('KickoffModule.base', 'Recent results') ?></h5>
            <table class="table table-sm">
                <thead>
                <tr>
                    <th><?= Yii::t('KickoffModule.base', 'Kickoff') ?></th>
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Home') ?></th>
                    <th class="text-center"><?= Yii::t('KickoffModule.base', 'Result') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Away') ?></th>
                    <th class="text-center"><?= Yii::t('KickoffModule.base', 'Your tip') ?></th>
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Points') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($finishedGames as $g):
                    $tip = $tipsByGame[$g->id] ?? null;
                    $pointsClass = 'points-zero';
                    if ($tip && $tip->points !== null) {
                        $scheme = $competition->scoringScheme;
                        if ($scheme !== null && $tip->points === $scheme->points_exact) {
                            $pointsClass = 'points-exact';
                        } elseif ($scheme !== null && $tip->points === $scheme->points_goal_diff) {
                            $pointsClass = 'points-diff';
                        } elseif ($scheme !== null && $tip->points === $scheme->points_tendency) {
                            $pointsClass = 'points-tendency';
                        }
                    }
                ?>
                    <tr class="kickoff-result-row">
                        <td>
                            <?php $finishedKickoffEpoch = KickoffTime::parse($g->kickoff_at); ?>
                            <?= $finishedKickoffEpoch !== null
                                ? Html::encode(Yii::$app->formatter->asDatetime($finishedKickoffEpoch, 'short'))
                                : '' ?>
                        </td>
                        <td class="text-end">
                            <?= Html::encode($g->homeTeam ? $g->homeTeam->getDisplayName() : '?') ?>
                            <?= $this->render('_team_badge', ['team' => $g->homeTeam]) ?>
                        </td>
                        <td class="text-center"><strong><?= (int) $g->home_score ?>:<?= (int) $g->away_score ?></strong></td>
                        <td>
                            <?= $this->render('_team_badge', ['team' => $g->awayTeam]) ?>
                            <?= Html::encode($g->awayTeam ? $g->awayTeam->getDisplayName() : '?') ?>
                        </td>
                        <td class="text-center">
                            <?php if ($tip): ?>
                                <?= (int) $tip->home_score ?>:<?= (int) $tip->away_score ?>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($tip && $tip->points !== null): ?>
                                <span class="<?= $pointsClass ?>"><?= (int) $tip->points ?></span>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php
        if ($selectedIsBonus) {
            $lbRows = $bonusLeaderboard;
            $lbHeading = Yii::t('KickoffModule.base', 'Top 10 — bonus only');
            $showBonusOnly = true;
            $skipLeaderboard = $resolvedSpecialBets === [];
        } else {
            // Hide leaderboard entirely on future / not-yet-scored matchdays.
            // Once at least one tip on this matchday has been scored, the
            // matchday-specific top 10 takes over.
            $lbRows = $matchdayLeaderboard;
            $lbHeading = Yii::t('KickoffModule.base', 'Top 10 — this matchday');
            $showBonusOnly = false;
            $skipLeaderboard = $matchdayLeaderboard === [];
        }
        ?>
        <?php if (!$skipLeaderboard): ?>
            <hr>
            <h5><?= Html::encode($lbHeading) ?></h5>

            <?php if ($lbRows === []): ?>
                <p class="text-muted">
                    <?= Yii::t('KickoffModule.base', 'No tips scored yet.') ?>
                </p>
            <?php else: ?>
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th><?= Yii::t('KickoffModule.base', 'Player') ?></th>
                        <th class="text-end"><?= Yii::t('KickoffModule.base', 'Points') ?></th>
                        <?php if (!$showBonusOnly): ?>
                            <th class="text-end"><?= Yii::t('KickoffModule.base', 'Exact') ?></th>
                            <th class="text-end"><?= Yii::t('KickoffModule.base', 'Diff') ?></th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lbRows as $row): ?>
                        <tr>
                            <td><?= (int) $row['rank'] ?></td>
                            <td>
                                <?php if ($row['user']): ?>
                                    <a href="#"
                                       data-kickoff-modal
                                       data-modal-url="<?= Url::to(['/kickoff/competition/user-history', 'slug' => $competition->slug, 'userId' => $row['user']->id]) ?>"
                                       data-modal-title="<?= Html::encode($row['user']->displayName) ?>">
                                        <?= Html::encode($row['user']->displayName) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted"><?= Yii::t('KickoffModule.base', '(deleted user)') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><strong><?= (int) $row['total'] ?></strong></td>
                            <?php if (!$showBonusOnly): ?>
                                <td class="text-end"><?= (int) ($row['exact'] ?? 0) ?></td>
                                <td class="text-end"><?= (int) ($row['diff'] ?? 0) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($userOverallRow !== null): ?>
            <p class="text-muted text-center mb-0">
                <?= Yii::t('KickoffModule.base', 'Your overall rank: #{rank} ({points} points)', [
                    'rank' => $userOverallRow['rank'],
                    'points' => $userOverallRow['total'],
                ]) ?>
            </p>
        <?php elseif (!Yii::$app->user->isGuest): ?>
            <p class="text-muted text-center mb-0">
                <?= Yii::t('KickoffModule.base', 'You are not ranked overall yet — points appear once your tipped games finish.') ?>
            </p>
        <?php endif; ?>

    </div>
</div>

<?= $this->render('_detail_modal') ?>
</div>
