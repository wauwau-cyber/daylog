# daylog

Tages-Tracker für Essen, Gewicht und Training mit KI-Auswertung (Google Gemini).
Stack: PHP 8.3 (ohne Framework), MySQL 8.4, Angular 21, Docker. Frontend und API laufen zusammen in einem Container auf einem Port.

## Installation

Voraussetzung: [Docker Desktop](https://www.docker.com/products/docker-desktop/) installiert und gestartet.

1. Ordner entpacken, z. B. nach `C:\daylog`
2. **Windows:** `start.bat` doppelklicken. **macOS/Linux:** `./start.sh`
3. Der Browser öffnet http://localhost:47080. Das Setup ausfüllen.
4. Für den Day Review unter **Settings** einen kostenlosen Gemini-Key eintragen ([aistudio.google.com/apikey](https://aistudio.google.com/apikey))

Der erste Start baut die App und dauert ein paar Minuten, danach geht es schnell.

**Automatisch beim PC-Start:** In Docker Desktop unter Settings → General „Start Docker Desktop when you sign in“ aktivieren. Die Container starten dann von selbst mit.

**Stoppen:** `stop.bat` bzw. `docker compose down`. Daten bleiben erhalten.
**Alles löschen:** `docker compose down -v`

**Port belegt?** In `.env` `APP_PORT=` ändern (die Datei legt `start.bat` beim ersten Start an), dann `start.bat` erneut.

**Update:** Neue Version über den Ordner kopieren (`.env` behalten), `start.bat` ausführen. Datenbank-Änderungen spielt die App beim Start selbst ein (`backend/migrations/`).

## Für andere bereitstellen

Siehe [GITHUB.md](GITHUB.md): Code auf GitHub, bei jedem Versions-Tag baut GitHub das Image und eine `daylog.zip` mit `start.bat`, `update.bat`, `stop.bat`. Nutzer brauchen nur Docker Desktop; `update.bat` macht vorher automatisch ein Backup. Die App zeigt einen Hinweis, sobald eine neue Version erschienen ist.

## Entwicklung

```powershell
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
cd frontend
npm install
npm start -- --port 47200       # http://localhost:47200, Proxy /api -> :47080
```

Der Dev-Modus mountet `backend/` live in den Container (PHP-Änderungen sofort aktiv), öffnet MySQL auf `localhost:47306` und phpMyAdmin auf http://localhost:47081 (User/Passwort `daylog`). Unter 47080 läuft dann nur die API, das Frontend kommt von `ng serve`.

Neue Tabellen oder Spalten: neue Datei `backend/migrations/00X_name.sql` anlegen. Sie wird beim nächsten Request genau einmal ausgeführt (`schema_migrations`).

## Ziele und Tagesbedarf

Der Sollwert wird per Formel berechnet (`backend/src/Service/Targets.php`), damit gleiche Eingaben immer gleiche Ziele ergeben:

| Baustein | Quelle |
|---|---|
| Grundumsatz (Mifflin-St-Jeor) × 1,2 | PHP |
| Schritte × Gewicht × 0,0005 kcal | PHP |
| Training aus den Sätzen | Gemini (`training_kcal`) |
| Ziel: abnehmen −275/−550, aufbauen +200/+350 | PHP |

Untergrenze ist der Grundumsatz. Protein: 1,8 / 1,6 / 2,0 g pro kg (abnehmen/halten/aufbauen), ab BMI 25 bezogen auf das Gewicht bei BMI 25. Ballaststoffe 30 g.
Abgleich: kcal ±10 % = getroffen, Protein und Ballaststoffe ab 90 % = erreicht.

Ziele sind versioniert (`goals.valid_from`): Eine Änderung gilt ab heute, vergangene Tage behalten ihr damaliges Ziel. Jede Analyse speichert zusätzlich einen Snapshot der Sollwerte in `result_json.targets`.

## Fortschritt bei Übungen

Messwert ist der beste Satz (meiste Wiederholungen am Stück), berechnet in `backend/src/Service/Progress.php`:
- 4 Wochen: bester Satz der letzten 7 Tage gegen die Woche vor 4 Wochen
- Seit Beginn: gegen die erste geloggte Woche
- Unter ±3 % gilt als unverändert
- Verlauf: bester Satz pro Woche, letzte 12 Wochen (Exercises-Seite)

Gemini bekommt diese Werte pro trainierter Übung mitgeschickt. In der Wochenleiste sind Trainingstage türkis markiert.

## Bedienung

- `←` / `→` wechseln den Tag (außer wenn ein Eingabefeld fokussiert ist), Wochenleiste zum Springen
- Essen: Text eingeben, Enter. Ein Eintrag pro Mahlzeit. Beim Tippen (oder Klick ins leere Feld) erscheinen frühere Einträge, häufigste zuerst. Auswahl mit Maus oder `↑`/`↓` + Enter übernimmt auch die Nährwerte, sodass das Essen sofort mitzählt. Gemini schätzt übernommene Werte bei der Analyse nicht neu.
- Gewicht und Schritte: speichern bei Enter oder beim Verlassen des Felds, leeren = löschen
- Training: Übung + Wiederholungen, `+` pro Satz. Reps bleiben stehen, damit der nächste Satz ein Klick ist. `✕` am Satz löscht ihn
- Notes: Tags antippen (z. B. Rest day, Sick) und freie Notiz, speichert beim Verlassen des Felds. Tags verwalten unter Settings. Rest days sind in der Wochenleiste gestrichelt umrandet. Gemini bekommt Tags und Notiz als Kontext.
- Übungen: werden hart gelöscht, wenn unbenutzt; sonst ausgeblendet (Historie bleibt) und wiederherstellbar

## Struktur

```
backend/
  public/index.php            Front-Controller + Routen
  src/Http/                   Request, Response, Router
  src/Controller/             Profile, Day, Exercise, Analysis
  src/Service/DayRepository   Tagesdaten laden/mappen
  src/Service/GeminiClient    REST-Call mit JSON-Schema-Output
backend/migrations/           SQL, wird automatisch eingespielt
Dockerfile                    baut Angular und packt es zur PHP-API
start.bat / start.sh          Start aus dem Quellcode (baut lokal)
distribution/                 Dateien für Nutzer: Compose mit fertigem Image, start/update/stop, LIESMICH
.github/workflows/release.yml Build und Release bei Tag v*.*.*
frontend/src/app/
  core/                       ApiService, Models, Datums-Utils, Profile-Guard
  day/                        DayPage + DayStore (Signals) + Food/Training/Analysis-Panel
  exercises/                  Verwaltung
  setup/                      Erststart / Profil
```

## API

| Methode | Pfad | |
|---|---|---|
| GET/POST | `/api/profile` | Profil lesen / anlegen (schreibt Gewicht für heute) |
| GET | `/api/days/{date}` | Alles zu einem Tag inkl. letzter Analyse |
| PUT | `/api/days/{date}/weight` | `{ weight_kg }`, `null` löscht |
| PUT | `/api/days/{date}/steps` | `{ steps }`, `null` löscht |
| POST / DELETE | `/api/days/{date}/foods`, `/api/foods/{id}` | Essen (`from_food_id` übernimmt Nährwerte) |
| GET | `/api/foods/suggestions?q=&limit=` | Frühere Essen, häufigste zuerst |
| PUT | `/api/days/{date}/tags`, `/api/days/{date}/note` | Tags des Tages (`tag_ids`), Notiz |
| GET/POST/DELETE | `/api/tags[?include=archived]`, `/api/tags/{id}`, `/api/tags/{id}/restore` | Tags verwalten |
| POST / DELETE | `/api/days/{date}/sets`, `/api/sets/{id}` | Sätze |
| GET/POST/DELETE | `/api/exercises[?include=archived]`, `/api/exercises/{id}` | Übungen |
| POST | `/api/exercises/{id}/restore` | Ausgeblendete Übung zurückholen |
| GET | `/api/progress?date=` | Fortschritt aller Übungen zum Datum |
| GET | `/api/calendar?from=&to=` | Tage mit Training (Wochenleiste) |
| GET | `/api/version` | Laufende Version, neueste Release-Version (GitHub, 6 h gecacht) |
| GET/PUT | `/api/settings` | Gemini-Key (nur Hinweis, nie im Klartext zurück) und Modell |
| POST | `/api/days/{date}/analysis` | Gemini-Auswertung |

## Datenmodell

- `food_entries`: Freitext + `calories_kcal, protein_g, carbs_g, sugar_g, fat_g, saturated_fat_g, fiber_g, sodium_mg` (NULL bis zur Analyse), `nutrients_source` (`ai`/`manual`/`copied`)
- `weight_logs`, `step_logs`: ein Wert pro Tag
- `goals`: Ziel, Tempo, Zielgewicht, gültig ab Datum
- `tags` (`system_key` für Sonderfälle wie `rest_day`), `day_tags`, `day_notes`
- `exercises` (Soft-Delete über `archived_at`), `exercise_sets`
- `day_analyses`: jede Auswertung wird behalten — Score, Summary, Tagessummen, komplette Antwort (`result_json`) und gesendete Daten (`input_json`)

## Gemini

- Ein Request pro Analyse. Gemini schätzt die Nährwerte **pro Essenseintrag** (werden in `food_entries` zurückgeschrieben) und bewertet den Tag. Die Summen rechnet PHP selbst, nicht das Modell.
- Antwortformat wird per `responseSchema` erzwungen, siehe `AnalysisController::schema()`.
- Key und Modell unter **Settings** in der App (Tabelle `settings`), alternativ `GEMINI_API_KEY`/`GEMINI_MODEL` in `.env`. `gemini-flash-latest` zeigt immer auf das aktuelle Flash-Modell; feste Versionen werden regelmäßig abgeschaltet.
- Free Tier: begrenzte Requests pro Minute/Tag (HTTP 429 → Meldung in der UI). Google darf Eingaben im Free Tier zur Produktverbesserung nutzen, auch durch menschliche Reviewer.
