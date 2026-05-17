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

$containerClass = ThemeHelper::isFluid() ? 'container-fluid' : 'container';

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

        <?php if ($upcomingGames === []): ?>
            <p class="text-muted">
                <?= Yii::t('KickoffModule.base', 'No upcoming games to tip on.') ?>
            </p>
        <?php else: ?>
            <?= Html::beginForm(['/kickoff/competition/tips', 'slug' => $competition->slug], 'post') ?>
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th><?= Yii::t('KickoffModule.base', 'Kickoff') ?></th>
                    <th class="text-end"><?= Yii::t('KickoffModule.base', 'Home') ?></th>
                    <th colspan="3" class="text-center"><?= Yii::t('KickoffModule.base', 'Your tip') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Away') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($upcomingGames as $g): ?>
                    <?php $existing = $tipsByGame[$g->id] ?? null; ?>
                    <tr>
                        <td>
                            <?= Html::encode($g->kickoff_at) ?>
                            <?php if ($g->stage !== Game::STAGE_GROUP): ?>
                                <span class="badge bg-light text-dark ms-1"><?= Html::encode($g->stage) ?></span>
                            <?php elseif ($g->group_label): ?>
                                <span class="text-muted">(<?= Html::encode($g->group_label) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= Html::encode($g->homeTeam->name ?? '?') ?></td>
                        <td style="width: 70px">
                            <input type="number" min="0" max="99" class="form-control form-control-sm text-center"
                                   name="tips[<?= (int) $g->id ?>][home]"
                                   value="<?= $existing ? (int) $existing->home_score : '' ?>">
                        </td>
                        <td class="text-center text-muted">:</td>
                        <td style="width: 70px">
                            <input type="number" min="0" max="99" class="form-control form-control-sm text-center"
                                   name="tips[<?= (int) $g->id ?>][away]"
                                   value="<?= $existing ? (int) $existing->away_score : '' ?>">
                        </td>
                        <td><?= Html::encode($g->awayTeam->name ?? '?') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">
                <?= Yii::t('KickoffModule.base', 'Save tips') ?>
            </button>
            <?= Html::endForm() ?>
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
                <?php foreach ($finishedGames as $g): ?>
                    <?php $tip = $tipsByGame[$g->id] ?? null; ?>
                    <tr>
                        <td><?= Html::encode($g->kickoff_at) ?></td>
                        <td class="text-end"><?= Html::encode($g->homeTeam->name ?? '?') ?></td>
                        <td class="text-center"><strong><?= (int) $g->home_score ?>:<?= (int) $g->away_score ?></strong></td>
                        <td><?= Html::encode($g->awayTeam->name ?? '?') ?></td>
                        <td class="text-center">
                            <?php if ($tip): ?>
                                <?= (int) $tip->home_score ?>:<?= (int) $tip->away_score ?>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
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
