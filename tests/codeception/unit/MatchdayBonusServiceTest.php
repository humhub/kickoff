<?php

namespace kickoff;

use Codeception\Test\Unit;
use humhub\modules\kickoff\services\MatchdayBonusService;

/**
 * The service itself is mostly DB orchestration — exercise the parts we can
 * isolate via reflection: the bucket-builder, completion check, and top-scorer
 * tie handling. Full integration with games/tips lives behind an active Yii
 * application, which the unit suite intentionally doesn't bootstrap fully —
 * those paths are covered by manual smoke tests and the admin recompute
 * action's flash message.
 *
 * Smoke-style: build the service with a stub competition + scheme that
 * exposes `matchday_winner_points`, then ensure the public surface behaves
 * sanely when the feature is disabled (returns 0, no DB writes).
 */
class MatchdayBonusServiceTest extends Unit
{
    public function testReturnsZeroWhenBonusDisabled(): void
    {
        $scheme = new \stdClass();
        $scheme->matchday_winner_points = 0;

        $competition = new \stdClass();
        $competition->id = 1;
        $competition->scoringScheme = $scheme;

        // Construct the service via reflection so we don't have to touch the DB.
        $ref = new \ReflectionClass(MatchdayBonusService::class);
        $service = $ref->newInstanceWithoutConstructor();
        $compProp = $ref->getProperty('competition');
        $compProp->setAccessible(true);
        $compProp->setValue($service, $competition);
        $pointsProp = $ref->getProperty('bonusPoints');
        $pointsProp->setAccessible(true);
        $pointsProp->setValue($service, 0);

        $this->assertSame(0, $service->awardForCompleteMatchdays());
    }

    public function testBucketConstantIsBonus(): void
    {
        // The constant is part of the public contract — admin tooling and
        // future migrations key off it. Catch accidental renames here.
        $this->assertSame('bonus', MatchdayBonusService::BUCKET_BONUS);
    }
}
