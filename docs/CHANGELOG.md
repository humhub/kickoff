Changelog
=========

1.0.14 (Unreleased)
-------------------
- Fix: The live-match minute badge ran well behind the real match clock (e.g. it showed 64' while the match was at 85') and mislabelled the 51st–65th minute as half-time. The minute reported by the live-data API is now displayed verbatim, instead of being re-derived through a wall-clock/half-time conversion that only the mock data source ever used.
- Enh: The live-match badge now shows "HT" (localised "HZ" / "MT") during the half-time break instead of a frozen minute, by tracking the football-data.org `PAUSED` state as a distinct match status.
- Enh: While a match is live, football-data.org is now polled every minute instead of every two minutes, so the displayed score and match minute lag the live feed by at most ~1 minute.

1.0.13 (June 25, 2026)
----------------------
- Fix: Updating the module could fail with "Undefined constant humhub\modules\kickoff\Events::SETTING_PENDING_FIXTURES_RESYNC" — the 1.0.12 migration referenced a constant from a class that is not always reloaded during the update request. The one-off post-update fixtures re-sync is now triggered from the hourly cron (by comparing the installed module version) instead of from a migration, so updates no longer depend on class reloading.

1.0.12 (June 25, 2026)
----------------------
- Fix: A group-stage game without a matchday number (e.g. a fixture whose stage could not be classified and defaulted to the group stage) no longer collapses the whole group view into one entry per calendar day — numbered matchdays are always bundled by their number, and unnumbered strays are left out until their stage is corrected.
- Enh: Updating the module schedules a one-off full fixtures re-sync on the next hourly cron, so corrected data mappings are re-stamped onto existing games automatically without an admin having to trigger a manual sync.

1.0.11 (June 25, 2026)
----------------------
- Fix: Round-of-32 fixtures (football-data.org stage `LAST_32`, used by the 48-team FWC 2026 format) are no longer mislabelled as group-stage games. The unmapped stage previously fell back to the group stage, and because knockout matches carry no matchday number this flipped the group-stage view from three matchdays to one entry per calendar day.

1.0.10 (June 16, 2026)
----------------------
- Fix: Bundled team flag images no longer break (404) on HumHub develop, where the asset bundle base URL is an unresolved `@web` path alias — the flag URL is now resolved before it is rendered.

1.0.9 (June 16, 2026)
---------------------
- Enh: Competition view auto-scrolls to the last finished match above a live match.
- Enh: "Show all tips" modal reuses the match card header instead of a separate pairing/result line.
- Fix: Team flag badges drop their white background, removing the white box around flags on live match cards.

1.0.8 (June 12, 2026)
---------------------
- Fix: Undrawn knockout fixtures no longer mark the sync as failed, which had blocked point calculation for the whole competition.
- Fix: Finished games are now scored after every sync and during FWC 2026 setup, not only on a fully error-free sync.
- Enh: Sync and tip-save failures are now written to the application log.

1.0.7 (June 9, 2026)
--------------------
- Enh: Banner added to info, rules and leaderboard pages; nav buttons reordered (Competition → Leaderboard → Rules → Info), admin moved to top-right; headings no longer repeat the competition name.
- Enh: Browser tab titles: competition pages show the competition name (or its main-menu label), admin pages "{module name} - Administration".

1.0.6 (June 9, 2026)
---------------------
- Enh: Match card meta row split into date+time / stage / status; team badges enlarged with rectangular corners; probability tooltip scoped to text; "Show all tips" tied to actual tip existence.
- Enh: The "Show all tips" modal now shows each player's profile picture, matching the leaderboard and Top 10.
- Enh: URL-sourced team flag images keep their own aspect ratio and get a hairline frame drawn on the image, separating white-edged flags (Japan, England, France, …) from the card background.
- Enh: Team badges now prefer the bundled Twemoji flag over the data provider's logo (resolution order: flag → logo → initials), for consistent flags across browsers.

