<?php

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Tip;
use humhub\modules\kickoff\services\KickoffTime;
use humhub\modules\user\widgets\Image as UserImage;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var Game[] $matchdayGames */
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
/** @var bool $canParticipate */
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
    if ($canParticipate && !$g->isKickoffPassed()) {
        $hasEditableGame = true;
    }
}

$this->registerAssetBundle(\humhub\modules\kickoff\assets\Assets::class);

$autosaveMessages = [
    'kickoff_passed' => Yii::t('KickoffModule.base', 'This tip could not be saved — kickoff has already started. Reload to see the current state.'),
    'deadline_passed' => Yii::t('KickoffModule.base', 'This bonus tip could not be saved — the deadline has already passed. Reload to see the current state.'),
    'generic' => Yii::t('KickoffModule.base', 'Tip could not be saved.'),
];
$autosaveMessagesJson = json_encode($autosaveMessages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$autosaveJs = <<<JS
(function (\$) {
    \$(function () {
        var \$form = \$('[data-kickoff-tip-form]');
        if (!\$form.length) return;
        var url = \$form.data('save-url');
        if (!url) return;
        var csrfParam = (typeof yii !== 'undefined') ? yii.getCsrfParam() : '_csrf';
        var csrfToken = (typeof yii !== 'undefined') ? yii.getCsrfToken() : '';
        var messages = {$autosaveMessagesJson};
        var timers = {};
        var lockedGames = {};

        \$form.on('input', '[data-kickoff-tip-input]', function () {
            var \$card = \$(this).closest('[data-game-id]');
            var gameId = \$card.data('game-id');
            if (!gameId || lockedGames[gameId]) return;
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
                        handleFailure(\$card, \$inputs, gameId, resp ? resp.error : null);
                    }
                })
                .fail(function (xhr) {
                    var code = xhr.responseJSON ? xhr.responseJSON.error : null;
                    handleFailure(\$card, \$inputs, gameId, code);
                });
        }

        function handleFailure(\$card, \$inputs, gameId, code) {
            flash(\$inputs, '#f8d7da');
            if (code === 'kickoff_passed') {
                lockedGames[gameId] = true;
                \$inputs.prop('readonly', true);
                showStatus('info', messages.kickoff_passed);
            } else {
                showStatus('error', messages.generic);
            }
        }

        function flash(\$inputs, color) {
            \$inputs.css({ 'transition': 'background-color 0.2s', 'background-color': color });
            setTimeout(function () { \$inputs.css('background-color', ''); }, 900);
        }

        function showStatus(level, text) {
            if (!text) return;
            if (window.humhub && humhub.modules && humhub.modules.ui
                && humhub.modules.ui.status && typeof humhub.modules.ui.status[level] === 'function') {
                humhub.modules.ui.status[level](text);
            }
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
        var messages = {$autosaveMessagesJson};
        var timers = {};
        var lockedBets = {};

        function saveBet(\$row, betId, debounceMs) {
            if (lockedBets[betId]) return;
            clearTimeout(timers[betId]);
            timers[betId] = setTimeout(function () {
                var \$input = \$row.find('[data-kickoff-special-bet-input]');
                var value = \$input.val();
                flash(\$input, '#fff3cd');
                var data = { bet_id: betId, value: value };
                data[csrfParam] = csrfToken;
                \$.ajax({ url: url, method: 'POST', data: data, dataType: 'json' })
                    .done(function (resp) {
                        if (resp && resp.ok) {
                            flash(\$input, '#d4edda');
                        } else {
                            handleBetFailure(\$row, \$input, betId, resp ? resp.error : null);
                        }
                    })
                    .fail(function (xhr) {
                        var code = xhr.responseJSON ? xhr.responseJSON.error : null;
                        handleBetFailure(\$row, \$input, betId, code);
                    });
            }, debounceMs);
        }

        function handleBetFailure(\$row, \$input, betId, code) {
            flash(\$input, '#f8d7da');
            if (code === 'deadline_passed') {
                lockedBets[betId] = true;
                \$input.prop('disabled', true);
                showStatus('info', messages.deadline_passed);
            } else {
                showStatus('error', messages.generic);
            }
        }

        function showStatus(level, text) {
            if (!text) return;
            if (window.humhub && humhub.modules && humhub.modules.ui
                && humhub.modules.ui.status && typeof humhub.modules.ui.status[level] === 'function') {
                humhub.modules.ui.status[level](text);
            }
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
<?= $this->render('_banner', ['competition' => $competition]) ?>
<div class="panel panel-default">
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
                    <?php if ($canParticipate && $openSpecialBets !== []): ?>
                        · <?= Yii::t('KickoffModule.base', 'Tips save automatically as you type.') ?>
                    <?php endif; ?>
                </div>
                <?php if (!$canParticipate): ?>
                    <p class="text-muted">
                        <?= Yii::t('KickoffModule.base', 'You have view-only access — placing bets is disabled.') ?>
                    </p>
                <?php elseif ($openSpecialBets !== []): ?>
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
                    <div class="grid-view">
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
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="kickoff-matchday-progress">
                    <?php if ($canParticipate): ?>
                        <?= Yii::t('KickoffModule.base', '{tipped} of {total} tipped on this matchday', [
                            'tipped' => $tippedCount,
                            'total' => count($matchdayGames),
                        ]) ?>
                        <?php if ($hasEditableGame): ?>
                            · <?= Yii::t('KickoffModule.base', 'Tips save automatically as you type.') ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?= Yii::t('KickoffModule.base', 'You have view-only access — placing tips is disabled.') ?>
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
                            'editable' => $canParticipate && !$g->isKickoffPassed(),
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
            <h4 style="margin-top: 2rem;"><?= Html::encode($lbHeading) ?></h4>

            <?php if ($lbRows === []): ?>
                <p class="text-muted">
                    <?= Yii::t('KickoffModule.base', 'No tips scored yet.') ?>
                </p>
            <?php else: ?>
                <div class="grid-view">
                <table class="table table-sm kickoff-leaderboard">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th></th>
                        <th><?= Yii::t('KickoffModule.base', 'Player') ?></th>
                        <th class="text-end"><?= Yii::t('KickoffModule.base', 'Total') ?></th>
                        <?php if (!$showBonusOnly): ?>
                            <th class="text-end" title="<?= Yii::t('KickoffModule.base', 'Number of exact-score predictions') ?>">
                                <?= Yii::t('KickoffModule.base', 'Exact') ?>
                            </th>
                            <th class="text-end" title="<?= Yii::t('KickoffModule.base', 'Number of correct goal differences') ?>">
                                <?= Yii::t('KickoffModule.base', 'Goal diff') ?>
                            </th>
                            <th class="text-end" title="<?= Yii::t('KickoffModule.base', 'Number of correct winner/draw tendencies') ?>">
                                <?= Yii::t('KickoffModule.base', 'Tendency') ?>
                            </th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lbRows as $row): ?>
                        <tr>
                            <td><?= (int) $row['rank'] ?></td>
                            <td style="width:38px">
                                <?php if ($row['user']): ?>
                                    <?= UserImage::widget(['user' => $row['user'], 'width' => 34]) ?>
                                <?php endif; ?>
                            </td>
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
                                <td class="text-end"><?= (int) ($row['tendency'] ?? 0) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
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
