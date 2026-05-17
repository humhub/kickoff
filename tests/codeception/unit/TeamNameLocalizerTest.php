<?php

namespace kickoff;

use Codeception\Test\Unit;
use humhub\modules\kickoff\services\TeamNameLocalizer;
use Locale;

class TeamNameLocalizerTest extends Unit
{
    public function testNormalizeToIso2PassesAlpha2Through(): void
    {
        $this->assertSame('DE', TeamNameLocalizer::normalizeToIso2('DE'));
        $this->assertSame('US', TeamNameLocalizer::normalizeToIso2('us'), 'lowercase upper-cased');
        $this->assertSame('BR', TeamNameLocalizer::normalizeToIso2('  BR  '), 'whitespace trimmed');
    }

    public function testNormalizeToIso2MapsFifaAndIso3(): void
    {
        $this->assertSame('DE', TeamNameLocalizer::normalizeToIso2('GER'), 'FIFA GER → DE');
        $this->assertSame('DE', TeamNameLocalizer::normalizeToIso2('DEU'), 'ISO-3 DEU → DE');
        $this->assertSame('CH', TeamNameLocalizer::normalizeToIso2('SUI'));
        $this->assertSame('CH', TeamNameLocalizer::normalizeToIso2('CHE'));
        $this->assertSame('NL', TeamNameLocalizer::normalizeToIso2('NED'));
        $this->assertSame('UY', TeamNameLocalizer::normalizeToIso2('URY'));
        $this->assertSame('UY', TeamNameLocalizer::normalizeToIso2('URU'));
        $this->assertSame('SA', TeamNameLocalizer::normalizeToIso2('KSA'));
        $this->assertSame('CL', TeamNameLocalizer::normalizeToIso2('CHI'));
        $this->assertSame('CD', TeamNameLocalizer::normalizeToIso2('COD'), 'DR Congo');
    }

    public function testNormalizeToIso2ReturnsNullForUnknownInputs(): void
    {
        $this->assertNull(TeamNameLocalizer::normalizeToIso2(null));
        $this->assertNull(TeamNameLocalizer::normalizeToIso2(''));
        $this->assertNull(TeamNameLocalizer::normalizeToIso2('   '));
        $this->assertNull(TeamNameLocalizer::normalizeToIso2('XYZ'));
        $this->assertNull(TeamNameLocalizer::normalizeToIso2('TOOLONG'));
    }

    public function testLocalizeTranslatesViaIntl(): void
    {
        if (!class_exists(Locale::class)) {
            $this->markTestSkipped('Intl extension not available');
        }
        $this->assertSame('Deutschland', TeamNameLocalizer::localize('GER', 'Germany', 'de'));
        $this->assertSame('Germany', TeamNameLocalizer::localize('GER', 'Germany', 'en'));
        $this->assertSame('Brésil', TeamNameLocalizer::localize('BRA', 'Brazil', 'fr'));
        $this->assertSame('Schweiz', TeamNameLocalizer::localize('CH', 'Switzerland', 'de'));
    }

    public function testLocalizeFallsBackToStoredNameWhenCodeUnknown(): void
    {
        if (!class_exists(Locale::class)) {
            $this->markTestSkipped('Intl extension not available');
        }
        $this->assertSame('FC Bayern', TeamNameLocalizer::localize(null, 'FC Bayern', 'de'));
        $this->assertSame('FC Bayern', TeamNameLocalizer::localize('', 'FC Bayern', 'de'));
        $this->assertSame('Mystery Club', TeamNameLocalizer::localize('XYZ', 'Mystery Club', 'de'));
    }
}
