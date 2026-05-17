# Kickoff

A football prediction game (Tippspiel) for HumHub. Built initially for the
FIFA World Cup 2026 with a pluggable architecture that also covers other
tournaments, national leagues, and internal company brackets.

Members predict match results and place bonus bets (tournament winner, group
winners) and compete on a shared leaderboard. Match data is pulled from
configurable adapters (football-data.org, sandbox mock).

## Key Features

- **Auto-saved tips:** Predictions are stored as participants type — no submit
  button, deadline is each match's kickoff.
- **Live scoring:** Live status, current minute, and final scores refresh
  automatically via cron hooks; the leaderboard keeps pace match by match.
- **Special bets:** Tournament winner and per-group winners with automatic
  resolution from finished matches. Group-winner bets are managed as a set.
- **Win probabilities:** Each upcoming match shows percentage hints derived
  from FIFA points and Elo ratings — a friendly orientation for newcomers,
  not betting odds.
- **Configurable scoring:** Per-competition scheme for exact / goal-difference
  / tendency points, plus separate points for each special bet.
- **Multi-source data:** Real schedules and live results via football-data.org,
  or a built-in mock for offline testing.
- **HumHub integration:** Top-menu entries per competition, push
  notifications for missing tips, permissions for admin / participate / view.

See the [admin guide](MANUAL.md) for setup and day-to-day use.
