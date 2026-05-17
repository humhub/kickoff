<?php

use humhub\components\Migration;

/**
 * Matchday-winner bonus: the highest-scoring participant(s) of each matchday
 * (group stage matchdays 1–3, each KO round, and the bonus round of special
 * bets) get extra points credited to their leaderboard total.
 *
 * Schema:
 *   - `kickoff_scoring_scheme.matchday_winner_points` is the per-matchday
 *     bonus value. Zero disables the feature; default is 5 (enabled).
 *   - `kickoff_matchday_bonus` records who got the bonus for which matchday,
 *     so the same matchday is never double-awarded and admins can audit.
 *
 * `matchday_key` is a stable string identifying the matchday — see
 * MatchdayBonusService::bucketsFor(): `group-md-1`, `ko-round_of_16`,
 * `bonus` (special bets), etc.
 */
class m260525_120000_add_matchday_winner_bonus extends Migration
{
    public function up()
    {
        $this->addColumn(
            'kickoff_scoring_scheme',
            'matchday_winner_points',
            'int NOT NULL DEFAULT 5 AFTER points_tendency',
        );

        $this->createTable('kickoff_matchday_bonus', [
            'competition_id' => 'int NOT NULL',
            'matchday_key' => 'varchar(64) NOT NULL',
            'user_id' => 'int NOT NULL',
            'points' => 'int NOT NULL',
            'awarded_at' => 'datetime NOT NULL',
        ], '');
        $this->addPrimaryKey(
            'pk_kickoff_matchday_bonus',
            'kickoff_matchday_bonus',
            ['competition_id', 'matchday_key', 'user_id'],
        );
        $this->createIndex(
            'idx_kickoff_matchday_bonus_user',
            'kickoff_matchday_bonus',
            ['competition_id', 'user_id'],
        );
        $this->addForeignKey(
            'fk_kickoff_matchday_bonus_comp',
            'kickoff_matchday_bonus',
            'competition_id',
            'kickoff_competition',
            'id',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_kickoff_matchday_bonus_user',
            'kickoff_matchday_bonus',
            'user_id',
            'user',
            'id',
            'CASCADE',
        );
    }

    public function down()
    {
        echo "m260525_120000_add_matchday_winner_bonus does not support migration down.\n";
        return false;
    }
}
