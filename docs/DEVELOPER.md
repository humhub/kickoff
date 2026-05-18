Developer Notes
===============

Material for working on the Kickoff module itself — running the test suite,
exploring the architecture, and adding new adapters or special bet types.

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
- `TeamNameLocalizer` — country-code normalization + Intl-based team-name localization.

Exit code 0 means every suite passed, 1 means at least one failed.

The tests deliberately target pure helpers extracted from the services —
anything that depends on ActiveRecord lives behind a thin wrapper, so the
core logic stays unit-testable.

Architecture overview
---------------------

- `adapters/` — data sources implementing the common `MatchDataAdapter`
  contract. `FootballDataOrgAdapter` is the production source;
  `MockAdapter` / `MockLargeAdapter` provide offline sandboxes.
  `FootballDataMatchParser` holds the pure JSON-to-internal mapping.
- `specialbets/` — `SpecialBetType` implementations (Tournament winner, Group
  winner). New types register themselves with `SpecialBetTypeRegistry`.
- `services/` — pure-logic helpers (`PointCalculator`,
  `WinProbabilityCalculator`, `GroupStandings`) and stateful services
  (`ScoringService`, `SpecialBetResolver`, `WinProbabilityService`).
- `data/wm2026_ratings.php` — bundled world-ranking/Elo snapshot for the
  WM 2026 qualifier pool, keyed by every common country-code variant.
- `controllers/` — `CompetitionController` (user-facing tip flow),
  `AdminController` (competition CRUD, sync actions, special-bet management).

See [CONCEPT.md](CONCEPT.md) for the original design notes.

Adding a new data adapter
-------------------------

1. Implement the `humhub\modules\kickoff\adapters\MatchDataAdapter` interface
   in a new class under `adapters/`. The two operations to fill are
   `syncFixtures()` (one-shot schedule import) and `syncResults()` (idempotent
   result refresh, also called by cron).
2. Register the adapter in `Module::getMatchDataAdapter()` so it's resolvable
   by key from the competition's `data_source` column.
3. Add an option in the competition editor's data-source dropdown so admins
   can pick it.
4. If the adapter talks to an external API, extract any non-trivial parsing
   into a pure helper class (mirror `FootballDataMatchParser`) and add unit
   tests for it.

Adding a new special bet type
-----------------------------

1. Add a constant on `models\SpecialBet` (e.g. `TYPE_TOP_SCORER`) and extend
   the validation range.
2. Implement `humhub\modules\kickoff\specialbets\SpecialBetType` in a new
   class. The four methods to fill are `buildOptions()`,
   `getDefaultQuestion()`, `tryResolve()` (or return `null` if manual-only),
   and `isManualResolveOnly()`.
3. Register the new type in `SpecialBetTypeRegistry::createDefault()`.
4. If the type has its own data shape (extra DB columns, separate UI), add
   a migration and template hooks in `views/admin/special-bet/`.
