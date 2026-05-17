<?php

namespace humhub\modules\kickoff\models;

use humhub\components\ActiveRecord;
use humhub\modules\kickoff\services\KickoffTime;

/**
 * @property int $id
 * @property int $competition_id
 * @property string $type
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
    public const TYPE_GROUP_WINNER = 'group_winner';

    public static function tableName()
    {
        return 'kickoff_special_bet';
    }

    public function rules()
    {
        return [
            [['competition_id', 'type', 'points', 'deadline_at'], 'required'],
            [['competition_id', 'points'], 'integer'],
            [['type'], 'in', 'range' => [self::TYPE_WINNER, self::TYPE_GROUP_WINNER]],
            [['options', 'resolved_value'], 'string'],
            [['group_label'], 'string', 'max' => 16],
            [['deadline_at', 'resolved_at'], 'safe'],
        ];
    }

    /**
     * Translated, type-derived question shown to users and admins. Defined by
     * the bet's type (with the group label folded in for group winners), so
     * questions translate cleanly into German / English / future locales.
     */
    public function getDisplayQuestion(): string
    {
        $type = \humhub\modules\kickoff\Module::instance()
            ->getSpecialBetTypeRegistry()
            ->get($this->type);
        return $type !== null
            ? $type->getDefaultQuestion($this)
            : ($this->type . ($this->group_label ? ' ' . $this->group_label : ''));
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
        $deadline = KickoffTime::parse($this->deadline_at);
        return $deadline !== null && $deadline <= time();
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
