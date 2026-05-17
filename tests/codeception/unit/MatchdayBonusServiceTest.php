<?php

namespace kickoff;

use Codeception\Test\Unit;
use humhub\modules\kickoff\services\MatchdayBonusService;

/**
 * The service itself is DB orchestration — its main behaviors (bucket
 * detection, top-scorer tie handling, idempotent inserts) need a live Yii
 * application with games/tips/bets seeded, which the unit suite intentionally
 * doesn't bootstrap. Full behavior is covered by the manual smoke test
 * around the admin "Recompute points" action and the cron flow.
 *
 * What we *can* freeze here is the public contract — the bucket key for the
 * special-bets round is part of it (migrations, admin tooling and any future
 * UI may key off the literal `bonus`).
 */
class MatchdayBonusServiceTest extends Unit
{
    public function testBucketConstantIsBonus(): void
    {
        $this->assertSame('bonus', MatchdayBonusService::BUCKET_BONUS);
    }
}
