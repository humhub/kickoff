Changelog
=========

1.0.0 (Unreleased)
------------------
- New: Live match indicator on match cards — pulsing red badge with current minute (`45+3'`, `HT`, `67'`, `90+5'`, `FT`). MockAdapter rolls scheduled games into LIVE for ~115 min, FootballDataOrgAdapter parses `minute` from the API. Migration `m260519_120000_add_game_current_minute` adds the optional `current_minute` cache column.
- New: `Game.venue` column (migration `m260518_120000_add_game_venue`) with the host city/stadium per match
- Enh: MockLargeAdapter anchors its schedule to the real FIFA WM 2026 calendar (Jun 11 – Jul 19) and assigns the 16 real host venues to games
- Enh: Adapters expose `getEstimatedStageDate()`; placeholder dropdown entries now show "Final · ~ Sun, 19 Jul" instead of just "TBD" when the adapter knows the canonical date
- New: Module icon (green soccer-ball) under `resources/module_image.png`
- Enh: MockLargeAdapter ships real WM-2026 nation names and ISO country codes, rendering flag emojis in team badges
- Enh: MockLargeAdapter pairs R32 with half-vs-half ordering so winners and runners-up of different groups face each other
- Fix: Matchday dropdown navigates via direct URL on change instead of a GET form (the GET-form route handling broke selection on some setups)
- New: "Fast forward 1 matchday" admin action for test competitions skips the next day's games ahead and runs scoring
- Enh: MockLargeAdapter spreads the 72 group games across 12 real calendar days for realistic matchday grouping in the UI
- New: MockLargeAdapter generates a WM-2026-sized sandbox (48 teams, 12 groups, 104 games) so admins can preview UI scale
- Fix: MockAdapter generates KO bracket progressively from real group standings instead of pre-baking it from team indices
- Initial release
