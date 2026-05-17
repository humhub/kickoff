<?php

use humhub\modules\kickoff\models\Competition;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var Competition[] $competitions */
/** @var Competition|null $wm2026Competition */
/** @var bool $showTests */
/** @var int $testCount */

$serverEpoch = time();
// Mirror HumHub's formatter timezone — same source the rest of the UI uses
// to display match kickoffs, deadlines, etc. So the widget answers
// "what time does THIS user see?", not "what time does this browser think".
$userTimeZone = Yii::$app->formatter->timeZone ?: 'UTC';
?>
<div class="alert alert-light border d-flex flex-wrap align-items-center gap-3 small mb-3 py-2"
     data-kickoff-clock
     data-server-epoch="<?= $serverEpoch ?>"
     data-user-tz="<?= Html::encode($userTimeZone) ?>"
     title="<?= Yii::t('KickoffModule.base', 'Times in the module are stored as UTC. Match deadlines are checked against the server clock.') ?>">
    <span class="text-nowrap text-muted">
        ⏱️ <?= Yii::t('KickoffModule.base', 'Server time check') ?>
    </span>
    <span class="text-nowrap">
        <span class="text-muted me-1"><?= Yii::t('KickoffModule.base', 'UTC') ?></span>
        <span data-utc class="font-monospace fw-semibold"><?= Html::encode(gmdate('Y-m-d H:i:s', $serverEpoch)) ?></span>
    </span>
    <span class="text-nowrap">
        <span class="text-muted me-1"><?= Yii::t('KickoffModule.base', 'Your time') ?></span>
        <span data-local class="font-monospace fw-semibold text-muted">…</span>
        <span class="text-muted ms-1">(<?= Html::encode($userTimeZone) ?>)</span>
    </span>
</div>
<?php $this->registerJs(<<<'JS'
(function () {
    var box = document.querySelector('[data-kickoff-clock]');
    if (!box) return;
    var serverEpochMs = parseInt(box.getAttribute('data-server-epoch'), 10) * 1000;
    var pageLoadedMs = Date.now();
    var userTz = box.getAttribute('data-user-tz') || 'UTC';

    // Build one Intl formatter per zone — reused on every tick. en-CA gives
    // ISO-ish parts (YYYY-MM-DD, 24h clock) without us juggling locale quirks.
    function formatter(tz) {
        return new Intl.DateTimeFormat('en-CA', {
            timeZone: tz,
            hour12: false,
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }
    var fmtUtc = formatter('UTC');
    var fmtLocal = formatter(userTz);

    function render(fmt, date) {
        var parts = fmt.formatToParts(date).reduce(function (acc, p) {
            acc[p.type] = p.value;
            return acc;
        }, {});
        // Chrome can emit hour '24' for midnight in some locales — normalize.
        var hour = parts.hour === '24' ? '00' : parts.hour;
        return parts.year + '-' + parts.month + '-' + parts.day
            + ' ' + hour + ':' + parts.minute + ':' + parts.second;
    }

    function tick() {
        var nowMs = serverEpochMs + (Date.now() - pageLoadedMs);
        var d = new Date(nowMs);
        box.querySelector('[data-utc]').textContent = render(fmtUtc, d);
        box.querySelector('[data-local]').textContent = render(fmtLocal, d);
    }
    tick();
    setInterval(tick, 1000);
})();
JS); ?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= $showTests
            ? Yii::t('KickoffModule.base', 'Kickoff Competitions — Test sandbox')
            : Yii::t('KickoffModule.base', 'Kickoff Competitions') ?>
        <span class="float-end">
            <?= Button::light(Yii::t('KickoffModule.base', 'Settings'))
                ->link(Url::to(['settings']))
                ->cssClass('btn-sm') ?>
            <?= Button::primary(Yii::t('KickoffModule.base', 'New competition'))
                ->link(Url::to(['create']))
                ->cssClass('btn-sm') ?>
        </span>
    </div>
    <div class="panel-body">
        <?php if (!$showTests): ?>
            <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="me-3">
                    <strong>⚽ <?= Yii::t('KickoffModule.base', 'FIFA World Cup 2026') ?></strong><br>
                    <span class="text-muted small">
                        <?php if ($wm2026Competition === null): ?>
                            <?= Yii::t('KickoffModule.base', 'One-click setup: teams, fixtures, ratings and default special bets are pulled from the HumHub data service. No API key needed.') ?>
                        <?php else: ?>
                            <?= Yii::t('KickoffModule.base', 'Already set up. Re-running pulls fresh fixtures and tops up any missing ratings or default special bets.') ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?= Html::beginForm(Url::to(['setup-wm2026']), 'post', ['class' => 'm-0']) ?>
                    <?php if ($wm2026Competition === null): ?>
                        <?= Button::primary(Yii::t('KickoffModule.base', 'Set up WM 2026'))
                            ->submit()
                            ->cssClass('btn-sm') ?>
                    <?php else: ?>
                        <?= Button::light(Yii::t('KickoffModule.base', 'Re-run WM 2026 setup'))
                            ->submit()
                            ->cssClass('btn-sm') ?>
                    <?php endif; ?>
                <?= Html::endForm() ?>
            </div>
        <?php endif; ?>

        <?php if ($competitions === []): ?>
            <p class="text-muted">
                <?= $showTests
                    ? Yii::t('KickoffModule.base', 'No test competitions. Use "New competition" with the test flag to create one.')
                    : Yii::t('KickoffModule.base', 'No competitions yet. Create one to get started.') ?>
            </p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th><?= Yii::t('KickoffModule.base', 'Name') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Type') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Status') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Data source') ?></th>
                    <th><?= Yii::t('KickoffModule.base', 'Period') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($competitions as $c): ?>
                    <tr>
                        <td>
                            <?= Html::a(Html::encode($c->name), Url::to(['view', 'id' => $c->id])) ?>
                            <?php if ($c->isTest()): ?>
                                <span class="badge bg-warning text-dark">TEST</span>
                            <?php endif; ?>
                        </td>
                        <td><?= Html::encode(ucfirst($c->type)) ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= Html::encode(ucfirst($c->status)) ?></span>
                        </td>
                        <td><code><?= Html::encode($c->data_source) ?></code></td>
                        <td>
                            <?= Html::encode($c->starts_at ? substr($c->starts_at, 0, 10) : '—') ?>
                            –
                            <?= Html::encode($c->ends_at ? substr($c->ends_at, 0, 10) : '—') ?>
                        </td>
                        <td class="text-end">
                            <?= Html::a(
                                Yii::t('KickoffModule.base', 'Open'),
                                Url::to(['view', 'id' => $c->id]),
                                ['class' => 'btn btn-sm btn-light'],
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($showTests): ?>
            <div class="text-muted small mt-3">
                <?= Html::a(
                    '← ' . Yii::t('KickoffModule.base', 'Back to production competitions'),
                    Url::to(['index']),
                ) ?>
            </div>
        <?php elseif ($testCount > 0): ?>
            <div class="text-muted small mt-3">
                <?= Html::a(
                    Yii::t('KickoffModule.base', 'Show test competitions ({n})', ['n' => $testCount]),
                    Url::to(['index', 'tests' => 1]),
                ) ?>
            </div>
        <?php endif; ?>
    </div>
</div>
