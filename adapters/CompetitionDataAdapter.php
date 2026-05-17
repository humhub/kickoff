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

    /**
     * Returns an estimated date (YYYY-MM-DD) for a stage that isn't yet scheduled,
     * if the adapter can compute one (e.g. from a known tournament calendar).
     * Used by the matchday dropdown to label placeholder entries as
     * "Final · ~ Sun, 19 Jul" instead of just "TBD". Return null when there's
     * no sensible estimate.
     */
    public function getEstimatedStageDate(Competition $competition, string $stage): ?string;

    /**
     * Minimum minutes between live-data sync calls while at least one match of a
     * competition is in its live window. The per-minute cron handler uses this
     * to decide whether to call `syncResults()` again. Return `null` to opt out
     * of high-frequency live polling (the hourly cron still runs normally).
     */
    public function getLiveSyncIntervalMinutes(): ?int;
}
