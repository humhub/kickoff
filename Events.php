<?php

namespace humhub\modules\kickoff;

use humhub\modules\kickoff\adapters\ManualAdapter;
use humhub\modules\kickoff\models\Competition;
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
