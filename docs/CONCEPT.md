# Kickoff — HumHub Tippspiel Module

Concept and design notes. Living document — updated as decisions are made.

## 1. Goals & Scope

Kickoff is a global HumHub module that lets all logged-in members of an
instance participate in a shared football prediction game ("Tippspiel"). The
initial driver is the FIFA World Cup 2026, but the architecture is built to
support arbitrary competitions (other tournaments, national leagues, internal
company tournaments).

**Primary user stories**

- As a member, I want to predict match results before kickoff and see my
  position in the overall leaderboard.
- As a member, I want to place a few high-value bonus bets at the start of a
  tournament (winner, top scorer, group winners).
- As an admin, I want to set up a competition, choose a data source, and trust
  that fixtures and results sync automatically.
- As an admin, I want to dry-run the full lifecycle in a sandboxed "test
  competition" before opening the real one.

**Out of scope (MVP)**

- Money/real-stakes betting. Points only.
- Live match commentary or chat per match.
- Mini-leagues / private groups inside a competition (planned for a later
  phase).
- Per-space tippspiel containers (module is global only).
- Activity stream integration (notifications only).

## 2. Module Structure

- **Module ID**: `kickoff`
- **Repo**: `humhub-modules/kickoff` (master / develop branches per convention)
- **Module class**: `humhub\modules\kickoff\Module` extending
  `humhub\components\Module` — global module, not a `ContentContainerModule`
- **License**: AGPL-3.0-or-later
- **Min HumHub version**: `1.17`
- **Min PHP version**: `8.1`
- **Translations**: `de` and `en` shipped from day one, message files under
  `messages/<locale>/`
- **Routing**:
  - `/kickoff` — dashboard
  - `/kickoff/competition/<slug>` — competition home
  - `/kickoff/competition/<slug>/matchday/<n>` — tip entry view
  - `/kickoff/competition/<slug>/leaderboard`
  - `/kickoff/competition/<slug>/match/<id>`
  - `/kickoff/competition/<slug>/special-bets`
  - `/kickoff/admin/...` — admin UI mounted into HumHub admin

## 3. Permissions

Three module-level permissions:

| Permission              | Default group | Purpose                                    |
| ----------------------- | ------------- | ------------------------------------------ |
| `ManageKickoff`         | Admins        | CRUD on competitions, manual fixture/result edits, scoring recompute, sync log access |
| `Participate`           | All users     | Place tips, view own data                  |
| `ViewLeaderboard`       | All users     | View overall leaderboard and others' tips after kickoff |

A "guest leaderboard visible" toggle per competition is a nice-to-have for
Phase 2 (e.g. clubs that want to expose results publicly).

## 4. Data Model

Naming note: PHP 8 reserves `match` as a keyword. Use `Game` for the model and
`kickoff_game` for the table.

```
Competition
  id (PK)
  name                  string
  slug                  string, unique
  type                  enum(tournament|league)
  season                string, free form (e.g. "2026")
  starts_at, ends_at    datetime
  status                enum(draft|active|finished|archived)
  is_test               bool       — true for sandbox competitions
  scoring_scheme_id     FK
  ko_scoring_mode       enum(regular_time|full_time)   — see §6
  data_source           string     — adapter key ('football-data', 'openliga', 'manual', 'mock')
  data_source_config    json       — e.g. { "external_competition_id": 2000 }
  last_synced_at        datetime, nullable
  created_at, updated_at, created_by

Team
  id (PK)
  name, short_name      string
  country_code          string, nullable
  logo_url              string, nullable
  external_ids          json       — { "football-data": 759, "openliga": "GER", ... }

CompetitionTeam        — join table
  competition_id, team_id, group_label (nullable, e.g. "A")

Game                    — match fixture (avoiding PHP reserved word)
  id (PK)
  competition_id        FK
  home_team_id, away_team_id   FK
  kickoff_at            datetime
  stage                 string     — 'group', 'round_of_32', 'round_of_16', 'quarter', 'semi', 'third_place', 'final'
  round_label           string, nullable    — e.g. "Matchday 3"
  status                enum(scheduled|live|finished|postponed|cancelled)
  home_score, away_score              int, nullable    — after 90 min
  home_score_et, away_score_et        int, nullable    — after extra time
  home_score_pen, away_score_pen      int, nullable    — penalty shootout
  external_id           string, nullable
  last_synced_at        datetime, nullable

ScoringScheme
  id (PK)
  name                  string
  points_exact          int, default 4
  points_goal_diff      int, default 3
  points_tendency       int, default 2
  special_bet_rules     json       — per-type point values

Participation           — opt-in per competition
  competition_id, user_id  (composite PK)
  joined_at             datetime
  display_name          string, nullable     — vanity name override

Tip
  id (PK)
  game_id, user_id      FK   (unique together)
  home_score, away_score   int
  submitted_at          datetime
  locked                bool       — true once kickoff_at passed
  points                int, nullable        — null until scored

SpecialBet
  id (PK)
  competition_id        FK
  type                  string     — 'winner', 'top_scorer', 'group_winner'
  question              string     — i18n key or free text
  options               json, nullable   — for select-type bets (e.g. team list)
  group_label           string, nullable — for per-group bets
  points                int
  deadline_at           datetime
  resolved_value        string, nullable
  resolved_at           datetime, nullable

SpecialBetTip
  id (PK)
  special_bet_id, user_id   FK (unique together)
  value                 string
  points                int, nullable
```

