<?php

namespace humhub\modules\kickoff\services;

/**
 * Resolves a localized display name for a team from its `country_code`,
 * using PHP's Intl extension (ICU/CLDR territory data) — no hand-maintained
 * translation tables needed. Works for national teams; club teams without a
 * country code simply fall back to the stored name.
 *
 * Adapters may store the country code as ISO-3166-1 alpha-2 (mock), alpha-3
 * (some football-data fields), or 3-letter FIFA codes (`GER`, `SUI`, `KSA`).
 * `normalizeToIso2()` collapses all three onto a canonical alpha-2.
 *
 * FIFA members without an ISO 3166-1 identity get special treatment: the
 * British home nations (England, Scotland, Wales) are ISO 3166-2
 * subdivisions of GB whose flags are Unicode tag sequences (see
 * `flagCodepoints()`), and Kosovo maps to the user-assigned-but-universal
 * `XK`. Their names fall back to the stored team name where ICU can't help
 * (PHP's intl exposes territory names only, not subdivision names).
 */
final class TeamNameLocalizer
{
    /**
     * 3-letter (FIFA / ISO-3) → ISO-2 alias map for nations the module is
     * likely to see. Codes already in alpha-2 are not listed — they pass
     * through unchanged. Where FIFA and ISO-3 disagree (GER/DEU, SUI/CHE, …)
     * both spellings are present.
     *
     * Deliberately NOT mapped: ENG/SCO/WAL (subdivision flags, see
     * SUBDIVISION_FLAGS) and NIR (no ISO code and no emoji flag — Unicode
     * declined the politically contested Ulster Banner — so Northern Ireland
     * intentionally falls through to the neutral initials badge).
     */
    private const ISO3_TO_ISO2 = [
        'ARG' => 'AR', 'FRA' => 'FR', 'BRA' => 'BR', 'ESP' => 'ES',
        'GBR' => 'GB',
        'KVX' => 'XK', // Kosovo: FIFA KVX → user-assigned ISO-2, resolved by ICU/CLDR
        'POR' => 'PT', 'PRT' => 'PT',
        'NED' => 'NL', 'NLD' => 'NL',
        'BEL' => 'BE',
        'CRO' => 'HR', 'HRV' => 'HR',
        'ITA' => 'IT',
        'GER' => 'DE', 'DEU' => 'DE',
        'MAR' => 'MA',
        'JPN' => 'JP',
        'URU' => 'UY', 'URY' => 'UY',
        'DEN' => 'DK', 'DNK' => 'DK',
        'COL' => 'CO',
        'SUI' => 'CH', 'CHE' => 'CH',
        'USA' => 'US',
        'SEN' => 'SN',
        'TUR' => 'TR',
        'IRN' => 'IR', 'IRI' => 'IR',
        'SRB' => 'RS',
        'MEX' => 'MX',
        'KOR' => 'KR',
        'POL' => 'PL',
        'CAN' => 'CA',
        'AUT' => 'AT',
        'HUN' => 'HU',
        'AUS' => 'AU',
        'ALG' => 'DZ', 'DZA' => 'DZ',
        'NOR' => 'NO',
        'NGA' => 'NG',
        'ECU' => 'EC',
        'CIV' => 'CI',
        'CHI' => 'CL', 'CHL' => 'CL',
        'EGY' => 'EG',
        'PER' => 'PE',
        'TUN' => 'TN',
        'PAR' => 'PY', 'PRY' => 'PY',
        'GHA' => 'GH',
        'SVN' => 'SI',
        'KSA' => 'SA', 'SAU' => 'SA',
        'VEN' => 'VE',
        'QAT' => 'QA',
        'PAN' => 'PA',
        'JAM' => 'JM',
        'NZL' => 'NZ',
        'CMR' => 'CM',
        'CRC' => 'CR', 'CRI' => 'CR',
        'SVK' => 'SK',
        'SWE' => 'SE',
        'ISL' => 'IS',
        'CZE' => 'CZ',
        'ROU' => 'RO', 'ROM' => 'RO',
        'GRE' => 'GR', 'GRC' => 'GR',
        'BIH' => 'BA',
        'UZB' => 'UZ',
        'IRQ' => 'IQ',
        'JOR' => 'JO',
        'COD' => 'CD',
        'RSA' => 'ZA', 'ZAF' => 'ZA',
        'CPV' => 'CV',
        'HAI' => 'HT', 'HTI' => 'HT',
        'CUW' => 'CW',
    ];

