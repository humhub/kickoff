<?php

use humhub\components\Migration;

class m260520_120000_add_competition_info_page extends Migration
{
    public function up()
    {
        $this->addColumn('kickoff_competition', 'info_page_title', 'varchar(255) NULL');
        $this->addColumn('kickoff_competition', 'info_page_content', 'text NULL');
    }

    public function down()
    {
        echo "m260520_120000_add_competition_info_page does not support migration down.\n";
        return false;
    }
}
