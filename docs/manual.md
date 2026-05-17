Admin Guide
===========

This guide covers installation, setup, and day-to-day operation of the
Kickoff module. It is aimed at HumHub administrators running a tippspiel for
the FIFA World Cup 2026 (or another competition).

Table of contents
-----------------

- [Installation](#installation)
- [One-time setup](#one-time-setup)
- [Creating a competition](#creating-a-competition)
- [Loading the schedule and syncing results](#loading-the-schedule-and-syncing-results)
- [Special bets](#special-bets)
- [Win probabilities](#win-probabilities)
- [Scoring and tip submission](#scoring-and-tip-submission)
- [Tip visibility and menu integration](#tip-visibility-and-menu-integration)
- [Pre-tournament testing](#pre-tournament-testing)
- [Day-to-day operation](#day-to-day-operation)
- [Running the tests](#running-the-tests)

Installation
------------

1. Place the module directory at `protected/modules/kickoff` in your HumHub
   instance (Git clone or Marketplace install).
2. HumHub Admin → **Modules** → enable Kickoff.
3. Run the database migrations — either via the HumHub modules UI or via CLI:
   `php protected/yii migrate --migrationPath=@humhub/modules/kickoff/migrations`
4. Grant permissions (HumHub Admin → Permissions):
   - **Kickoff: Admin** — create/edit competitions, sync data, recompute points.
   - **Kickoff: Participate** — submit match tips and special bets.
   - **Kickoff: View** — see the leaderboard and other participants' tips
     once kickoff has passed.

One-time setup
--------------

If you want real WM 2026 data, you need a free API token from football-data.org.

1. Sign up at [football-data.org](https://www.football-data.org/client/register)
   and copy your personal API token.
2. HumHub Admin → **Kickoff** → **Settings**.
3. Paste the token and save. The token stays server-side and is never sent to
   the browser.

For test runs the bundled mock adapter is enough; no token required.

Creating a competition
----------------------

HumHub Admin → **Kickoff** → **New competition**.

- **Name** — free-form, e.g. "FIFA World Cup 2026".
- **URL slug** — appears in the competition URL; lowercase + dashes.
- **Type** — tournament or league.
- **Season** — optional free-text label (e.g. "2026").
- **Data source** — `football-data.org` for real data, `mock-large` for 48
  fictional teams in WM-2026 shape, `mock` for a small test bracket.
- **Knockout scoring** — whether knockout tips are scored against the result
  after 90 minutes or including extra time.

After saving you land on the competition detail page — that is where every
follow-up action happens.

Loading the schedule and syncing results
----------------------------------------

The detail page exposes three core actions:

- **Load schedule** — fetches teams and fixtures from the adapter. For
  football-data, you pick the matching API competition on the first run
  (typically `WM` / `WC` for the World Cup). The mock adapter generates a
  full schedule in one click.
- **Sync results** — refreshes results, live status, and final scores for the
  already-loaded games. During normal operation it also runs **automatically**
  via HumHub's cron hooks (hourly + per-minute while games are live).
- **Recompute points** — re-runs scoring across every existing tip and resolved
  special bet. Useful after a manual correction or a change to the scoring
  scheme.

For football-data imports the adapter automatically writes each match's group
label back to the teams (`kickoff_competition_team.group_label`), so group-
winner special bets work without extra steps.

Special bets
------------

Competition detail → **Special bets**. Two types are supported:

- **Tournament winner** — one per competition. Resolves automatically after
  the final (including extra time and penalty shootouts).
- **Group winner** — managed as a set: when you create one, the system
  automatically generates one bet per group (with each group's first kickoff
  as the tip deadline). Editing points propagates to every group-winner bet;
  deleting one deletes them all. This avoids partial states like "11 of 12
  groups have a winner bet".

The **Auto-resolve** button tries to resolve every still-open special bet
based on the current state (group winners once all group games are finished,
tournament winner once the final is decided). Auto-resolve also runs in cron.

Manual **Resolve** is shown only for special bet types that cannot be
auto-resolved — currently there are none, but the hook stays in place for
future types.

Win probabilities
-----------------

To help inexperienced participants, Kickoff shows three percentages under
every match that hasn't kicked off yet — e.g. `62% · 22% · 16%`. They are
derived from each team's FIFA points and Elo rating (standard Elo expectancy
formula, with a draw share that shrinks as the strength gap widens). These
are explicitly **not** betting odds and not labelled as such — they are an
orientation hint and therefore not subject to bookmaker-data license issues.

To populate ratings:

1. Competition detail → **Apply default ratings**. This writes FIFA points
   and Elo values from a bundled WM-2026 snapshot to every team whose
   `country_code` matches the snapshot (ISO-2, ISO-3, and FIFA codes are all
   accepted).
2. If some teams aren't in the snapshot, a second flash message lists the
   un-matched country codes. Either fill those teams in manually (DB columns
   `kickoff_team.fifa_points` and `elo_rating`) or extend
   `data/wm2026_ratings.php`.
3. If only one of the two values is set, that value is used; if both are
   set, the service averages them.

Existing values are preserved when you click the button, so manual
corrections survive subsequent imports.

Scoring and tip submission
--------------------------

Each competition has its own scoring scheme (the "Scoring scheme" tab in the
competition editor):

- **Exact** — exact final score predicted.
- **Diff** — same goal difference, not exact.
- **Tendency** — correct tendency (winner side) but different goal difference.
- Otherwise: 0 points.

Tips are **saved automatically** as soon as their value changes (small
jQuery debounce). There is no submit button — the deadline is always the
kickoff of the match in question. After kickoff the tip is locked.

Special bets have their own deadlines, typically the first kickoff of the
respective group or the tournament start.

Tip visibility and menu integration
-----------------------------------

Per competition you can decide whether other participants' tips are visible
**before** kickoff or only after (default: only after, to prevent copying).

The **Show in navigation** flag adds the competition as its own entry to
HumHub's main top menu. As soon as at least one competition is flagged that
way, those specific entries replace the generic "Kickoff" menu item.

Pre-tournament testing
----------------------

Create a test competition (tick **Is test competition** when creating it).
Test competitions expose two extra actions:

- **Fast forward 1 matchday** — pushes the next matchday into the past and
  immediately syncs results. Handy for rehearsing the scoring flow without
  waiting for real matches.
- **Delete test competition** — removes the competition together with every
  tip, special bet, and team.

Real competitions deliberately omit these actions so nobody can wreck a
production tippspiel by accident.

Day-to-day operation
--------------------

Once the competition is live, the module handles most routine work itself:

- **Hourly cron hook** — full sync against the data source.
- **Daily cron hook** — special-bet auto-resolve, plus push notifications to
  participants who haven't tipped for the next day's matches yet.
- **Per-minute hook** — while games are live, results and live minute refresh
  every 60 seconds so the leaderboard keeps pace.

Admin intervention is usually only needed when something goes wrong (manual
result correction, API outage, mid-tournament scoring change). For all of
those, **Sync results** and **Recompute points** are the tools to reach for.

Running the tests
-----------------

The module ships a small suite of dependency-free unit tests that run as
plain PHP scripts — no HumHub bootstrap needed:

```
php tests/run.php
```

Covered:

- `PointCalculator` — match-tip scoring (exact / diff / tendency).
- `WinProbabilityCalculator` — Elo-based win probabilities.
- `GroupStandings` — group-table math driving auto-resolve.
- `FootballDataMatchParser` — football-data JSON parsing.

Exit code 0 means every suite passed, 1 means at least one failed.