1.0.5 (June 7, 2026)
--------------------
- Fix: Top menu competition entries caused an `UnknownMethodException` on HumHub 1.19, where the deprecated `addItem()` method was removed — replaced with `addEntry()`.
- Fix: Competition rules and leaderboard pages rendered unstyled when opened directly (e.g. right-click → open in new tab). The shared front-end styles now load on every Kickoff page via an asset bundle, instead of being inlined only on the competition view and relying on in-app navigation to carry them over.
- Fix: Banner action buttons no longer stay visually "active" after a right-click → open in new tab (use `:focus-visible` instead of `:focus`).
- Fix: Knockout-stage badges on match cards show translated stage names (e.g. "Semi-finals") instead of internal slugs (e.g. `semi`), using the same labels as the matchday dropdown.
- Fix: National flags no longer show as invisible (Chrome on Linux) or as emoji characters — they are now rendered from bundled Twemoji SVGs (twitter/twemoji v14.0.2, CC BY 4.0), which display consistently across all browsers and platforms, including the England, Scotland and Wales tag-sequence flags.
- Enh: Tables across the module now adopt HumHub's standard table-head styling (the bolder heading used elsewhere in the admin UI), for a consistent look.
- Enh: Leaderboards (the dedicated leaderboard page and the competition view's Top 10) now show each player's profile picture next to their name, and the player's tip-history popup shows it in the header — all using HumHub's standard user image.
- Enh: The competition admin view gains a "Teams" page listing all participating teams with their national flag, name and group.
- Enh: Leaderboards now show a count for every scoring tier — a new "Tendency" column alongside Exact and Goal diff — with clearer headings (Total / Exact / Goal diff / Tendency) and explanatory tooltips. Ties are now broken by total points, then exact-score hits, then correct goal differences, then correct tendencies.
- Fix: England no longer displays as "United Kingdom" with the Union Jack — England, Scotland and Wales now use their own flags (Unicode subdivision tag sequences) with their proper names; Kosovo (KVX) resolves to its localized name and flag; Northern Ireland falls back to a neutral initials badge (it has no emoji flag).
- Fix: Team flag/logo images no longer flash at full content width while the page stylesheet is loading (visible in Firefox) — badge images now carry explicit dimensions.
- Fix: Kickoff pages no longer show briefly unstyled when navigated to in-app (Pjax swaps content in before the page's stylesheet has loaded) — the small Kickoff stylesheet now loads with every full page load via a layout addon.

1.0.4 (June 3,2026)
-------------------
- Chore: bump minVersion to 1.18

1.0.2 (June 3,2026)
-------------------
- Enh: Per-competition access control. Each competition is either Public (default — any logged-in member can view and play, exactly as before) or Restricted. Existing competitions and newly created ones default to Public, so upgrading changes nothing until an admin restricts a competition. The competition admin form gains a "Restricted access" toggle and the competition list shows a lock/unlock indicator.
- Enh: For Restricted competitions the three permissions act as access tiers (admins always pass): "View Kickoff Leaderboard" grants read-only access (competitions, leaderboards and other members' tips); "Participate in Kickoff" additionally allows placing and editing match tips and special bets (view-only members see the fixtures and standings, but the tip inputs are hidden); "Manage Kickoff" gates the admin area (previously open to any site-settings manager) and the per-competition "Admin" banner button. The main-menu/top-sidebar entry and the pages of a restricted competition appear only to members allowed to view it.
- Enh: "Manage Kickoff" holders now get a "Kickoff" entry in HumHub's admin menu and the "Administration" entry in the profile dropdown, so they can reach the module's admin area without full site-admin rights (the entry's "Administration" visibility is cached per session, so a newly granted user may need to re-login).

1.0.1 (May 26, 2026)
--------------------
- Enh: Renamed "WM 2026" to "FWC 2026" (Football World Cup 2026) in English and French strings, code comments and documentation. The German translation keeps "WM 2026"/"Fußball-WM 2026". Internal identifiers (`COMPETITION_WM2026` constant, `'wm2026'` slug, `data/wm2026_ratings.php` filename) are unchanged for backwards compatibility.

1.0.0 (May 26, 2026)
--------------------
- Initial release
