<?php

use humhub\components\Migration;

class m260518_120000_add_game_venue extends Migration
{
    public function up()
    {
        $this->addColumn('kickoff_game', 'venue', 'varchar(255) NULL');
    }

    public function down()
    {
        echo "m260518_120000_add_game_venue does not support migration down.\n";
        return false;
    }
}
