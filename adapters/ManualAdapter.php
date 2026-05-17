<?php

namespace humhub\modules\kickoff\adapters;

use humhub\modules\kickoff\models\Competition;
use Yii;

class ManualAdapter implements CompetitionDataAdapter
{
    public const KEY = 'manual';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return Yii::t('KickoffModule.base', 'Manual entry');
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function listAvailable(): array
    {
        return [];
    }

    public function syncFixtures(Competition $competition): SyncReport
    {
        return new SyncReport();
    }

    public function syncResults(Competition $competition): SyncReport
    {
        return new SyncReport();
    }

    public function supportsLive(): bool
    {
        return false;
    }
}
