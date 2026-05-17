<?php

use humhub\components\Migration;

class m260521_120000_drop_special_bet_question extends Migration
{
    public function up()
    {
        $this->dropColumn('kickoff_special_bet', 'question');
    }

    public function down()
    {
        echo "m260521_120000_drop_special_bet_question does not support migration down.\n";
        return false;
    }
}
