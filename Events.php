<?php

namespace humhub\modules\kickoff;

use humhub\modules\kickoff\adapters\ManualAdapter;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\services\NotificationDispatcher;
use humhub\modules\kickoff\services\ScoringService;
use Yii;
use yii\helpers\Console;
use yii\helpers\Url;

class Events
{
    public static function onTopMenuInit($event): void
    {
        try {
            $hasVisible = Competition::find()
                ->where(['status' => Competition::STATUS_ACTIVE])
                ->exists();
            if (!$hasVisible) {
                return;
            }
            $event->sender->addItem([
                'label' => Yii::t('KickoffModule.base', 'Kickoff'),
                'url' => Url::to(['/kickoff']),
                'icon' => '<i class="fa fa-futbol-o"></i>',
                'isActive' => Yii::$app->controller && Yii::$app->controller->module
                    && Yii::$app->controller->module->id === 'kickoff'
                    && Yii::$app->controller->id !== 'admin',
                'sortOrder' => 300,
            ]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    public static function onCronHourly($event): void
    {
        self::runSyncForActiveCompetitions($event, syncFixtures: false);
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

        $competitions = Competition::find()
            ->where(['status' => Competition::STATUS_ACTIVE, 'is_test' => 0])
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
                            date('Y-m-d H:i:s', $now - $liveWindowSeconds),
                            date('Y-m-d H:i:s', $now)],
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
                $competition->updateAttributes(['last_synced_at' => date('Y-m-d H:i:s', $now)]);
                $settings->set($stateKey, $now);

                if ($report->isSuccess() && $report->updated > 0) {
                    (new ScoringService($competition))->scoreAllFinishedGames();
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
                $competition->updateAttributes(['last_synced_at' => date('Y-m-d H:i:s')]);
                self::log($controller, "Kickoff results [{$competition->slug}]: " . $report->summary());

                if ($report->isSuccess() && $report->updated > 0) {
                    $scored = (new ScoringService($competition))->scoreAllFinishedGames();
                    self::log($controller, "Kickoff scoring [{$competition->slug}]: {$scored} tip(s) updated.");
                }

                $dispatcher = new NotificationDispatcher();
                $reminded = $dispatcher->sendDeadlineReminders($competition);
                $pointsNotified = $dispatcher->sendPointsAwarded($competition);
                if ($reminded > 0 || $pointsNotified > 0) {
                    self::log($controller, "Kickoff notifications [{$competition->slug}]: {$reminded} deadline reminder(s), {$pointsNotified} points digest(s).");
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
