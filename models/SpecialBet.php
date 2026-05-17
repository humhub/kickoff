<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;

/**
 * @property int $id
 * @property int $competition_id
 * @property string $type
 * @property string $question
 * @property string|null $options
 * @property string|null $group_label
 * @property int $points
 * @property string $deadline_at
 * @property string|null $resolved_value
 * @property string|null $resolved_at
 */
class SpecialBet extends ActiveRecord
{
    public const TYPE_WINNER = 'winner';
    public const TYPE_TOP_SCORER = 'top_scorer';
    public const TYPE_GROUP_WINNER = 'group_winner';

    public static function tableName()
    {
        return 'kickoff_special_bet';
    }

    public function rules()
    {
        return [
            [['competition_id', 'type', 'question', 'points', 'deadline_at'], 'required'],
            [['competition_id', 'points'], 'integer'],
            [['type'], 'in', 'range' => [self::TYPE_WINNER, self::TYPE_TOP_SCORER, self::TYPE_GROUP_WINNER]],
            [['question'], 'string', 'max' => 500],
            [['options', 'resolved_value'], 'string'],
            [['group_label'], 'string', 'max' => 16],
            [['deadline_at', 'resolved_at'], 'safe'],
        ];
    }

    public function getOptions(): array
    {
        return $this->options ? (json_decode($this->options, true) ?? []) : [];
    }

    public function setOptions(array $options): void
    {
        $this->options = $options === [] ? null : json_encode($options);
    }

    public function isDeadlinePassed(): bool
    {
        return strtotime($this->deadline_at) <= time();
    }

    public function isResolved(): bool
    {
        return $this->resolved_value !== null;
    }

    public function getCompetition()
    {
        return $this->hasOne(Competition::class, ['id' => 'competition_id']);
    }

    public function getTips()
    {
        return $this->hasMany(SpecialBetTip::class, ['special_bet_id' => 'id']);
    }
}
