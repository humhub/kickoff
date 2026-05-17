Changelog
=========

1.0.0 (Unreleased)
------------------
- New: `humhub-api` data source — zero-config adapter that fetches normalized competition data (teams, fixtures, results, ratings, default special-bet templates) from api.humhub.com. No upstream API key required.
- New: One-click "Set up WM 2026" button on the admin index — creates the FIFA World Cup 2026 competition and syncs teams, fixtures, ratings and default special bets in a single action. Idempotent: re-clicking after a partial setup tops up missing metadata.
- Fix: Save failures in `applyMetadata` (default special-bet creation) were swallowed silently. Errors now surface in the flash message and the PHP error log.
- Enh: Pretty URLs for front-end competition pages — slug now lives in the path (`/kickoff/c/wm2026`) instead of as a `?slug=` query param. Sub-pages (info, rules, leaderboard, match tips, user history) follow the same shape.
- Removed: `PointsAwarded` notification. Hourly scoring during live windows fired too often to feel like a digest. Only `TipDeadlineReminder` remains, targeting participants (users with ≥1 tip) only.
- Enh: Tidied the admin competition view. Header keeps only "View as user" + "Back to list". The actions row is now Edit · Bonus bets · More ▾ (Load schedule / Sync results / Recompute points / Apply default ratings, plus Fast forward on test competitions). Delete moved out of the view entirely — it now lives only inside the Edit page's danger zone.
- Enh: Admin index hides test competitions by default; a "Show test competitions ({n})" link at the bottom switches to a separate test-sandbox view.
- Enh: Admin settings drop the HumHub Data Service fields (Base URL override, Local fixture path). The base URL is now a Module property (`apiBaseUrl`, default `https://api.humhub.com`) overridable through HumHub's module config — the path-based local-fixture override is gone for good.
- Enh: Module renamed to "Kickoff - Prediction Game" in `module.json` and docs for a cleaner display in HumHub Admin / Marketplace.
- New: `migrations/uninstall.php` drops every Kickoff table on module uninstall so the schema doesn't leak.
- Docs: README and Admin Guide updated to lead with the one-click WM 2026 setup and credit football-data.org for the free `humhub-api` feed (WM 2026 only).
- Tests: Migrated the five existing standalone unit tests (PointCalculator, WinProbabilityCalculator, GroupStandings, FootballDataMatchParser, TeamNameLocalizer) into the conventional `tests/codeception/unit/` layout so the upstream `module-coding-standards` Codeception workflows can pick them up. Removed the ad-hoc `tests/run.php` runner.
- New: Per-minute live sync via `CronController::EVENT_BEFORE_ACTION`. Adapters expose `getLiveSyncIntervalMinutes()`; mock-large and football-data poll every 2 min while a game is in its live window.
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
