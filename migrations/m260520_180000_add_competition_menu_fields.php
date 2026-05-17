<?php

use humhub\components\Migration;

class m260520_180000_add_competition_menu_fields extends Migration
{
    public function up()
    {
        $this->addColumn('kickoff_competition', 'show_in_main_menu', 'tinyint(1) NOT NULL DEFAULT 0');
        $this->addColumn('kickoff_competition', 'menu_title', 'varchar(255) NULL');
    }

    public function down()
    {
        echo "m260520_180000_add_competition_menu_fields does not support migration down.\n";
        return false;
    }
}
