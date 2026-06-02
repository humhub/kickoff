Changelog
=========

1.0.5 (Unreleased)
------------------
- Fix: Competition rules and leaderboard pages rendered unstyled when opened directly (e.g. right-click → open in new tab). The shared front-end styles now load on every Kickoff page via an asset bundle, instead of being inlined only on the competition view and relying on in-app navigation to carry them over.
- Fix: Banner action buttons no longer stay visually "active" after a right-click → open in new tab (use `:focus-visible` instead of `:focus`).

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
