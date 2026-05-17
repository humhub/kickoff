<?php

use humhub\components\Migration;

class m260523_120000_drop_top_scorer_special_bets extends Migration
{
    public function up()
    {
        // Top-scorer bets are no longer supported (football-data.org free tier
        // doesn't expose scorers, so the bet was unresolvable). Drop any
        // lingering rows along with their tips.
        $betIds = $this->db->createCommand(
            'SELECT id FROM kickoff_special_bet WHERE type = :t',
            [':t' => 'top_scorer'],
        )->queryColumn();

        if ($betIds !== []) {
            $this->delete('kickoff_special_bet_tip', ['special_bet_id' => $betIds]);
            $this->delete('kickoff_special_bet', ['id' => $betIds]);
        }
    }

    public function down()
    {
        echo "m260523_120000_drop_top_scorer_special_bets does not support migration down.\n";
        return false;
    }
}
