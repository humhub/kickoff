<?php

use humhub\components\Migration;

class m260517_120000_init extends Migration
{
    public function up()
    {
        $this->createTable('kickoff_scoring_scheme', [
            'id' => 'pk',
            'name' => 'varchar(255) NOT NULL',
            'points_exact' => 'int NOT NULL DEFAULT 4',
            'points_goal_diff' => 'int NOT NULL DEFAULT 3',
            'points_tendency' => 'int NOT NULL DEFAULT 2',
            'special_bet_rules' => 'text NULL',
            'created_at' => 'datetime NULL',
        ], '');

        $this->createTable('kickoff_competition', [
            'id' => 'pk',
            'name' => 'varchar(255) NOT NULL',
            'slug' => 'varchar(100) NOT NULL',
            'type' => 'varchar(32) NOT NULL',
            'season' => 'varchar(32) NULL',
            'starts_at' => 'datetime NULL',
            'ends_at' => 'datetime NULL',
            'status' => "varchar(32) NOT NULL DEFAULT 'draft'",
            'is_test' => 'tinyint(1) NOT NULL DEFAULT 0',
            'scoring_scheme_id' => 'int NOT NULL',
            'ko_scoring_mode' => "varchar(32) NOT NULL DEFAULT 'regular_time'",
            'data_source' => "varchar(64) NOT NULL DEFAULT 'manual'",
            'data_source_config' => 'text NULL',
            'last_synced_at' => 'datetime NULL',
            'created_at' => 'datetime NULL',
            'updated_at' => 'datetime NULL',
            'created_by' => 'int NULL',
        ], '');
        $this->createIndex('idx_kickoff_competition_slug', 'kickoff_competition', 'slug', true);
        $this->createIndex('idx_kickoff_competition_status', 'kickoff_competition', 'status');
        $this->addForeignKey('fk_kickoff_competition_scheme', 'kickoff_competition', 'scoring_scheme_id', 'kickoff_scoring_scheme', 'id', 'RESTRICT');
        $this->addForeignKey('fk_kickoff_competition_creator', 'kickoff_competition', 'created_by', 'user', 'id', 'SET NULL');

        $this->createTable('kickoff_team', [
            'id' => 'pk',
            'name' => 'varchar(255) NOT NULL',
            'short_name' => 'varchar(64) NULL',
            'country_code' => 'varchar(8) NULL',
            'logo_url' => 'varchar(500) NULL',
            'external_ids' => 'text NULL',
            'created_at' => 'datetime NULL',
        ], '');
        $this->createIndex('idx_kickoff_team_country', 'kickoff_team', 'country_code');

        $this->createTable('kickoff_competition_team', [
            'competition_id' => 'int NOT NULL',
            'team_id' => 'int NOT NULL',
            'group_label' => 'varchar(16) NULL',
        ], '');
        $this->addPrimaryKey('pk_kickoff_competition_team', 'kickoff_competition_team', ['competition_id', 'team_id']);
        $this->createIndex('idx_kickoff_competition_team_group', 'kickoff_competition_team', ['competition_id', 'group_label']);
        $this->addForeignKey('fk_kickoff_competition_team_comp', 'kickoff_competition_team', 'competition_id', 'kickoff_competition', 'id', 'CASCADE');
        $this->addForeignKey('fk_kickoff_competition_team_team', 'kickoff_competition_team', 'team_id', 'kickoff_team', 'id', 'RESTRICT');

        $this->createTable('kickoff_game', [
            'id' => 'pk',
            'competition_id' => 'int NOT NULL',
            'home_team_id' => 'int NOT NULL',
            'away_team_id' => 'int NOT NULL',
            'kickoff_at' => 'datetime NOT NULL',
            'stage' => 'varchar(32) NOT NULL',
            'round_label' => 'varchar(64) NULL',
            'group_label' => 'varchar(16) NULL',
            'status' => "varchar(32) NOT NULL DEFAULT 'scheduled'",
            'home_score' => 'int NULL',
            'away_score' => 'int NULL',
            'home_score_et' => 'int NULL',
            'away_score_et' => 'int NULL',
            'home_score_pen' => 'int NULL',
            'away_score_pen' => 'int NULL',
            'external_id' => 'varchar(64) NULL',
            'last_synced_at' => 'datetime NULL',
        ], '');
        $this->createIndex('idx_kickoff_game_comp_kickoff', 'kickoff_game', ['competition_id', 'kickoff_at']);
        $this->createIndex('idx_kickoff_game_comp_external', 'kickoff_game', ['competition_id', 'external_id']);
        $this->createIndex('idx_kickoff_game_status', 'kickoff_game', 'status');
        $this->addForeignKey('fk_kickoff_game_comp', 'kickoff_game', 'competition_id', 'kickoff_competition', 'id', 'CASCADE');
        $this->addForeignKey('fk_kickoff_game_home', 'kickoff_game', 'home_team_id', 'kickoff_team', 'id', 'RESTRICT');
        $this->addForeignKey('fk_kickoff_game_away', 'kickoff_game', 'away_team_id', 'kickoff_team', 'id', 'RESTRICT');

        $this->createTable('kickoff_participation', [
            'competition_id' => 'int NOT NULL',
            'user_id' => 'int NOT NULL',
            'joined_at' => 'datetime NOT NULL',
            'display_name' => 'varchar(255) NULL',
        ], '');
        $this->addPrimaryKey('pk_kickoff_participation', 'kickoff_participation', ['competition_id', 'user_id']);
        $this->addForeignKey('fk_kickoff_participation_comp', 'kickoff_participation', 'competition_id', 'kickoff_competition', 'id', 'CASCADE');
        $this->addForeignKey('fk_kickoff_participation_user', 'kickoff_participation', 'user_id', 'user', 'id', 'CASCADE');

        $this->createTable('kickoff_tip', [
            'id' => 'pk',
            'game_id' => 'int NOT NULL',
            'user_id' => 'int NOT NULL',
            'home_score' => 'int NOT NULL',
            'away_score' => 'int NOT NULL',
            'submitted_at' => 'datetime NOT NULL',
            'locked' => 'tinyint(1) NOT NULL DEFAULT 0',
            'points' => 'int NULL',
        ], '');
        $this->createIndex('idx_kickoff_tip_game_user', 'kickoff_tip', ['game_id', 'user_id'], true);
        $this->createIndex('idx_kickoff_tip_user_points', 'kickoff_tip', ['user_id', 'points']);
        $this->addForeignKey('fk_kickoff_tip_game', 'kickoff_tip', 'game_id', 'kickoff_game', 'id', 'CASCADE');
        $this->addForeignKey('fk_kickoff_tip_user', 'kickoff_tip', 'user_id', 'user', 'id', 'CASCADE');

        $this->createTable('kickoff_special_bet', [
            'id' => 'pk',
            'competition_id' => 'int NOT NULL',
            'type' => 'varchar(32) NOT NULL',
            'question' => 'varchar(500) NOT NULL',
            'options' => 'text NULL',
            'group_label' => 'varchar(16) NULL',
            'points' => 'int NOT NULL DEFAULT 10',
            'deadline_at' => 'datetime NOT NULL',
            'resolved_value' => 'varchar(255) NULL',
            'resolved_at' => 'datetime NULL',
        ], '');
        $this->createIndex('idx_kickoff_special_bet_comp', 'kickoff_special_bet', 'competition_id');
        $this->addForeignKey('fk_kickoff_special_bet_comp', 'kickoff_special_bet', 'competition_id', 'kickoff_competition', 'id', 'CASCADE');

        $this->createTable('kickoff_special_bet_tip', [
            'id' => 'pk',
            'special_bet_id' => 'int NOT NULL',
            'user_id' => 'int NOT NULL',
            'value' => 'varchar(255) NOT NULL',
            'submitted_at' => 'datetime NOT NULL',
            'points' => 'int NULL',
        ], '');
        $this->createIndex('idx_kickoff_special_bet_tip_unique', 'kickoff_special_bet_tip', ['special_bet_id', 'user_id'], true);
        $this->addForeignKey('fk_kickoff_special_bet_tip_bet', 'kickoff_special_bet_tip', 'special_bet_id', 'kickoff_special_bet', 'id', 'CASCADE');
        $this->addForeignKey('fk_kickoff_special_bet_tip_user', 'kickoff_special_bet_tip', 'user_id', 'user', 'id', 'CASCADE');

        $this->insert('kickoff_scoring_scheme', [
            'name' => 'Classic 4/3/2',
            'points_exact' => 4,
            'points_goal_diff' => 3,
            'points_tendency' => 2,
            'special_bet_rules' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down()
    {
        echo "m260517_120000_init does not support migration down.\n";
        return false;
    }
}
