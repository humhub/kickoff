# Kickoff - Prediction Game

A football prediction game for HumHub. Built initially for the Football
World Cup 2026 with a pluggable architecture that also covers other tournaments,
national leagues, and internal company brackets.

Members predict match results and place bonus bets (tournament winner, group
winners) and compete on a shared leaderboard.

## Key Features

- **One-click WM 2026 setup:** A single button on the admin index creates
  the competition, imports all 48 teams and 104 matches, applies team
  strength ratings, and creates the default special bets (tournament
  winner, all group winners). No API key, no manual configuration.
- **Free HumHub data service — for the WM 2026 only:** Live fixtures
  and results come from `api.humhub.com`, pre-aggregated and cached, so
  no upstream API key is required at your end. The underlying data is
  graciously provided by **[football-data.org](https://www.football-data.org/)** —
  many thanks to them. For any other competition, configure the
  football-data.org adapter (free token) or the manual adapter directly.
- **Auto-saved tips:** Predictions are stored as participants type — no
  submit button, deadline is each match's kickoff.
- **Live scoring:** Live status, current minute, and final scores refresh
  every minute during match windows; the leaderboard keeps pace match by
  match.
- **Special bets:** Tournament winner and per-group winners with automatic
  resolution from finished matches. Group-winner bets are managed as a set.
- **Win probabilities:** Each upcoming match shows percentage hints derived
  from world-ranking points and Elo ratings — a friendly orientation for newcomers,
  not betting odds.
- **Configurable scoring:** Per-competition scheme for exact / goal-difference
  / tendency points, plus separate points for each special bet.
- **HumHub integration:** Top-menu entries per competition, deadline-reminder
  notifications targeted only at participants, permissions for admin /
  participate / view.

See the [admin guide](MANUAL.md) for setup and day-to-day use.
