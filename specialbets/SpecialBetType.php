<?php

namespace humhub\modules\kickoff\specialbets;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\SpecialBet;

interface SpecialBetType
{
    public function getKey(): string;

    public function getLabel(): string;

    public function getDefaultPoints(): int;

    /**
     * @return array<string, string> options keyed by stored value, mapped to display label.
     *                                Empty array means free-text answer.
     */
    public function buildOptions(Competition $competition, SpecialBet $bet): array;

    public function validateValue(string $value, SpecialBet $bet): bool;

    public function needsGroupLabel(): bool;
}
