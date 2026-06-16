<?php

namespace kickoff;

use Codeception\Test\Unit;
use humhub\modules\kickoff\assets\Assets;
use Yii;

class AssetsTest extends Unit
{
    public function testFlagUrlResolvesUnresolvedWebAlias(): void
    {
        // On HumHub develop the published bundle base URL is the unresolved
        // alias `@web/assets/<hash>`. It must not leak into the emitted URL,
        // otherwise the browser resolves the literal `@web/...` against the
        // current page and the flag 404s.
        $previous = Yii::getAlias('@web', false);
        Yii::setAlias('@web', '/sub');
        try {
            $url = Assets::flagUrl('@web/assets/abc123', '1f1f2-1f1fd');
        } finally {
            Yii::setAlias('@web', $previous === false ? null : $previous);
        }

        $this->assertStringNotContainsString('@web', $url);
        $this->assertSame('/sub/assets/abc123/flags/1f1f2-1f1fd.svg', $url);
    }

    public function testFlagUrlLeavesResolvedBaseUrlUntouched(): void
    {
        // On stable the bundle base URL is already a resolved path; it must
        // pass through unchanged.
        $this->assertSame(
            '/assets/abc123/flags/1f1f2-1f1fd.svg',
            Assets::flagUrl('/assets/abc123', '1f1f2-1f1fd'),
        );
    }
}
