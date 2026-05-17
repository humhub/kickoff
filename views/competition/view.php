<?php

use humhub\helpers\ThemeHelper;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Tip;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition $competition */
/** @var Game[] $upcomingGames */
/** @var Game[] $finishedGames */
/** @var array<int, Tip> $tipsByGame */
/** @var \humhub\modules\kickoff\models\SpecialBet[] $openSpecialBets */
/** @var \humhub\modules\kickoff\models\SpecialBet[] $resolvedSpecialBets */
/** @var array<int, \humhub\modules\kickoff\models\SpecialBetTip> $specialBetTipsByBet */
/** @var array<int, array{rank:int, user:?\humhub\modules\user\models\User, total:int, exact:int, diff:int}> $leaderboard */
/** @var bool $isParticipating */
/** @var list<array{id:string,label:string,games:Game[],isPlaceholder:bool}> $matchdayEntries */
/** @var string $selectedMatchday */
/** @var array{id:string,label:string,games:Game[],isPlaceholder:bool}|null $selectedEntry */
/** @var bool $selectedIsPlaceholder */
/** @var array{id:string,label:string,games:Game[],isPlaceholder:bool}|null $prevEntry */
/** @var array{id:string,label:string,games:Game[],isPlaceholder:bool}|null $nextEntry */

$containerClass = ThemeHelper::isFluid() ? 'container-fluid' : 'container';

$tippedCount = 0;
foreach ($upcomingGames as $g) {
    if (isset($tipsByGame[$g->id])) {
        $tippedCount++;
    }
}

$css = <<<CSS
.kickoff-matchday-nav {
    display: flex; align-items: center; justify-content: space-between;
    gap: 8px; margin: 10px 0 4px;
}
.kickoff-matchday-nav .btn.disabled { pointer-events: none; opacity: 0.45; }
.kickoff-matchday-nav-current { flex: 1; text-align: center; }
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