**Invariants**

- A `Tip` is read-only once `Game.kickoff_at <= now`. `locked` is set by a
  cron job as a defense in depth — the controller also enforces it.
- A `SpecialBetTip` is read-only once `SpecialBet.deadline_at <= now`.
- A user can only see other users' tips for a game after that game's kickoff.
- `Tip.points` is recomputed idempotently — re-running the scorer must yield
  the same value.
- All user-owned records (`Participation`, `Tip`, `SpecialBetTip`) cascade on
  `User` delete via `ON DELETE CASCADE` foreign keys. A deleted user
  disappears from leaderboards and history.

**WM 2026 format note**

The 2026 World Cup uses a new format that the data model and adapter logic
must handle:

- 48 teams in 12 groups of 4
- Group stage (72 games) → Round of 32 (16) → R16 (8) → QF (4) → SF (2) →
  Third-place playoff + Final (2) = **104 games total**
- The `GroupWinnerBet` therefore expands to 12 separate bets per competition
- `stage = 'round_of_32'` only applies from WM 2026 onward; older tournaments
  still skip straight from group stage to R16

## 5. Data Source Adapters

Pluggable adapter pattern, one implementation per source. API keys are stored
**globally per adapter** in module settings, not per competition.

```php
interface CompetitionDataAdapter
{
    public function getKey(): string;          // 'football-data', 'openliga', ...
    public function getLabel(): string;        // for admin UI
    public function isConfigured(): bool;      // are required API keys set?
    public function listAvailable(): array;    // competitions this source offers
    public function syncFixtures(Competition $c): SyncReport;
    public function syncResults(Competition $c): SyncReport;
    public function supportsLive(): bool;
}
```

MVP implementations:

- **`FootballDataOrgAdapter`** — primary source for WM 2026. Free tier, 10
  req/min, REST. Stores `X-Auth-Token` in module settings.
- **`ManualAdapter`** — admin edits fixtures and results in the admin UI. No
  external dependency. Fallback if APIs go dark, and the basis for internal
  competitions.
