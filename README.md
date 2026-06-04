# SC Gremmendorf Badminton Website

Einfache statische Website für die Badmintonabteilung des SC Gremmendorf.

## Dateien

- `index.html` - Hauptseite mit Trainingszeiten, Teams, aktuellem Kontaktformular und Logo
- `impressum.html` - Impressum mit Beispielinhalten
- `datenschutz.html` - kurze Datenschutzerklärung
- `contact.php` - Serverseitiger Versand des Kontaktformulars
- `update-fixtures.php` - Per URL aufrufbarer, token-geschützter Aktualisierer für `fixtures.json`
- `.update-fixtures-token.example.php` - Beispiel für die lokale Token-Konfiguration
- `fixtures.json` - automatisch erzeugte Datei mit den nächsten Mannschaftsspielen
- `styles.css` - Layout und Design
- `script.js` - Interaktionen und Anzeige der nächsten Mannschaftsspiele
- `scripts/update-fixtures.py` - Importiert die nächsten Spiele aus den DBV-Teamspielplänen

## Logo

Platziere dein Vereinslogo unter `images/logo.png`, damit es im Kopfbereich der Seite angezeigt wird.

## Mannschaftsspiele aktualisieren

Die nächsten Spiele werden aus `fixtures.json` gelesen. Die Datei kann lokal mit folgendem Befehl aktualisiert werden:

```bash
python3 scripts/update-fixtures.py
```

Auf einem Webserver ohne Cronjob kann alternativ `update-fixtures.php` per URL aufgerufen werden. Der Aufruf ist über den Token in `.update-fixtures-token.php` geschützt:

```text
https://example.org/scg/update-fixtures.php?token=DEIN_TOKEN
```

Diese URL kann z. B. einmal täglich über einen externen Cron-Dienst aufgerufen werden.

Die echte Datei `.update-fixtures-token.php` enthält ein Geheimnis und wird nicht in Git versioniert. Für neue Umgebungen kann `.update-fixtures-token.example.php` kopiert und mit einem eigenen Token befüllt werden.

## Automatische Aktualisierung über GitHub Actions

Der Workflow `.github/workflows/update-fixtures.yml` aktualisiert `fixtures.json` täglich und lädt die Datei anschließend auf den Webserver hoch. Dafür müssen im GitHub-Repository unter `Settings` -> `Secrets and variables` -> `Actions` diese Secrets gesetzt werden:

- `DEPLOY_HOST` - FTP/SFTP-Server, z. B. `example.org`
- `DEPLOY_USER` - Benutzername
- `DEPLOY_PASSWORD` - Passwort
- `DEPLOY_PATH` - Zielordner auf dem Server, z. B. `/httpdocs/scg`
- `DEPLOY_PROTOCOL` - optional, z. B. `ftp` oder `sftp`; Standard ist `ftp`

Der Workflow kann in GitHub unter `Actions` auch manuell über `Run workflow` gestartet werden.

## Deployment der Website

Der Workflow `.github/workflows/deploy-site.yml` lädt die öffentlichen Website-Dateien manuell auf den Webserver hoch. Er wird nicht automatisch bei jedem Push gestartet, sondern in GitHub unter `Actions` -> `Deploy site` -> `Run workflow`.

Beim Start wird als Ziel `stage` oder `live` ausgewählt. Die Zugangsdaten liegen in GitHub-Environments mit denselben Namen:

- `stage` - Staging-Ziel
- `live` - Live-Ziel

Pro Environment müssen diese Secrets gesetzt werden:

- `DEPLOY_HOST` - FTP/SFTP-Server, z. B. `example.org`
- `DEPLOY_USER` - Benutzername
- `DEPLOY_PASSWORD` - Passwort
- `DEPLOY_PATH` - Zielordner auf dem Server, z. B. `/httpdocs/scg`
- `DEPLOY_PROTOCOL` - optional, z. B. `ftp` oder `sftp`; Standard ist `ftp`

Optional kann pro Environment die Variable `SITE_URL` gesetzt werden, damit GitHub nach dem Deployment direkt den passenden Link anzeigt.

Für das Environment `live` kann in GitHub eine manuelle Freigabe eingerichtet werden. Dann bleibt der Ablauf einfach, aber ein Live-Deployment braucht vor dem Upload noch eine bewusste Bestätigung.

Hochgeladen werden nur die öffentlichen Dateien wie HTML, CSS, JavaScript, Bilder, `contact.php`, `update-fixtures.php`, `.htaccess` und `fixtures.json`. Token-Dateien, GitHub-Konfiguration, README und Import-Skripte werden nicht deployed.

## Lokale Vorschau

In einem Terminal im Ordner `scg` kannst du einen einfachen HTTP-Server starten:

```bash
python3 -m http.server 8000
```

Dann im Browser `http://localhost:8000` öffnen.
