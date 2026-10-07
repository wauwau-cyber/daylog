# daylog über GitHub veröffentlichen

Bei jedem Versions-Tag (`v1.2.3`) baut GitHub automatisch das Docker-Image (`ghcr.io/<user>/daylog`)
und hängt `daylog.zip` mit Startskripten ans Release. Nutzer laden nur diese ZIP, Updates holen sie mit `update.bat`.
Für öffentliche Repos sind GitHub Actions und die Container Registry kostenlos.

## Einmalig

**1. Repository anlegen:** github.com → New repository → Name `daylog`, **Public**, ohne README/.gitignore.

**2. Code hochladen** (im Projektordner):
```powershell
git init
git add .
git status          # .env darf hier NICHT auftauchen
git commit -m "daylog 1.0.0"
git branch -M main
git remote add origin https://github.com/<user>/daylog.git
git push -u origin main
```

**3. Erstes Release:**
```powershell
git tag v1.0.0
git push origin v1.0.0
```
Unter *Actions* läuft jetzt „Release“. Der erste Lauf dauert etwa 10–15 Minuten (Image für Windows/Intel und Apple-Macs), spätere sind schneller.

**4. Image öffentlich machen** (nur einmal): github.com → dein Profil → *Packages* → `daylog` → *Package settings* → *Change visibility* → **Public**.
Sonst können Nutzer das Image nicht herunterladen.

**5. Teilen:** `https://github.com/<user>/daylog/releases/latest`. Dort liegt `daylog.zip`, Anleitung steht in `LIESMICH.txt`.

## Neue Version veröffentlichen

```powershell
git add .
git commit -m "Was sich geändert hat"
git push
git tag v1.1.0
git push origin v1.1.0
```

Nutzer sehen innerhalb von ein paar Stunden den Hinweis in der App und klicken `update.bat`.
Versionsnummern: Fehlerbehebung `1.0.0 → 1.0.1`, neue Funktion `1.0.0 → 1.1.0`.
Datenbank-Änderungen immer als neue Datei in `backend/migrations/` anlegen, nie bestehende ändern.

## Eigene Installation auf die Release-Version umstellen

Deine bisherige Installation nutzt die Passwörter `daylog`/`root`. Damit die Release-Version deine Daten übernimmt:

1. Im alten Ordner `docker compose down --remove-orphans` (Daten bleiben).
2. `daylog.zip` vom Release entpacken, z. B. nach `C:\daylog`.
3. Dort **vor** dem ersten Start eine Datei `.env` anlegen:
   ```
   APP_PORT=47080
   TZ=Europe/Berlin
   DB_PASSWORD=daylog
   DB_ROOT_PASSWORD=root
   ```
4. `start.bat`. Ab jetzt holst du Updates wie alle anderen mit `update.bat`.

Zum Entwickeln bleibt der Projektordner mit `docker-compose.dev.yml` und `npm start` wie bisher. Beide verwenden dieselben Daten, also nicht gleichzeitig laufen lassen.
