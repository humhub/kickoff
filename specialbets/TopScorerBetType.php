<?php

namespace humhub\modules\kickoff\specialbets;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\SpecialBet;
use Yii;

class TopScorerBetType implements SpecialBetType
{
    public function getKey(): string
    {
        return SpecialBet::TYPE_TOP_SCORER;
    }

    public function getLabel(): string
    {
        return Yii::t('KickoffModule.base', 'Top scorer');
    }

    public function getDefaultPoints(): int
    {
        return 15;
    }

    public function buildOptions(Competition $competition, SpecialBet $bet): array
    {
        return [];
    }

    public function validateValue(string $value, SpecialBet $bet): bool
    {
        return trim($value) !== '';
    }

    public function needsGroupLabel(): bool
    {
        return false;
    }

    public function tryResolve(SpecialBet $bet, Competition $competition): ?string
    {
        // We don't track goal scorers — admin must resolve manually.
        return null;
    }
}
