<?php

namespace humhub\modules\kickoff\services;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\SpecialBet;
use humhub\modules\kickoff\Module;
use humhub\modules\kickoff\services\KickoffTime;

/**
 * Auto-resolves still-open special bets whose type can determine the resolved
 * value from the current competition state (group standings, final result, …).
 *
 * Returns the number of bets that were just resolved on this pass. Triggers
 * scoring of all tips placed on each newly-resolved bet so leaderboards stay
 * consistent without an admin click.
 */
class SpecialBetResolver
{
    public function autoResolveAll(Competition $competition): int
    {
        $bets = SpecialBet::find()
            ->where(['competition_id' => $competition->id])
            ->andWhere(['IS', 'resolved_value', null])
            ->all();
        if ($bets === []) {
            return 0;
        }

        $registry = Module::instance()->getSpecialBetTypeRegistry();
        $scoring = new ScoringService($competition);
        $resolved = 0;

        foreach ($bets as $bet) {
            $type = $registry->get($bet->type);
            if ($type === null) {
                continue;
            }
            $value = $type->tryResolve($bet, $competition);
            if ($value === null) {
                continue;
            }
            $bet->resolved_value = $value;
            $bet->resolved_at = KickoffTime::nowDb();
            if ($bet->save()) {
                $scoring->scoreSpecialBet($bet);
                $resolved++;
            }
        }
        return $resolved;
    }
}
