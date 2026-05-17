<?php

use humhub\components\Migration;

class m260518_180000_add_competition_tip_visibility extends Migration
{
    public function up()
    {
        $this->addColumn(
            'kickoff_competition',
            'tips_visible_before_kickoff',
            'tinyint(1) NOT NULL DEFAULT 0',
        );
    }

    public function down()
    {
        echo "m260518_180000_add_competition_tip_visibility does not support migration down.\n";
        return false;
    }
}
