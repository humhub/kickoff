<?php

use humhub\components\Migration;

class m260526_140000_add_competition_banner extends Migration
{
    public function up()
    {
        $this->addColumn(
            'kickoff_competition',
            'banner_image_url',
            'varchar(500) NULL',
        );
    }

    public function down()
    {
        echo "m260526_140000_add_competition_banner does not support migration down.\n";
        return false;
    }
}
