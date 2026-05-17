Changelog
=========

1.0.0 (Unreleased)
------------------
- New: Module icon (green soccer-ball) under `resources/module_image.png`
- Enh: MockLargeAdapter ships real WM-2026 nation names and ISO country codes, rendering flag emojis in team badges
- Enh: MockLargeAdapter pairs R32 with half-vs-half ordering so winners and runners-up of different groups face each other
- Fix: Matchday dropdown navigates via direct URL on change instead of a GET form (the GET-form route handling broke selection on some setups)
- New: "Fast forward 1 matchday" admin action for test competitions skips the next day's games ahead and runs scoring
- Enh: MockLargeAdapter spreads the 72 group games across 12 real calendar days for realistic matchday grouping in the UI
- New: MockLargeAdapter generates a WM-2026-sized sandbox (48 teams, 12 groups, 104 games) so admins can preview UI scale
- Fix: MockAdapter generates KO bracket progressively from real group standings instead of pre-baking it from team indices
- Initial release
