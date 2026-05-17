<?php

use humhub\components\Migration;

class m260522_120000_add_game_matchday_number extends Migration
{
    public function up()
    {
        $this->addColumn('kickoff_game', 'matchday_number', 'int NULL');
    }

    public function down()
    {
        echo "m260522_120000_add_game_matchday_number does not support migration down.\n";
        return false;
    }
}
