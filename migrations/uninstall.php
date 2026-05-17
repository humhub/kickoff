<?php

use humhub\components\Migration;

/**
 * Drops every table the Kickoff module created so a module uninstall leaves
 * no orphan schema behind. Settings rows in `setting` / `contentcontainer_setting`
 * are cleaned up by HumHub's `ModuleManager` itself, so they don't need to
 * be handled here.
 *
 * Order matters: tables with outgoing foreign keys must drop *before* the
 * tables they reference (leaves first, parents last).
 */
class uninstall extends Migration
{
    public function up()
    {
        $this->dropTable('kickoff_special_bet_tip');
        $this->dropTable('kickoff_tip');
        $this->dropTable('kickoff_participation');
        $this->dropTable('kickoff_special_bet');
        $this->dropTable('kickoff_competition_team');
        $this->dropTable('kickoff_game');
        $this->dropTable('kickoff_competition');
        $this->dropTable('kickoff_team');
        $this->dropTable('kickoff_scoring_scheme');
    }

    public function down()
    {
        echo "uninstall does not support migration down.\n";
        return false;
    }
}
