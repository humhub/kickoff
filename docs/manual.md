Benutzerhandbuch
================

Dieses Handbuch beschreibt die Installation, Einrichtung und den laufenden
Betrieb des Kickoff-Moduls. Es richtet sich an HumHub-Administrator:innen, die
ein Tippspiel für die FIFA WM 2026 (oder einen anderen Wettbewerb) aufsetzen
und betreuen.

Inhaltsverzeichnis
------------------

- [Installation](#installation)
- [Einmalige Einrichtung](#einmalige-einrichtung)
- [Wettbewerb anlegen](#wettbewerb-anlegen)
- [Spielplan laden und synchronisieren](#spielplan-laden-und-synchronisieren)
- [Sonderwetten verwalten](#sonderwetten-verwalten)
- [Quoten / Stärkeprognosen](#quoten--stärkeprognosen)
- [Punktwertung und Tipp-Annahme](#punktwertung-und-tipp-annahme)
- [Sichtbarkeit der Tipps und Menü-Integration](#sichtbarkeit-der-tipps-und-menü-integration)
- [Tests vor dem Turnier](#tests-vor-dem-turnier)
- [Laufender Betrieb](#laufender-betrieb)
- [Tests ausführen](#tests-ausführen)

Installation
------------

1. Modul-Verzeichnis nach `protected/modules/kickoff` in deiner HumHub-Instanz
   spielen (Git-Clone oder Marketplace).
2. HumHub-Admin → **Module** → Kickoff aktivieren.
3. Datenbankmigrationen laufen lassen — entweder über die HumHub-Module-UI
   oder per CLI:
   `php protected/yii migrate --migrationPath=@humhub/modules/kickoff/migrations`
4. Zugriffsrechte vergeben (Gruppen unter HumHub-Admin → Berechtigungen):
   - **Kickoff: Admin** — Wettbewerbe anlegen/bearbeiten, Daten synchronisieren.
   - **Kickoff: Participate** — Tipps und Sonderwetten abgeben.
   - **Kickoff: View** — Tabelle und fremde Tipps nach Anpfiff einsehen.

Einmalige Einrichtung
---------------------

Wenn du echte WM-2026-Daten benutzen willst, brauchst du einen API-Token von
football-data.org (kostenfrei nach Anmeldung).

1. Unter [football-data.org](https://www.football-data.org/client/register)
   einen Account anlegen und den persönlichen API-Token kopieren.
2. HumHub-Admin → **Kickoff** → **Einstellungen** öffnen.
3. Token einfügen und speichern. Der Token wird ausschließlich serverseitig
   genutzt und gelangt nicht in den Browser.

Für reine Tests reicht der mitgelieferte Mock-Adapter (siehe unten); dafür
braucht es keinen Token.

Wettbewerb anlegen
------------------

HumHub-Admin → **Kickoff** → **Neuer Wettbewerb**.

- **Name**: frei wählbar, z. B. „FIFA WM 2026".
- **URL-Slug**: erscheint in der Wettbewerbs-URL; Kleinbuchstaben und
  Bindestriche.
- **Typ**: Turnier oder Liga.
- **Saison**: optional, freier Text (z. B. „2026").
- **Datenquelle**: `football-data.org` für echte Daten, `mock-large` für 48
  fiktive Teams nach WM-2026-Schema, `mock` für einen kleinen Test-Bracket.
- **Knockout-Wertung**: bestimmt ob KO-Tipps gegen das Ergebnis nach 90 Min
  oder inkl. Verlängerung gewertet werden.

Nach dem Speichern landest du in der Wettbewerbs-Detailansicht — alle weiteren
Aktionen passieren dort.

Spielplan laden und synchronisieren
-----------------------------------

In der Wettbewerbs-Detailansicht stehen drei zentrale Aktionen:

- **Load schedule** — holt Teams und Spielplan beim Adapter ab. Bei
  football-data wählst du beim ersten Aufruf den passenden Wettbewerb aus der
  API (für die WM 2026 typischerweise `WM` oder `WC`). Beim Mock-Adapter wird
  ein fertiger Spielplan in einem Schritt erstellt.
- **Sync results** — aktualisiert Ergebnisse, Live-Status und Endstände der
  bereits geladenen Spiele. Läuft im normalen Betrieb außerdem **automatisch**
  per HumHub-Cron-Hook (stündlich + minütlich während laufender Spielzeit).
- **Recompute points** — rechnet alle bereits abgegebenen Tipps und gelösten
  Sonderwetten neu durch. Brauchst du z. B. nach einer manuellen Korrektur
  oder einem geänderten Punktschema.

Bei football-data-Importen schreibt der Adapter das Gruppen-Label aus den
Matches automatisch auf die Teams zurück (`kickoff_competition_team.group_label`),
sodass Gruppen-Sonderwetten ohne weitere Schritte funktionieren.

Sonderwetten verwalten
----------------------

Wettbewerbsdetail → **Special bets**. Zwei Typen werden unterstützt:

- **Tournament winner** — einmal pro Wettbewerb. Auflösung erfolgt nach dem
  Finale automatisch (inkl. Verlängerung und Elfmeterschießen).
- **Group winner** — wird als Set verwaltet: Beim Anlegen erstellt das System
  automatisch eine Wette pro Gruppe (Anpfiff-Datum des ersten Gruppenspiels
  als Tipp-Deadline). Beim Editieren der Punkte werden alle Gruppensieger-
  Wetten synchron aktualisiert. Beim Löschen werden alle Gruppensieger-Wetten
  zusammen gelöscht.

Die Schaltfläche **Auto-resolve** versucht alle offenen Sonderwetten anhand
des aktuellen Spielstands aufzulösen (Gruppensieger sobald alle Gruppenspiele
finished sind, Turniersieger sobald das Finale entschieden ist). Die Auflösung
läuft auch im Cron automatisch.

Manuelles **Resolve** ist nur bei Sonderwetten verfügbar, die nicht automatisch
ermittelt werden können — aktuell gibt es keinen solchen Typ, kommt aber
in Zukunft ggf. zurück.

Quoten / Stärkeprognosen
------------------------

Damit unerfahrene Teilnehmende eine Orientierung haben, zeigt Kickoff dezent
unter jedem noch nicht angepfiffenen Spiel drei Prozentwerte — z. B.
`62 % · 22 % · 16 %`. Sie werden aus FIFA-Punkten und Elo-Ratings der Teams
berechnet (Elo-Standardformel, mit reduziertem Unentschieden-Anteil je nach
Spielstärke-Differenz). Ausdrücklich keine Wettquoten — daher rechtlich
unproblematisch.

So setzt du die Ratings:

1. Wettbewerbsdetail → **Apply default ratings** klicken — schreibt FIFA-
   Punkte und Elo-Werte aus einem mitgelieferten WM-2026-Snapshot in alle
   Teams, deren `country_code` im Snapshot vorkommt (matcht ISO-2, ISO-3 und
   FIFA-Codes).
2. Falls Teams im Snapshot fehlen, listet eine zweite Flash-Meldung die
   nicht-gematchten Codes auf. Trage die fehlenden Werte entweder von Hand
   pro Team ein (DB-Spalten `kickoff_team.fifa_points` und `elo_rating`) oder
   erweitere `data/wm2026_ratings.php`.
3. Wenn nur einer der beiden Werte gesetzt ist, wird damit gerechnet; sind
   beide gesetzt, mittelt der Service sie.

Bereits gesetzte Werte bleiben beim Drücken des Buttons unangetastet — manuelle
Korrekturen überleben einen erneuten Sync.

Punktwertung und Tipp-Annahme
-----------------------------

Pro Wettbewerb gibt es ein Punktschema (siehe Tab „Scoring scheme" in der
Wettbewerbsanlage):

- **Exact** — exaktes Endergebnis getippt.
- **Diff** — gleiche Tordifferenz, aber nicht exakt.
- **Tendency** — richtige Tendenz (Sieger-Seite), aber andere Tordifferenz.
- Sonst: 0 Punkte.

Tipps werden **automatisch** gespeichert, sobald der Wert sich ändert (kleines
Debounce-Delay, jQuery). Es gibt keinen Submit-Knopf — die Frist ist immer der
Anpfiff des jeweiligen Spiels. Nach Anpfiff ist der Tipp gesperrt.

Sonderwetten haben separate Fristen, typischerweise der erste Anpfiff der
betreffenden Gruppe bzw. Turnierstart.

Sichtbarkeit der Tipps und Menü-Integration
-------------------------------------------

Pro Wettbewerb kannst du einstellen, ob fremde Tipps **vor** dem jeweiligen
Anpfiff sichtbar sein sollen oder erst danach (Standard: erst danach,
verhindert Abschreiben).

Mit der Option **Show in navigation** wird der Wettbewerb als eigener Eintrag
ins HumHub-Hauptmenü gehängt. Sobald mindestens ein Wettbewerb so markiert
ist, ersetzt der spezifische Eintrag den generischen „Kickoff"-Menüpunkt.

Tests vor dem Turnier
---------------------

Lege einen Test-Wettbewerb an (Häkchen **Is test competition** beim Erstellen).
Test-Wettbewerbe zeigen zusätzlich diese Aktionen:

- **Fast forward 1 matchday** — schiebt den nächsten Spieltag in die
  Vergangenheit und holt sofort Ergebnisse vom Adapter. Praktisch um den
  Auswertungs-Flow durchzuspielen, ohne auf echte Spiele warten zu müssen.
- **Delete test competition** — löscht den Wettbewerb mit allen Tipps,
  Sonderwetten und Teams.

Echte Wettbewerbe haben diese Aktionen bewusst nicht, damit niemand versehentlich
einen produktiven Spielbetrieb beschädigt.

Laufender Betrieb
-----------------

Sobald der Wettbewerb läuft, übernimmt das Modul die meisten Routine-Aufgaben
selbst:

- **Cron-Hook stündlich** — voller Sync gegen die Datenquelle.
- **Cron-Hook täglich** — Sonderwetten-Auto-Resolve, Push-Benachrichtigungen
  an Tipper:innen mit fehlenden Tipps für Spiele am nächsten Tag.
- **Per-Minute-Hook** — während laufender Spiele (Status `live`) frischt das
  Modul Ergebnisse und Live-Minute alle 60 Sekunden auf, damit die Tabelle
  live mitläuft.

Admin-Aktionen sind in der Regel nur nötig, wenn etwas schiefgeht (manuelle
Ergebniskorrektur, API-Ausfall, Punktschema-Änderung mitten im Turnier). Für
all diese Fälle sind **Sync results** und **Recompute points** die Werkzeuge.

Tests ausführen
---------------

Das Modul bringt eine kleine Suite eigener Unit-Tests mit, die ohne HumHub-
Bootstrap laufen (reine PHP-Skripte):

```
php tests/run.php
```

Abgedeckt sind:

- `PointCalculator` — die Tipp-Punktwertung (exakt / Differenz / Tendenz)
- `WinProbabilityCalculator` — die Elo-basierten Wahrscheinlichkeiten
- `GroupStandings` — Gruppen-Tabellenrechnung für Auto-Resolve
- `FootballDataMatchParser` — Parsing des football-data-JSON

Exit-Code 0 bedeutet alle Suiten grün, 1 mindestens ein Fehlschlag.