?>
<div class="<?= $containerClass ?>">
<div class="panel panel-default">
    <div class="panel-heading">
        <?= Html::encode($competition->name) ?>
        <?php if ($competition->isTest()): ?>
            <span class="badge bg-warning text-dark">TEST</span>
        <?php endif; ?>
        <a href="<?= Url::to(['/kickoff/competition/leaderboard', 'slug' => $competition->slug]) ?>"
           class="btn btn-sm btn-light float-end">
            <?= Yii::t('KickoffModule.base', 'Full leaderboard') ?>
        </a>
    </div>
    <div class="panel-body">

        <h5><?= Yii::t('KickoffModule.base', 'Open tips') ?></h5>

        <?php if ($matchdayEntries === []): ?>
            <p class="text-muted">
                <?= Yii::t('KickoffModule.base', 'No upcoming games to tip on.') ?>
            </p>
        <?php else: ?>
            <?php if (count($matchdayEntries) > 1): ?>
                <div class="kickoff-matchday-nav">
                    <?php if ($prevEntry !== null): ?>
                        <a href="<?= Url::to(['view', 'slug' => $competition->slug, 'matchday' => $prevEntry['id']]) ?>"
                           class="btn btn-light btn-sm">
                            ← <?= Html::encode($prevEntry['label']) ?>
                        </a>
                    <?php else: ?>
                        <span class="btn btn-light btn-sm disabled">
                            ← <?= Yii::t('KickoffModule.base', 'Previous matchday') ?>
                        </span>
                    <?php endif; ?>

                    <div class="kickoff-matchday-nav-current">
                        <?= Html::beginForm(['view', 'slug' => $competition->slug], 'get', ['class' => 'd-inline']) ?>
                            <input type="hidden" name="slug" value="<?= Html::encode($competition->slug) ?>">
                            <select name="matchday"
                                    class="form-select form-select-sm d-inline w-auto"
                                    onchange="this.form.submit()">
                                <?php foreach ($matchdayEntries as $entry): ?>
                                    <option value="<?= Html::encode($entry['id']) ?>" <?= $entry['id'] === $selectedMatchday ? 'selected' : '' ?>>
                                        <?= Html::encode($entry['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?= Html::endForm() ?>
                    </div>

                    <?php if ($nextEntry !== null): ?>
                        <a href="<?= Url::to(['view', 'slug' => $competition->slug, 'matchday' => $nextEntry['id']]) ?>"
                           class="btn btn-light btn-sm">
                            <?= Html::encode($nextEntry['label']) ?> →
                        </a>
                    <?php else: ?>
                        <span class="btn btn-light btn-sm disabled">
                            <?= Yii::t('KickoffModule.base', 'Next matchday') ?> →
                        </span>
                    <?php endif; ?>
                </div>
            <?php elseif ($selectedEntry !== null): ?>
                <h6 class="text-center mb-2">
                    <?= Html::encode($selectedEntry['label']) ?>
                </h6>
            <?php endif; ?>

            <?php if ($selectedIsPlaceholder): ?>
                <div class="alert alert-info text-center my-3">
                    <?= Yii::t(
                        'KickoffModule.base',
                        'Pairings for this round are not decided yet — they will appear once the preceding round is finished.',
                    ) ?>
                </div>
            <?php else: ?>
                <div class="kickoff-matchday-progress">
                    <?= Yii::t('KickoffModule.base', '{tipped} of {total} tipped on this matchday', [
                        'tipped' => $tippedCount,
                        'total' => count($upcomingGames),
                    ]) ?>
                    · <?= Yii::t('KickoffModule.base', 'Tips save automatically as you type.') ?>
                </div>

                <?= Html::beginForm(['/kickoff/competition/tips', 'slug' => $competition->slug], 'post', [
                    'data-kickoff-tip-form' => '1',
                    'data-save-url' => Url::to(['/kickoff/competition/tip', 'slug' => $competition->slug]),
                ]) ?>
                    <input type="hidden" name="matchday" value="<?= Html::encode((string) $selectedMatchday) ?>">
                    <?php foreach ($upcomingGames as $g): ?>
                        <?= $this->render('_match_card', [
                            'game' => $g,
                            'tip' => $tipsByGame[$g->id] ?? null,
                            'editable' => true,
                        ]) ?>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-light btn-sm">
                        <?= Yii::t('KickoffModule.base', 'Save tips') ?>
                    </button>
                <?= Html::endForm() ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($openSpecialBets !== []): ?>
            <hr>
            <h5><?= Yii::t('KickoffModule.base', 'Special bets') ?></h5>
            <?= Html::beginForm(['/kickoff/competition/special-bet-tips', 'slug' => $competition->slug], 'post') ?>
            <?php foreach ($openSpecialBets as $bet):
                $existing = $specialBetTipsByBet[$bet->id] ?? null;
                $options = $bet->getOptions();
            ?>
                <div class="mb-3">
                    <label class="form-label">
                        <strong><?= Html::encode($bet->question) ?></strong>
                        <small class="text-muted">
                            (<?= (int) $bet->points ?> <?= Yii::t('KickoffModule.base', 'pts') ?>,
                            <?= Yii::t('KickoffModule.base', 'until') ?> <?= Html::encode($bet->deadline_at) ?>)
                        </small>
                    </label>
                    <?php if ($options !== []): ?>
                        <select name="special_bets[<?= (int) $bet->id ?>]" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($options as $value => $label): ?>
                                <option value="<?= Html::encode((string) $value) ?>"
                                    <?= $existing && $existing->value === (string) $value ? 'selected' : '' ?>>
                                    <?= Html::encode($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" name="special_bets[<?= (int) $bet->id ?>]" class="form-control"
                               value="<?= Html::encode($existing->value ?? '') ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary">
                <?= Yii::t('KickoffModule.base', 'Save special bet tips') ?>
            </button>
            <?= Html::endForm() ?>
        <?php endif; ?>

        <?php if ($resolvedSpecialBets !== []): ?>
            <hr>
            <h5><?= Yii::t('KickoffModule.base', 'Resolved special bets') ?></h5>
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
                        <td><?= Html::encode($bet->question) ?></td>
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

        <?php if ($finishedGames !== []): ?>
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
                        <td><?= Html::encode(substr($g->kickoff_at, 0, 16)) ?></td>
                        <td class="text-end">
                            <?= Html::encode($g->homeTeam->name ?? '?') ?>
                            <?= $this->render('_team_badge', ['team' => $g->homeTeam]) ?>
                        </td>
                        <td class="text-center"><strong><?= (int) $g->home_score ?>:<?= (int) $g->away_score ?></strong></td>
                        <td>
                            <?= $this->render('_team_badge', ['team' => $g->awayTeam]) ?>
                            <?= Html::encode($g->awayTeam->name ?? '?') ?>
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

        <hr>
        <h5><?= Yii::t('KickoffModule.base', 'Leaderboard') ?> <small class="text-muted">(Top 10)</small></h5>

        <?php if ($leaderboard === []): ?>
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
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Exact') ?></th>
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Diff') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($leaderboard as $row): ?>
                    <tr>
                        <td><?= (int) $row['rank'] ?></td>
                        <td>
                            <?= $row['user'] ? Html::encode($row['user']->displayName) : Yii::t('KickoffModule.base', '(deleted user)') ?>
                        </td>
                        <td class="text-end"><strong><?= (int) $row['total'] ?></strong></td>
                        <td class="text-end"><?= (int) $row['exact'] ?></td>
                        <td class="text-end"><?= (int) $row['diff'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</div>
</div>
