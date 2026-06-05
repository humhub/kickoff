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

    public function testNormalizeToIso2MapsInternationalAndIso3(): void
    {
        $this->assertSame('DE', TeamNameLocalizer::normalizeToIso2('GER'), 'GER → DE');
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

    public function testNormalizeToIso2HandlesNonIsoFifaMembers(): void
    {
        $this->assertSame('XK', TeamNameLocalizer::normalizeToIso2('KVX'), 'Kosovo → user-assigned XK');
        // The home nations are ISO 3166-2 subdivisions, not countries — they
        // must NOT resolve to GB (England is not the United Kingdom).
        $this->assertNull(TeamNameLocalizer::normalizeToIso2('ENG'));
        $this->assertNull(TeamNameLocalizer::normalizeToIso2('SCO'));
        $this->assertNull(TeamNameLocalizer::normalizeToIso2('WAL'));
        $this->assertNull(TeamNameLocalizer::normalizeToIso2('NIR'));
        // The UK itself keeps resolving (e.g. an Olympic "Team GB").
        $this->assertSame('GB', TeamNameLocalizer::normalizeToIso2('GBR'));
    }

    public function testFlagCodepointsForCountries(): void
    {
        $this->assertSame([0x1F1E9, 0x1F1EA], TeamNameLocalizer::flagCodepoints('DE'));
        $this->assertSame([0x1F1E9, 0x1F1EA], TeamNameLocalizer::flagCodepoints('GER'), 'FIFA code resolves too');
        $this->assertSame([0x1F1FD, 0x1F1F0], TeamNameLocalizer::flagCodepoints('KVX'), 'Kosovo 🇽🇰');
        $this->assertNull(TeamNameLocalizer::flagCodepoints(null));
        $this->assertNull(TeamNameLocalizer::flagCodepoints('XYZ'));
    }

    public function testFlagCodepointsForHomeNations(): void
    {
        $this->assertSame(
            [0x1F3F4, 0xE0067, 0xE0062, 0xE0065, 0xE006E, 0xE0067, 0xE007F],
            TeamNameLocalizer::flagCodepoints('ENG'),
            'England tag sequence',
        );
        $this->assertSame(
            [0x1F3F4, 0xE0067, 0xE0062, 0xE0073, 0xE0063, 0xE0074, 0xE007F],
            TeamNameLocalizer::flagCodepoints('SCO'),
            'Scotland tag sequence',
        );
        $this->assertSame(
            [0x1F3F4, 0xE0067, 0xE0062, 0xE0077, 0xE006C, 0xE0073, 0xE007F],
            TeamNameLocalizer::flagCodepoints('WAL'),
            'Wales tag sequence',
        );
        $this->assertNull(TeamNameLocalizer::flagCodepoints('NIR'), 'Northern Ireland has no emoji flag');
    }

    public function testFlagEmoji(): void
    {
        $this->assertSame('🇫🇷', TeamNameLocalizer::flagEmoji('FRA'));
        $this->assertSame('🏴󠁧󠁢󠁥󠁮󠁧󠁿', TeamNameLocalizer::flagEmoji('ENG'));
        $this->assertNull(TeamNameLocalizer::flagEmoji('NIR'));
        $this->assertNull(TeamNameLocalizer::flagEmoji(null));
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

    public function testLocalizeHomeNationsAndKosovo(): void
    {
        if (!class_exists(Locale::class)) {
            $this->markTestSkipped('Intl extension not available');
        }
        // England must NOT become "Vereinigtes Königreich" — no ISO country,
        // so the stored name passes through on every UI language.
        $this->assertSame('England', TeamNameLocalizer::localize('ENG', 'England', 'de'));
        $this->assertSame('Scotland', TeamNameLocalizer::localize('SCO', 'Scotland', 'fr'));
        $this->assertSame('Northern Ireland', TeamNameLocalizer::localize('NIR', 'Northern Ireland', 'de'));
        // Kosovo resolves via the user-assigned XK, which CLDR localizes.
        $this->assertSame('Kosovo', TeamNameLocalizer::localize('KVX', 'Kosovo', 'de'));
    }
}
