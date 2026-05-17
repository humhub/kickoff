<?php

use humhub\components\Migration;

class m260519_120000_add_game_current_minute extends Migration
{
    public function up()
    {
        $this->addColumn('kickoff_game', 'current_minute', 'int NULL');
    }

    public function down()
    {
        echo "m260519_120000_add_game_current_minute does not support migration down.\n";
        return false;
    }
}
