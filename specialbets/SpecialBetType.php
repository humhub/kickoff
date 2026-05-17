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

    /**
     * Attempts to determine the resolved value from the current competition state
     * (e.g. from finished games). Return the value as it would be stored in
     * `resolved_value` (e.g. a team id as string), or null if the data isn't
     * conclusive yet (e.g. group stage not finished, final still tied).
     */
    public function tryResolve(SpecialBet $bet, Competition $competition): ?string;
}
