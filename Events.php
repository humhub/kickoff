<?php

namespace humhub\modules\kickoff;

use humhub\modules\kickoff\adapters\ManualAdapter;
use humhub\modules\kickoff\assets\Assets;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\services\KickoffTime;
use humhub\modules\kickoff\services\MatchdayBonusService;
use humhub\modules\kickoff\services\NotificationDispatcher;
use humhub\modules\kickoff\services\ScoringService;
use humhub\modules\kickoff\services\SpecialBetResolver;
use humhub\modules\ui\menu\MenuLink;
use Yii;
use yii\helpers\Console;
use yii\helpers\Url;

class Events
{
    /**
     * Loads the Kickoff stylesheet (~8 KB, cached) on every full page load,
     * so Pjax navigation never swaps in Kickoff content before its styles
     * are available (brief unstyled flash, most visible in Firefox).
     */
    public static function onLayoutAddonsBeforeRun($event): void
    {
        $event->sender->view->registerAssetBundle(Assets::class);
    }

    public static function onTopMenuInit($event): void
    {
        try {
            // The front-end requires login, so guests get no competition entries
            // — even for public competitions, which are otherwise open to all
            // logged-in members. Without this, a public competition would surface
            // in the menu for guests (on guest-access installs) only to bounce
            // them to the login page on click.
            if (Yii::$app->user->isGuest) {
                return;
            }

            $pinned = Competition::find()
                ->where(['status' => Competition::STATUS_ACTIVE, 'show_in_main_menu' => 1])
                ->orderBy(['name' => SORT_ASC])
                ->all();

            $isOnKickoff = Yii::$app->controller
                && Yii::$app->controller->module
                && Yii::$app->controller->module->id === 'kickoff'
                && Yii::$app->controller->id !== 'admin';
            $currentSlug = Yii::$app->request->get('slug');

            if ($pinned !== []) {
                $offset = 0;
                foreach ($pinned as $competition) {
                    // Public competitions appear for everyone; restricted ones
                    // only for members who are allowed to view them.
                    if (!$competition->canView()) {
                        continue;
                    }
                    $event->sender->addEntry(new MenuLink([
                        'id' => 'kickoff-' . $competition->slug,
                        'label' => $competition->getMenuLabel(),
                        'url' => Url::to(['/kickoff/competition/view', 'slug' => $competition->slug]),
                        'icon' => 'futbol-o',
                        'isActive' => $isOnKickoff && $currentSlug === $competition->slug,
                        'sortOrder' => 300 + $offset,
                    ]));
                    $offset++;
                }
                return;
            }

            // No competition is flagged for the main menu — leave it empty.
            // The module is still reachable via the admin area and direct URLs.
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /**
     * Adds a "Kickoff" entry to HumHub's admin sidebar for users who can manage
     * the module. This is also what surfaces the "Administration" entry in the
     * profile dropdown for a non-site-admin ManageKickoff holder:
     * AdminMenu::canAccess() returns true once the admin menu has a visible
     * entry. (canAccess() is cached per session, so a freshly granted user may
     * need to re-login before the entry appears.)
     */
    public static function onAdminMenuInit($event): void
    {
        $event->sender->addEntry(new MenuLink([
            'id' => 'kickoff',
            'label' => Yii::t('KickoffModule.base', 'Kickoff'),
            'url' => ['/kickoff/admin'],
            'icon' => 'futbol-o',
            'sortOrder' => 500,
            'isActive' => Yii::$app->controller
                && Yii::$app->controller->module
                && Yii::$app->controller->module->id === 'kickoff'
                && Yii::$app->controller->id === 'admin',
            'isVisible' => Module::canManage(),
        ]));
    }

    /**
     * Remembers the module version the last full fixtures sync ran for. After
     * an update the installed version (read from module.json) differs, so the
     * next hourly cron performs a one-off full fixtures sync — re-stamping data
     * fixed by a code change (e.g. a corrected stage mapping) onto existing
     * games automatically, without an admin having to trigger a manual sync.
     *
     * Deliberately driven from the cron, NOT a migration: a migration runs
     * inside the update request, where the module's freshly shipped classes
     * may not be reloaded yet (referencing new constants/methods there throws).
     * The cron runs in a separate process that always has the new code loaded.
     */
    public const SETTING_LAST_RESYNC_VERSION = 'last_fixtures_resync_version';

    public static function onCronHourly($event): void
    {
        $module = Module::instance();
        $settings = $module->settings;
        $pendingResync = (string) $settings->get(self::SETTING_LAST_RESYNC_VERSION) !== (string) $module->getVersion();

        self::runSyncForActiveCompetitions($event, syncFixtures: $pendingResync);

        if ($pendingResync) {
            // Record the synced version so the full sync runs once per update,
            // not every hour. Per-competition failures are logged inside
            // runSyncForActiveCompetitions; the daily fixtures sync is the
            // long-term backstop.
            $settings->set(self::SETTING_LAST_RESYNC_VERSION, (string) $module->getVersion());
        }
    }

    public static function onCronDaily($event): void
    {
        self::runSyncForActiveCompetitions($event, syncFixtures: true);
    }

    /**
     * Per-minute hook for live polling. The HumHub crontab invokes `cron/run`
     * every minute, which triggers `EVENT_BEFORE_ACTION` on the controller. We
     * only act on the `run` action (skip `hourly`/`daily` to avoid double work)
     * and only when an active competition has a live-capable adapter, at least
     * one game in its live window, and the adapter's configured live-sync
     * interval has elapsed since the last poll.
     */
    public static function onCronBeforeAction($event): void
    {
        if (!isset($event->action) || $event->action->id !== 'run') {
            return;
        }

        $registry = Module::instance()->getAdapterRegistry();
        $settings = Module::instance()->settings;
        $now = time();
        $controller = $event->sender ?? null;
        $liveWindowSeconds = 115 * 60;

        // Test competitions tick along too: per-minute live-sync only calls the
        // adapter's syncResults for games already in their live window, and the
        // mock adapter relies on this to demo the LIVE UI. The hourly/daily
        // sync below still skips test competitions to avoid surprise data in
        // production.
        $competitions = Competition::find()
            ->where(['status' => Competition::STATUS_ACTIVE])
            ->all();

        foreach ($competitions as $competition) {
            $adapter = $registry->forCompetition($competition);
            if ($adapter === null) {
                continue;
            }
            $interval = $adapter->getLiveSyncIntervalMinutes();
            if ($interval === null || $interval <= 0) {
                continue;
            }

            $hasLive = \humhub\modules\kickoff\models\Game::find()
                ->where(['competition_id' => $competition->id])
                ->andWhere([
                    'or',
                    ['status' => \humhub\modules\kickoff\models\Game::STATUS_LIVE],
                    [
                        'and',
                        ['status' => \humhub\modules\kickoff\models\Game::STATUS_SCHEDULED],
                        ['between', 'kickoff_at',
                            KickoffTime::dbAt($now - $liveWindowSeconds),
                            KickoffTime::dbAt($now)],
                    ],
                ])
                ->exists();
            if (!$hasLive) {
                continue;
            }

            $stateKey = 'live_sync.' . $competition->id;
            $lastRun = (int) $settings->get($stateKey, 0);
            if ($lastRun > 0 && ($now - $lastRun) < ($interval * 60)) {
                continue;
            }

            try {
                $report = $adapter->syncResults($competition);
                $competition->updateAttributes(['last_synced_at' => KickoffTime::dbAt($now)]);
                $settings->set($stateKey, $now);

                if ($report->updated > 0) {
                    // Score regardless of partial errors: scoring is idempotent
                    // and a bad record (e.g. an undrawn knockout fixture) must
                    // not block scoring of the games that imported cleanly.
                    (new ScoringService($competition))->scoreAllFinishedGames();
                    (new MatchdayBonusService($competition))->awardForCompleteMatchdays();
                }
                self::log($controller, "Kickoff live sync [{$competition->slug}]: " . $report->summary());
            } catch (\Throwable $e) {
                Yii::error("Kickoff live sync failed for '{$competition->slug}': " . $e->getMessage());
                self::log($controller, "Kickoff live sync failed for [{$competition->slug}]: " . $e->getMessage(), Console::FG_RED);
            }
        }
    }

    /**
     * Auto-syncs all active non-test competitions whose adapter supports it.
     * Test competitions stay admin-driven so they don't trigger surprise mock results in production.
     */
    private static function runSyncForActiveCompetitions($event, bool $syncFixtures): void
    {
        $registry = Module::instance()->getAdapterRegistry();
        $controller = $event->sender ?? null;

        $competitions = Competition::find()
            ->where(['status' => Competition::STATUS_ACTIVE, 'is_test' => 0])
            ->all();

        foreach ($competitions as $competition) {
            $adapter = $registry->forCompetition($competition);
            if ($adapter === null || $adapter->getKey() === ManualAdapter::KEY) {
                continue;
            }

            try {
                if ($syncFixtures) {
                    $report = $adapter->syncFixtures($competition);
                    self::log($controller, "Kickoff fixtures [{$competition->slug}]: " . $report->summary());
                }

                $report = $adapter->syncResults($competition);
                $competition->updateAttributes(['last_synced_at' => KickoffTime::nowDb()]);
                self::log($controller, "Kickoff results [{$competition->slug}]: " . $report->summary());

                if ($report->updated > 0) {
                    // Score regardless of partial errors: scoring is idempotent
                    // and a bad record (e.g. an undrawn knockout fixture) must
                    // not block scoring of the games that imported cleanly.
                    $scored = (new ScoringService($competition))->scoreAllFinishedGames();
                    self::log($controller, "Kickoff scoring [{$competition->slug}]: {$scored} tip(s) updated.");
                }

                $autoResolved = (new SpecialBetResolver())->autoResolveAll($competition);
                if ($autoResolved > 0) {
                    self::log($controller, "Kickoff auto-resolve [{$competition->slug}]: {$autoResolved} bet(s) resolved.");
                }

                $awarded = (new MatchdayBonusService($competition))->awardForCompleteMatchdays();
                if ($awarded > 0) {
                    self::log($controller, "Kickoff matchday bonus [{$competition->slug}]: {$awarded} winner(s) awarded.");
                }

                $dispatcher = new NotificationDispatcher();
                $reminded = $dispatcher->sendDeadlineReminders($competition);
                if ($reminded > 0) {
                    self::log($controller, "Kickoff notifications [{$competition->slug}]: {$reminded} deadline reminder(s).");
                }
            } catch (\Throwable $e) {
                Yii::error("Kickoff cron sync failed for competition '{$competition->slug}': " . $e->getMessage());
                self::log($controller, "Kickoff sync failed for [{$competition->slug}]: " . $e->getMessage(), Console::FG_RED);
            }
        }
    }

    private static function log($controller, string $message, ?int $color = null): void
    {
        if ($controller === null || !method_exists($controller, 'stdout')) {
            Yii::info($message, 'kickoff');
            return;
        }
        if ($color !== null) {
            $controller->stdout($message . PHP_EOL, $color);
        } else {
            $controller->stdout($message . PHP_EOL);
        }
    }
}
