<?php

namespace humhub\modules\kickoff\services;

/**
 * Resolves a localized display name for a team from its `country_code`,
 * using PHP's Intl extension (ICU/CLDR territory data) — no hand-maintained
 * translation tables needed. Works for national teams; club teams without a
 * country code simply fall back to the stored name.
 *
 * Adapters may store the country code as ISO-3166-1 alpha-2 (mock), alpha-3
 * (some football-data fields), or FIFA-style 3-letter codes (`GER`, `SUI`,
 * `KSA`). `normalizeToIso2()` collapses all three onto a canonical alpha-2.
 */
final class TeamNameLocalizer
{
    /**
     * FIFA / ISO-3 → ISO-2 alias map for nations the module is likely to see.
     * Codes already in alpha-2 are not listed — they pass through unchanged.
     */
    private const ISO3_TO_ISO2 = [
        'ARG' => 'AR', 'FRA' => 'FR', 'BRA' => 'BR', 'ESP' => 'ES',
        'ENG' => 'GB', 'GBR' => 'GB',
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
