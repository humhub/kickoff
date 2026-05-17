Admin Guide
===========

This guide covers setup and day-to-day operation of the Kickoff module. It is
aimed at HumHub administrators running a prediction game for the FIFA World
Cup 2026 (or another competition).

Table of contents
-----------------

- [Permissions](#permissions)
- [Quick setup: FIFA World Cup 2026](#quick-setup-fifa-world-cup-2026)
- [Data sources](#data-sources)
- [Creating a custom competition](#creating-a-custom-competition)
- [Loading the schedule and syncing results](#loading-the-schedule-and-syncing-results)
- [Special bets](#special-bets)
- [Win probabilities](#win-probabilities)
- [Scoring and tip submission](#scoring-and-tip-submission)
- [Tip visibility and menu integration](#tip-visibility-and-menu-integration)
- [Pre-tournament testing](#pre-tournament-testing)
- [Day-to-day operation](#day-to-day-operation)

Permissions
-----------

Once the module is enabled, grant the three Kickoff permissions to the user
groups that should use it (HumHub Admin → Permissions):

- **Kickoff: Admin** — create and edit competitions, sync data, recompute
  points.
- **Kickoff: Participate** — submit match tips and special bets.
- **Kickoff: View** — see the leaderboard and other participants' tips once
  kickoff has passed.

Quick setup: FIFA World Cup 2026
--------------------------------

The fastest way to get started:

1. HumHub Admin → **Kickoff**.
2. Click **Set up WM 2026** in the blue banner at the top.

That single action creates the competition, imports all 48 teams and 104
matches, applies team strength ratings, and adds the default special bets
(tournament winner, 12 group winners). No API key required — all data
comes from the HumHub data service at `api.humhub.com`.

You can re-click the button at any time to top up missing metadata; it's
idempotent and never destroys existing data.

Data sources
------------

Kickoff supports several adapters; the right one is picked when you create
a competition (or auto-selected by the quick setup):

- **HumHub data service** (`humhub-api`) — zero-config feed served from
  `api.humhub.com`. **Exclusively for the FIFA WM 2026.** Pre-aggregated
  and cached at HumHub's end so no token is required on your side. The
  underlying data is graciously provided by
  [football-data.org](https://www.football-data.org/) — many thanks to
  them.
- **football-data.org** — direct upstream feed; needs a free API token
  (HumHub Admin → **Kickoff** → **Settings** → paste token). Use this
  for any competition other than the WM 2026, or if you prefer pulling
  data yourself.
- **Manual** — admin enters teams and games by hand. For internal company
  brackets or competitions without a public data source.
- **Mock / Mock-large** — sandbox adapters for testing; mock-large
  generates a full WM-2026-sized fictional bracket.

Creating a custom competition
-----------------------------

For anything beyond the one-click WM 2026 setup: HumHub Admin →
**Kickoff** → **New competition**.

- **Name** — free-form, e.g. "Bundesliga 2026/27".
- **URL slug** — appears in the competition URL; lowercase + dashes.
- **Type** — tournament or league.
- **Season** — optional free-text label (e.g. "2026").
- **Data source** — see [Data sources](#data-sources) above. Pick
  `humhub-api` and set `external_competition_id` in the data source config
  field to consume from `api.humhub.com`; pick `football-data` to pull
  directly with your own token.
- **Knockout scoring** — whether knockout tips are scored against the result
  after 90 minutes or including extra time.

After saving you land on the competition detail page — that is where every
follow-up action happens.

Loading the schedule and syncing results
----------------------------------------

The competition view's **More ▾** menu exposes:

- **Load schedule** — fetches teams and fixtures from the adapter.
- **Sync results** — refreshes results, live status, and final scores for the
  already-loaded games. During normal operation it also runs **automatically**
  via HumHub's cron hooks (hourly + per-minute while games are live).
- **Recompute points** — re-runs scoring across every existing tip and
  resolved special bet. Useful after a manual correction or a change to the
  scoring scheme.
- **Apply default ratings** — see [Win probabilities](#win-probabilities).

Both `humhub-api` and `football-data` adapters propagate each match's group
label to the teams, so group-winner special bets work without extra steps.

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

For the WM 2026 the quick setup applies ratings automatically. For other
competitions:

1. Competition detail → **More ▾** → **Apply default ratings**. Writes
   FIFA points and Elo values from a bundled snapshot to every team whose
   `country_code` matches (ISO-2, ISO-3, and FIFA codes are all accepted).
2. If some teams aren't in the snapshot, a second flash message lists the
   un-matched country codes. Either fill those teams in manually (DB
   columns `kickoff_team.fifa_points` and `elo_rating`) or extend
   `data/wm2026_ratings.php`.
3. If only one of the two values is set, that value is used; if both are
   set, the service averages them.

Existing values are preserved when you click the action, so manual
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
Test competitions are hidden from the default admin list — click "Show
test competitions (N)" at the bottom of the index to access them.

The competition view's **More ▾** menu picks up an extra action for tests:

- **Fast forward 1 matchday** — pushes the next matchday into the past and
  immediately syncs results. Handy for rehearsing the scoring flow without
  waiting for real matches.

Delete (any competition, test or production) lives in the danger zone at
the bottom of the **Edit** page.

Day-to-day operation
--------------------

Once the competition is live, the module handles most routine work itself:

- **Hourly cron hook** — full sync against the data source.
- **Daily cron hook** — special-bet auto-resolve, plus notifications to
  participants who haven't tipped for the next day's matches yet.
- **Per-minute hook** — while games are live, results and live minute refresh
  every 60 seconds so the leaderboard keeps pace.

Admin intervention is usually only needed when something goes wrong (manual
result correction, API outage, mid-tournament scoring change). For all of
those, **Sync results** and **Recompute points** in the **More ▾** menu are
the tools to reach for.
