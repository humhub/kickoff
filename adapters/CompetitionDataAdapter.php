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
}
