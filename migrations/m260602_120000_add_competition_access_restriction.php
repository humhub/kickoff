<?php

use humhub\components\Migration;

/**
 * Per-competition access restriction. Default 0 (public) so existing
 * competitions stay open to all logged-in members on upgrade — only
 * competitions an admin explicitly ticks become permission-gated.
 */
class m260602_120000_add_competition_access_restriction extends Migration
{
    public function up()
    {
        $this->addColumn(
            'kickoff_competition',
            'is_restricted',
            'tinyint(1) NOT NULL DEFAULT 0',
        );
    }

    public function down()
    {
        echo "m260602_120000_add_competition_access_restriction does not support migration down.\n";
        return false;
    }
}
