<?php

/**
 * Plausible FIFA-points and Elo-rating snapshot for WM 2026 nations, keyed by
 * 3-letter ISO country code. Values are rough mid-2026 estimates and meant as
 * a starting point — the admin can override individual teams via the team
 * editor. Add more entries as needed; teams without an entry simply won't get
 * a probability shown until ratings are filled manually.
 *
 * @return array<string, array{fifa: int, elo: int}>
 */
return [
    'ARG' => ['fifa' => 1864, 'elo' => 2125],
    'FRA' => ['fifa' => 1857, 'elo' => 2065],
    'BRA' => ['fifa' => 1780, 'elo' => 2080],
    'ESP' => ['fifa' => 1758, 'elo' => 2020],
    'ENG' => ['fifa' => 1730, 'elo' => 2010],
    'POR' => ['fifa' => 1718, 'elo' => 1990],
    'NED' => ['fifa' => 1700, 'elo' => 1970],
    'BEL' => ['fifa' => 1675, 'elo' => 1925],
    'CRO' => ['fifa' => 1690, 'elo' => 1910],
    'ITA' => ['fifa' => 1665, 'elo' => 1940],
    'GER' => ['fifa' => 1645, 'elo' => 1960],
    'MAR' => ['fifa' => 1645, 'elo' => 1820],
    'JPN' => ['fifa' => 1640, 'elo' => 1800],
    'URU' => ['fifa' => 1635, 'elo' => 1875],
    'DEN' => ['fifa' => 1655, 'elo' => 1800],
    'COL' => ['fifa' => 1620, 'elo' => 1810],
    'SUI' => ['fifa' => 1610, 'elo' => 1815],
    'USA' => ['fifa' => 1565, 'elo' => 1745],
    'SEN' => ['fifa' => 1565, 'elo' => 1780],
    'TUR' => ['fifa' => 1565, 'elo' => 1780],
    'IRN' => ['fifa' => 1550, 'elo' => 1735],
    'SRB' => ['fifa' => 1545, 'elo' => 1770],
    'MEX' => ['fifa' => 1530, 'elo' => 1750],
    'KOR' => ['fifa' => 1530, 'elo' => 1755],
    'POL' => ['fifa' => 1530, 'elo' => 1745],
    'CAN' => ['fifa' => 1530, 'elo' => 1715],
    'AUT' => ['fifa' => 1530, 'elo' => 1760],
    'HUN' => ['fifa' => 1520, 'elo' => 1715],
    'AUS' => ['fifa' => 1510, 'elo' => 1715],
    'ALG' => ['fifa' => 1500, 'elo' => 1715],
    'NOR' => ['fifa' => 1495, 'elo' => 1730],
    'WAL' => ['fifa' => 1495, 'elo' => 1720],
    'NGA' => ['fifa' => 1490, 'elo' => 1720],
    'ECU' => ['fifa' => 1480, 'elo' => 1735],
    'SCO' => ['fifa' => 1480, 'elo' => 1735],
    'CIV' => ['fifa' => 1475, 'elo' => 1710],
    'CHI' => ['fifa' => 1470, 'elo' => 1710],
    'EGY' => ['fifa' => 1470, 'elo' => 1680],
    'PER' => ['fifa' => 1465, 'elo' => 1700],
    'TUN' => ['fifa' => 1460, 'elo' => 1670],
    'PAR' => ['fifa' => 1455, 'elo' => 1700],
    'GHA' => ['fifa' => 1450, 'elo' => 1660],
    'SVN' => ['fifa' => 1450, 'elo' => 1680],
    'KSA' => ['fifa' => 1430, 'elo' => 1620],
    'VEN' => ['fifa' => 1420, 'elo' => 1665],
    'QAT' => ['fifa' => 1395, 'elo' => 1620],
    'PAN' => ['fifa' => 1380, 'elo' => 1620],
    'JAM' => ['fifa' => 1300, 'elo' => 1565],
    'NZL' => ['fifa' => 1290, 'elo' => 1490],
];
