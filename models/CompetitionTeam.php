<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;

/**
 * @property int $competition_id
 * @property int $team_id
 * @property string|null $group_label
 */
class CompetitionTeam extends ActiveRecord
{
    public static function tableName()
    {
        return 'kickoff_competition_team';
    }

    public static function primaryKey()
    {
        return ['competition_id', 'team_id'];
    }

    public function rules()
    {
        return [
            [['competition_id', 'team_id'], 'required'],
            [['competition_id', 'team_id'], 'integer'],
            [['group_label'], 'string', 'max' => 16],
        ];
    }

    public function getCompetition()
    {
        return $this->hasOne(Competition::class, ['id' => 'competition_id']);
    }

    public function getTeam()
    {
        return $this->hasOne(Team::class, ['id' => 'team_id']);
    }
}
