Changelog
=========

1.0.2 (Unreleased)
------------------
- Enh: Gate the main-menu/top-sidebar competition entries and the front-end competition and dashboard pages behind the Kickoff permissions. A user now needs at least one of the "Manage Kickoff", "Participate in Kickoff" or "View Kickoff Leaderboard" permissions (admins always pass) to see or open them.
- Enh: Enforce the three permissions as access tiers. "View Kickoff Leaderboard" grants read-only access to competitions, leaderboards and other users' tips. "Participate in Kickoff" additionally allows placing and editing match tips and special bets — view-only users still see the fixtures and standings, but the tip inputs are hidden. "Manage Kickoff" now gates the admin area (previously open to any site-settings manager) and the per-competition "Admin" banner button.

1.0.1 (May 26, 2026)
--------------------
- Enh: Renamed "WM 2026" to "FWC 2026" (Football World Cup 2026) in English and French strings, code comments and documentation. The German translation keeps "WM 2026"/"Fußball-WM 2026". Internal identifiers (`COMPETITION_WM2026` constant, `'wm2026'` slug, `data/wm2026_ratings.php` filename) are unchanged for backwards compatibility.

1.0.0 (May 26, 2026)
--------------------
- Initial release