- **`MockAdapter`** — generates a *small* sandbox tournament (8 teams in 2
  groups of 4) with compressed time (configurable, e.g. "1 real-day = 5
  minutes"). Random results once mock kickoff passes. See §10.
- **`MockLargeAdapter`** — same engine as `MockAdapter` but at WM 2026 scale:
  48 teams in 12 groups of 4, with R32 → R16 → QF → SF → Final/3rd. 104 games
  total. Lets admins gauge how the UI feels at tournament size before opening
  the real WM. Bracket seeding is simplified (sequential pairings instead of
  the actual FIFA bracket).

Phase 2:

- **`OpenLigaDbAdapter`** — for Bundesliga and other German leagues, no API
  key needed.

**Sync behavior**

- `SyncReport` contains created/updated/skipped counts plus any errors, persisted
  in a `kickoff_sync_log` table for the admin UI.
- Adapters must be **idempotent**: re-syncing must not duplicate games or teams.
  Matching uses `external_id` first, then a `(competition, kickoff_at,
  home_team, away_team)` tuple as fallback.
- Adapter switch mid-competition: allowed but with a warning. Existing
  `external_id`s become orphaned (kept for history; new syncs use new IDs).

## 6. Scoring

Implemented as a `ScoringService` keyed off a competition. Triggered after every
results import and runnable on demand from the admin UI.

**Per-game tip scoring**

The relevant score is determined by `Competition.ko_scoring_mode`:

- `regular_time` (default for cup competitions): always use `home_score` /
  `away_score` (after 90 min) regardless of stage. Aligns with German
  Tippspiel convention; allows draw tips in K.-o. rounds.
- `full_time`: for K.-o. games, use the final score including extra time and
  penalties. For group stage, use regular time.

Points are awarded per the competition's `ScoringScheme`:

1. Exact score → `points_exact`
2. Correct goal difference (non-zero, non-exact) → `points_goal_diff`
3. Correct tendency only → `points_tendency`
4. Otherwise → 0

**Missed tips**: 0 points. No penalty.

**Late joiners**: a user can opt into a competition at any time, but can only
place tips on games whose kickoff is still in the future. Past games count as
0.

**Tiebreaker for leaderboard** (in order):

1. Total points
2. Number of exact-score hits
3. Number of correct-difference hits
4. Earlier `Participation.joined_at`

## 7. Special Bets

MVP ships three hardcoded types as strategies under
`humhub\modules\kickoff\specialbets\`:

- `WinnerBet` — pick one of the competition's teams. Deadline: tournament start.
- `TopScorerBet` — free text (validated against player list if adapter supplies
  one, otherwise plain string). Deadline: tournament start.
- `GroupWinnerBet` — one bet per group, pick a team from that group. Deadline:
  first game of the group.

Each type implements:

```php
interface SpecialBetType
{
    public function getKey(): string;
    public function getDefaultPoints(): int;
    public function buildOptions(Competition $c): array;   // for select bets
    public function validateValue(string $value): bool;
    public function resolve(Competition $c): ?string;       // auto-resolve attempt
}
```

The `resolve()` method is optional — admin can always override manually.
Architecture is open for additional types in later phases (e.g. `FinalistBet`,
`MostYellowCardsBet`) without schema changes.

Free-form admin-defined bonus questions are **deferred to Phase 2+** per
decision in concept review.

## 8. UI / Views

All views built on HumHub Bootstrap, no bespoke frontend stack.

- **Dashboard** (`/kickoff`): cards for active competition(s), countdown to next
  deadline, own points, miniature leaderboard, "X tips missing" CTA.
- **Matchday view**: list of games up to the next deadline, inline number
  inputs with autosave. Visual lock state for past kickoffs.
- **Leaderboard**: sortable table; toggle to expand per-matchday detail.
- **Game detail**: teams, score, own tip, all participants' tips (visible only
  after kickoff), points breakdown.
- **Special bets**: separate page per competition with all open and resolved
  bets.
- **Admin**:
  - Competitions index (active / test / archived)
  - Competition editor (name, slug, scoring scheme, KO scoring mode, data
    source, adapter config)
  - Manual fixture/result editor (for `ManualAdapter` competitions)
  - Sync log viewer
  - "Recompute points" action
  - Module settings: API keys per adapter, default scoring scheme, notification
    defaults

Topmenu entry appears once any non-archived competition exists.

## 9. Notifications

No activity stream integration. Notifications via HumHub's notification system
only, with user-configurable channels (web / email / mobile).

MVP notifications:

- **`DeadlineReminder`** — 24h before a matchday's first kickoff, sent to users
  who have not yet submitted all their tips for that matchday.
- **`SpecialBetDeadlineReminder`** — 24h before a special bet deadline.
- **`PointsAwarded`** — after results sync, summarises points earned in that
  batch (one digest per sync, not per game, to avoid noise).

Each notification is opt-out per user via standard HumHub settings.

## 10. Test Mode

Sandbox competitions for admins to validate the full lifecycle before opening
a real competition (e.g. the WM).

- `Competition.is_test = true`, `data_source = 'mock'`
- `MockAdapter` generates a parameterised tournament with **time compression**
  configurable in adapter config (default 1 real-day = 5 real-minutes). Real
  `kickoff_at` timestamps are written, just densely packed.
- Mock results are produced pseudo-randomly when a game's mock kickoff passes,
  via the same `SyncResultsJob` path used in production.
- The bracket is built **progressively**, not pre-baked: `syncFixtures` only
  creates the group stage. Once all group games are finished, the next
  `syncResults` derives the semi-final pairings from the real group standings
  (points → goal difference → goals for). Once both semi-finals are finished,
  the next `syncResults` creates the final and third-place playoff from the
  actual semi winners/losers. This way the "Group winner" special bet works
  as intended in the sandbox, and users don't see future KO matchups before
  they're decided.
- Test competitions are visibly badged "TEST" in the UI and excluded from the
  default landing dashboard's primary leaderboard.
- Notifications fire normally during test (intentional — to exercise the
  delivery path).
- Admins can delete a test competition with all its tips at any time. No
  delete option for non-test competitions; they are archived instead.

## 11. Background Jobs

All jobs go through HumHub's queue (`humhub\modules\queue`). Triggered by
HumHub's cron unless noted.

| Job                      | Schedule                                  | Notes |
| ------------------------ | ----------------------------------------- | ----- |
| `SyncFixturesJob`        | daily                                     | one per active competition |
| `SyncResultsJob`         | every 5 min during competition windows, otherwise hourly | independent of fixture sync |
| `RecalculatePointsJob`   | triggered after `SyncResultsJob`          | idempotent |
| `SendDeadlineRemindersJob` | hourly                                  | dedupes per user/matchday |
| `LockExpiredTipsJob`     | every minute                              | belt-and-braces; controller already enforces |
| `ResolveSpecialBetsJob`  | daily after competition end               | attempts `resolve()`; admin can override |

Sync windows ("during competition") are derived from `Competition.starts_at /
ends_at` of any non-archived, non-test competition.

## 12. Open Questions

Resolved during concept review:

- [x] Integration scope: **global module**
- [x] MVP tip modes: classic (exact / diff / tendency) + special bets
- [x] Multi-competition support: yes, with test competition from day one
- [x] Social: notifications only, no stream
- [x] Special bets flexibility: 3 fixed types in MVP, extensible architecture
- [x] K.-o. scoring: configurable per competition
- [x] API keys: global per adapter
- [x] HumHub min version: 1.17 (PHP 8.1+)
- [x] User deletion: `ON DELETE CASCADE` on all user-owned records

Still open / to revisit before / during implementation:

- [ ] Exact football-data.org competition ID for WM 2026 (resolve once API
      publishes the 2026 fixtures)
- [x] Test-competition fast-forward: implemented as an admin action that
      advances the next pending matchday by setting kickoff_at to the past and
      running results sync + scoring.
- [ ] Display name override on `Participation` — needed for MVP or YAGNI?
- [ ] Visibility of others' tips before kickoff to **admins** for support
      purposes — allow with audit log?
- [ ] Tiebreaker behavior when even `joined_at` is identical (cosmetic but
      worth a tie-by-`user_id` fallback)
- [ ] Top scorer special bet: validate against player list (requires
      player-roster sync from adapter) or accept any string?
- [ ] Phase 2 mini-leagues design: new `League` entity vs. tagged
      `Participation`?

## 13. Roadmap

**Phase 1 — MVP, deliverable before WM 2026 kickoff (2026-06-11)**

1. Module skeleton (`module.json`, `Module.php`, permissions, routing,
   translations setup)
2. Migrations for the data model
3. `ScoringScheme` seed (classic 4/3/2)
4. `ManualAdapter` + `MockAdapter` — enables full feature development without
   external dependencies
5. Tip entry + scoring + leaderboard
6. Three special bet types
7. Test mode end-to-end runnable
8. Deadline + points-awarded notifications

**Phase 2 — before WM 2026**

9. `FootballDataOrgAdapter` production-ready (rate limit handling, retry,
   sync log)
10. Admin UI polish and i18n pass
11. Internal beta with real users on a test competition
12. Release `1.0.0` to marketplace

**Phase 3 — after WM 2026**

13. `OpenLigaDbAdapter` for Bundesliga and other German leagues
14. Mini-leagues / private groups within a competition
15. Admin-defined free-form bonus questions
16. Stats and history view (own hit rate over time)
17. Optional: per-space mode if demand emerges
