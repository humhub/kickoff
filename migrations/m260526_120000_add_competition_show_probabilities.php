<?php

use humhub\components\Migration;

class m260526_120000_add_competition_show_probabilities extends Migration
{
    public function up()
    {
        $this->addColumn(
            'kickoff_competition',
            'show_probabilities',
            'tinyint(1) NOT NULL DEFAULT 1',
        );
    }

    public function down()
    {
        echo "m260526_120000_add_competition_show_probabilities does not support migration down.\n";
        return false;
    }
}