    /**
     * FIFA codes of teams that are ISO 3166-2 *subdivisions* (the British
     * home nations), keyed to the lowercase tag-letter part of their Unicode
     * tag-sequence flag (🏴 + tag letters + cancel tag). They have no ISO
     * 3166-1 code, so `normalizeToIso2()` keeps returning null for them —
     * names fall back to the stored team name, only the flag is resolvable.
     */
    private const SUBDIVISION_FLAGS = [
        'ENG' => 'gbeng',
        'SCO' => 'gbsct',
        'WAL' => 'gbwls',
    ];

    /**
     * Returns the canonical ISO-3166-1 alpha-2 code for whatever variant the
     * adapter wrote in, or null if the input doesn't look like a country code
     * the module knows about.
     */
    public static function normalizeToIso2(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        if (strlen($code) === 2 && ctype_alpha($code)) {
            return $code;
        }
        return self::ISO3_TO_ISO2[$code] ?? null;
    }

    /**
     * Returns the Unicode codepoint sequence of the team's flag emoji, or
     * null when no flag exists for the code. Two shapes:
     *  - countries: a regional-indicator pair (e.g. DE → U+1F1E9 U+1F1EA);
     *  - home nations (ENG/SCO/WAL): a tag sequence — black flag, one tag
     *    character per subdivision letter, cancel tag.
     *
     * The sequence doubles as the Twemoji asset filename (codepoints in
     * lowercase hex, joined by "-"), so emoji rendering and SVG lookup can't
     * drift apart.
     *
     * @return int[]|null
     */
    public static function flagCodepoints(?string $code): ?array
    {
        $raw = strtoupper(trim((string) $code));
        if (isset(self::SUBDIVISION_FLAGS[$raw])) {
            $codepoints = [0x1F3F4]; // 🏴 waving black flag
            foreach (str_split(self::SUBDIVISION_FLAGS[$raw]) as $letter) {
                $codepoints[] = 0xE0000 + ord($letter); // tag character
            }
            $codepoints[] = 0xE007F; // cancel tag
            return $codepoints;
        }

        $iso2 = self::normalizeToIso2($code);
        if ($iso2 === null) {
            return null;
        }
        return [
            0x1F1E6 + ord($iso2[0]) - 65,
            0x1F1E6 + ord($iso2[1]) - 65,
        ];
    }

    /**
     * Returns the team's flag as an emoji string, or null when no flag
     * exists for the code (clubs, unknown codes, Northern Ireland).
     */
    public static function flagEmoji(?string $code): ?string
    {
        $codepoints = self::flagCodepoints($code);
        if ($codepoints === null) {
            return null;
        }
        return implode('', array_map(
            static fn(int $codepoint): string => mb_chr($codepoint, 'UTF-8'),
            $codepoints,
        ));
    }

    /**
     * Returns a localized name for the team (e.g. "Deutschland" / "Germany"
     * / "Brésil") via Intl. Falls back to `$fallbackName` whenever:
     *  - no country code is set (clubs, manual teams);
     *  - the code can't be mapped to an alpha-2;
     *  - Intl returns an "unknown region" placeholder.
     *
     * `$language` is typically `Yii::$app->language`; pass null to use the
     * server default. Accepts both "de" and "de-DE" style locale strings.
     */
    public static function localize(?string $countryCode, string $fallbackName, ?string $language = null): string
    {
        $iso2 = self::normalizeToIso2($countryCode);
        if ($iso2 === null) {
            return $fallbackName;
        }
        $lang = $language !== null && $language !== '' ? $language : 'en';
        $localized = \Locale::getDisplayRegion('-' . $iso2, $lang);
        // ICU returns the raw code (e.g. "ZZ") when it can't resolve the
        // region — fall back rather than show that to users.
        if ($localized === '' || $localized === $iso2) {
            return $fallbackName;
        }
        return $localized;
    }
}
