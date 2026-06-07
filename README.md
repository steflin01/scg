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
- `gallery.json` - Bildliste für die Galerie auf der Startseite
- `styles.css` - Layout und Design
- `script.js` - Interaktionen und Anzeige der nächsten Mannschaftsspiele
- `scripts/update-fixtures.py` - Importiert die nächsten Spiele aus den DBV-Teamspielplänen

## Logo

Platziere dein Vereinslogo unter `images/logo.png`, damit es im Kopfbereich der Seite angezeigt wird.

## Galerie pflegen

Die Galerie auf der Startseite wird aus `gallery.json` erzeugt. Die Bilddateien liegen unter `images/gallery/`.

Ein Eintrag sieht so aus:

```json
{
  "src": "images/gallery/training-01.jpg",
  "alt": "Badmintontraining in der Sporthalle Gremmendorf",
  "caption": "Training in der Sporthalle Gremmendorf.",
  "enabled": true
}
```

Mit `"enabled": false` kann ein Bild vorübergehend ausgeblendet werden, ohne den Eintrag zu löschen. Wenn keine aktiven Bilder vorhanden sind, wird der gesamte Galerie-Block inklusive Navigationseintrag ausgeblendet.

Bilder sollten vor dem Hochladen webtauglich verkleinert werden, z. B. auf etwa 1600 px Breite als `.jpg` oder `.webp`.

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

Der Workflow `.github/workflows/update-fixtures.yml` aktualisiert `fixtures.json` täglich und lädt die Datei anschließend auf den Webserver hoch. Der automatische Lauf aktualisiert `live`. Beim manuellen Start über `Actions` -> `Update fixtures` -> `Run workflow` kann als Ziel `stage` oder `live` ausgewählt werden.

Die Zugangsdaten liegen in GitHub-Environments:

- `stage` - manuelle Aktualisierung der Stage-Datei
- `live-fixtures` - automatische und manuelle Aktualisierung der Live-Datei, ohne Review-Schutz

Pro Environment müssen diese Secrets gesetzt werden:

- `DEPLOY_HOST` - FTP/SFTP-Server, z. B. `example.org`
- `DEPLOY_USER` - Benutzername
- `DEPLOY_PASSWORD` - Passwort
- `DEPLOY_PATH` - Zielordner auf dem Server, z. B. `/httpdocs/scg`
- `DEPLOY_PROTOCOL` - optional, z. B. `ftp` oder `sftp`; Standard ist `ftp`

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

Für das Environment `live` kann in GitHub eine manuelle Freigabe eingerichtet werden. Dann bleibt der Ablauf einfach, aber ein Live-Deployment braucht vor dem Upload noch eine bewusste Bestätigung.

Hochgeladen werden nur die öffentlichen Dateien wie HTML, CSS, JavaScript, Bilder, `contact.php`, `update-fixtures.php`, `.htaccess` und `fixtures.json`. Token-Dateien, GitHub-Konfiguration, README und Import-Skripte werden nicht deployed.

### Stage-Zugangsschutz

Das Deployment-Ziel `stage` wird per HTTP Basic Auth geschützt. Dafür müssen im GitHub-Environment `stage` zusätzlich diese Secrets gesetzt werden:

- `STAGE_HTPASSWD_LINE` - eine komplette `.htpasswd`-Zeile, z. B. `benutzername:$2y$...`
- `STAGE_HTPASSWD_PATH` - absoluter Server-Dateipfad zur `.htpasswd`, z. B. `/var/www/vhosts/example.org/httpdocs/stage/.htpasswd`

Die `.htpasswd` wird nur beim Stage-Deployment erzeugt und hochgeladen. Live erhält keinen Passwortschutz.

## Offene Ideen

- Bei den Mannschaftskarten zusätzlich zum nächsten Spiel das Ergebnis des letzten gespielten Mannschaftsspiels anzeigen. Dafür sollte der Importer später neben dem nächsten zukünftigen Spiel auch das letzte vergangene Spiel mit vorhandenem Ergebnis aus den DBV-Teamspielplänen speichern. Umsetzung erst angehen, wenn echte Ergebnisdaten in der DBV-Tabelle verfügbar sind, damit Sonderfälle wie leere Ergebnisse, Verlegungen oder kampflose Spiele realistisch getestet werden können.

## Lokale Vorschau

In einem Terminal im Ordner `scg` kannst du einen einfachen HTTP-Server starten:

```bash
python3 -m http.server 8000
```

Dann im Browser `http://localhost:8000` öffnen.
