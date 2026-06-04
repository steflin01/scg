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

## Lokale Vorschau

In einem Terminal im Ordner `scg` kannst du einen einfachen HTTP-Server starten:

```bash
python3 -m http.server 8000
```

Dann im Browser `http://localhost:8000` öffnen.
