<?php

use humhub\components\Migration;

class m260524_120000_add_team_ratings extends Migration
{
    public function up()
    {
        $this->addColumn('kickoff_team', 'fifa_points', 'int NULL');
        $this->addColumn('kickoff_team', 'elo_rating', 'int NULL');
    }

    public function down()
    {
        echo "m260524_120000_add_team_ratings does not support migration down.\n";
        return false;
    }
}
