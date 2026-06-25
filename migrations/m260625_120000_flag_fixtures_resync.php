<?php

use humhub\components\Migration;
use humhub\modules\kickoff\Events;
use humhub\modules\kickoff\Module;

/**
 * Schedules a one-off full fixtures re-sync on the next hourly cron. Set on
 * update so a corrected data mapping (here: football-data stage `LAST_32` →
 * round of 32) is re-stamped onto already-imported games automatically — no
 * admin has to trigger a manual sync. The flag is consumed and cleared by
 * {@see Events::onCronHourly()}. Harmless if the data is already correct: the
 * re-sync is idempotent.
 */
class m260625_120000_flag_fixtures_resync extends Migration
{
    public function up()
    {
        $module = Module::instance();
        if ($module === null) {
            echo "kickoff module not available; skipping fixtures re-sync flag.\n";
            return;
        }
        $module->settings->set(Events::SETTING_PENDING_FIXTURES_RESYNC, 1);
    }

    public function down()
    {
        echo "m260625_120000_flag_fixtures_resync does not support migration down.\n";
        return false;
    }
}
