<?php

namespace humhub\modules\kickoff\adapters;

use humhub\modules\kickoff\models\Competition;

interface CompetitionDataAdapter
{
    public function getKey(): string;

    public function getLabel(): string;

    public function isConfigured(): bool;

    /**
     * Returns external competitions this adapter can offer.
     * Each entry is `['id' => string, 'name' => string, 'season' => string|null]`.
     *
     * @return array<int, array{id:string, name:string, season:?string}>
     */
    public function listAvailable(): array;

    public function syncFixtures(Competition $competition): SyncReport;

    public function syncResults(Competition $competition): SyncReport;

    public function supportsLive(): bool;

    /**
     * Stages this adapter expects a competition to have, in tournament progression
     * order. Used by the UI to render placeholder entries for stages whose bracket
     * isn't decided yet ("Semi-finals · TBD"). Always include `Game::STAGE_GROUP`
     * even for pure-knockout competitions if you want a group entry; leave out
     * stages that this format will never produce.
     *
     * @return string[]
     */
    public function getExpectedStages(): array;
}
