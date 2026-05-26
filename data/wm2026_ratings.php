<?php

/**
 * Plausible world-ranking-points and Elo-rating snapshot for FWC 2026 nations.
 * Each entry lists every country-code variant the team might appear under
 * (ISO-3166-1 alpha-2, alpha-3, and 3-letter international code) so the apply
 * action finds a match regardless of which adapter populated `kickoff_team`.
 *
 * Values are rough mid-2026 estimates — admins can override individual
 * teams via the team editor. Nations not in this list simply won't get
 * probabilities shown until ratings are filled manually. The `fifa` key name
 * mirrors the DB column `kickoff_team.fifa_points` (internal naming retained
 * for backwards compatibility).
 *
 * @return array<int, array{codes: array<int, string>, fifa: int, elo: int}>
 */
return [
    ['codes' => ['ARG', 'AR'],         'fifa' => 1864, 'elo' => 2125],
    ['codes' => ['FRA', 'FR'],         'fifa' => 1857, 'elo' => 2065],
    ['codes' => ['BRA', 'BR'],         'fifa' => 1780, 'elo' => 2080],
    ['codes' => ['ESP', 'ES'],         'fifa' => 1758, 'elo' => 2020],
    ['codes' => ['ENG', 'GB-ENG', 'GB'], 'fifa' => 1730, 'elo' => 2010],
    ['codes' => ['POR', 'PRT', 'PT'],  'fifa' => 1718, 'elo' => 1990],
    ['codes' => ['NED', 'NLD', 'NL'],  'fifa' => 1700, 'elo' => 1970],
    ['codes' => ['BEL', 'BE'],         'fifa' => 1675, 'elo' => 1925],
    ['codes' => ['CRO', 'HRV', 'HR'],  'fifa' => 1690, 'elo' => 1910],
    ['codes' => ['ITA', 'IT'],         'fifa' => 1665, 'elo' => 1940],
    ['codes' => ['GER', 'DEU', 'DE'],  'fifa' => 1645, 'elo' => 1960],
    ['codes' => ['MAR', 'MA'],         'fifa' => 1645, 'elo' => 1820],
    ['codes' => ['JPN', 'JP'],         'fifa' => 1640, 'elo' => 1800],
    ['codes' => ['URU', 'URY', 'UY'],  'fifa' => 1635, 'elo' => 1875],
    ['codes' => ['DEN', 'DNK', 'DK'],  'fifa' => 1655, 'elo' => 1800],
    ['codes' => ['COL', 'CO'],         'fifa' => 1620, 'elo' => 1810],
    ['codes' => ['SUI', 'CHE', 'CH'],  'fifa' => 1610, 'elo' => 1815],
    ['codes' => ['USA', 'US'],         'fifa' => 1565, 'elo' => 1745],
    ['codes' => ['SEN', 'SN'],         'fifa' => 1565, 'elo' => 1780],
    ['codes' => ['TUR', 'TR'],         'fifa' => 1565, 'elo' => 1780],
    ['codes' => ['IRN', 'IRI', 'IR'],  'fifa' => 1550, 'elo' => 1735],
    ['codes' => ['SRB', 'RS'],         'fifa' => 1545, 'elo' => 1770],
    ['codes' => ['MEX', 'MX'],         'fifa' => 1530, 'elo' => 1750],
    ['codes' => ['KOR', 'KR'],         'fifa' => 1530, 'elo' => 1755],
    ['codes' => ['POL', 'PL'],         'fifa' => 1530, 'elo' => 1745],
    ['codes' => ['CAN', 'CA'],         'fifa' => 1530, 'elo' => 1715],
    ['codes' => ['AUT', 'AT'],         'fifa' => 1530, 'elo' => 1760],
    ['codes' => ['HUN', 'HU'],         'fifa' => 1520, 'elo' => 1715],
    ['codes' => ['AUS', 'AU'],         'fifa' => 1510, 'elo' => 1715],
    ['codes' => ['ALG', 'DZA', 'DZ'],  'fifa' => 1500, 'elo' => 1715],
    ['codes' => ['NOR', 'NO'],         'fifa' => 1495, 'elo' => 1730],
    ['codes' => ['WAL', 'GB-WLS'],     'fifa' => 1495, 'elo' => 1720],
    ['codes' => ['NGA', 'NG'],         'fifa' => 1490, 'elo' => 1720],
    ['codes' => ['ECU', 'EC'],         'fifa' => 1480, 'elo' => 1735],
    ['codes' => ['SCO', 'GB-SCT'],     'fifa' => 1480, 'elo' => 1735],
    ['codes' => ['CIV', 'CI'],         'fifa' => 1475, 'elo' => 1710],
    ['codes' => ['CHI', 'CHL', 'CL'],  'fifa' => 1470, 'elo' => 1710],
    ['codes' => ['EGY', 'EG'],         'fifa' => 1470, 'elo' => 1680],
    ['codes' => ['PER', 'PE'],         'fifa' => 1465, 'elo' => 1700],
    ['codes' => ['TUN', 'TN'],         'fifa' => 1460, 'elo' => 1670],
    ['codes' => ['PAR', 'PRY', 'PY'],  'fifa' => 1455, 'elo' => 1700],
    ['codes' => ['GHA', 'GH'],         'fifa' => 1450, 'elo' => 1660],
    ['codes' => ['SVN', 'SI'],         'fifa' => 1450, 'elo' => 1680],
    ['codes' => ['KSA', 'SAU', 'SA'],  'fifa' => 1430, 'elo' => 1620],
    ['codes' => ['VEN', 'VE'],         'fifa' => 1420, 'elo' => 1665],
    ['codes' => ['QAT', 'QA'],         'fifa' => 1395, 'elo' => 1620],
    ['codes' => ['PAN', 'PA'],         'fifa' => 1380, 'elo' => 1620],
    ['codes' => ['JAM', 'JM'],         'fifa' => 1300, 'elo' => 1565],
    ['codes' => ['NZL', 'NZ'],         'fifa' => 1290, 'elo' => 1490],

    // Additional nations that show up in mock data or qualified squads.
    ['codes' => ['BIH', 'BA'],         'fifa' => 1450, 'elo' => 1700],
    ['codes' => ['UZB', 'UZ'],         'fifa' => 1450, 'elo' => 1700],
    ['codes' => ['IRQ', 'IQ'],         'fifa' => 1410, 'elo' => 1620],
    ['codes' => ['JOR', 'JO'],         'fifa' => 1390, 'elo' => 1610],
    ['codes' => ['COD', 'CD'],         'fifa' => 1430, 'elo' => 1670],
    ['codes' => ['RSA', 'ZAF', 'ZA'],  'fifa' => 1450, 'elo' => 1680],
    ['codes' => ['CPV', 'CV'],         'fifa' => 1380, 'elo' => 1610],
    ['codes' => ['HAI', 'HTI', 'HT'],  'fifa' => 1350, 'elo' => 1540],
    ['codes' => ['CUW', 'CW'],         'fifa' => 1350, 'elo' => 1500],
    ['codes' => ['CMR', 'CM'],         'fifa' => 1455, 'elo' => 1685],
    ['codes' => ['CRC', 'CRI', 'CR'],  'fifa' => 1465, 'elo' => 1685],
    ['codes' => ['SVK', 'SK'],         'fifa' => 1480, 'elo' => 1715],
    ['codes' => ['SWE', 'SE'],         'fifa' => 1510, 'elo' => 1740],
    ['codes' => ['ISL', 'IS'],         'fifa' => 1465, 'elo' => 1680],
    ['codes' => ['CZE', 'CZ'],         'fifa' => 1495, 'elo' => 1720],
    ['codes' => ['ROU', 'ROM', 'RO'],  'fifa' => 1490, 'elo' => 1700],
    ['codes' => ['GRE', 'GRC', 'GR'],  'fifa' => 1490, 'elo' => 1710],
];
