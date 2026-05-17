<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * @property int $id
 * @property string $name
 * @property int $points_exact
 * @property int $points_goal_diff
 * @property int $points_tendency
 * @property int $matchday_winner_points
 * @property string|null $special_bet_rules
 * @property string|null $created_at
 */
class ScoringScheme extends ActiveRecord
{
    public static function tableName()
    {
        return 'kickoff_scoring_scheme';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false,
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['points_exact', 'points_goal_diff', 'points_tendency', 'matchday_winner_points'], 'integer', 'min' => 0],
            [['special_bet_rules'], 'string'],
        ];
    }

    /**
     * True when matchday-winner bonus awards are configured (i.e. the
     * per-matchday point value is non-zero). UI and rules pages should
     * skip the bonus column / explanation when this returns false.
     */
    public function hasMatchdayWinnerBonus(): bool
    {
        return (int) $this->matchday_winner_points > 0;
    }

    public function getSpecialBetRules(): array
    {
        return $this->special_bet_rules ? (json_decode($this->special_bet_rules, true) ?? []) : [];
    }

    public function setSpecialBetRules(array $rules): void
    {
        $this->special_bet_rules = $rules === [] ? null : json_encode($rules);
    }

    public function getCompetitions()
    {
        return $this->hasMany(Competition::class, ['scoring_scheme_id' => 'id']);
    }
}
