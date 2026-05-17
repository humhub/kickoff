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
     * True if this type cannot be auto-resolved from competition data.
     * Auto-resolvable types hide the per-row Resolve button in the admin UI
     * and rely on the "Auto-resolve" action instead.
     */
    public function isManualResolveOnly(): bool;

    /**
     * Attempts to determine the resolved value from the current competition state
     * (e.g. from finished games). Return the value as it would be stored in
     * `resolved_value` (e.g. a team id as string), or null if the data isn't
     * conclusive yet (e.g. group stage not finished, final still tied).
     */
    public function tryResolve(SpecialBet $bet, Competition $competition): ?string;

    /**
     * Localized default question shown when the bet has no custom `question`.
     * Uses the bet's type (and `group_label` for group winners) so the question
     * is fully translatable instead of frozen as a raw string in the database.
     */
    public function getDefaultQuestion(SpecialBet $bet): string;
}
