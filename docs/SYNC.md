# Brassica Sync

## Grundprinzip

Web und Android synchronisieren keine SQLite-Dateien und keine internen numerischen IDs. Ein Rezept wird über eine stabile UUID identifiziert.

Für die Änderungserkennung verwendet Brassica 2.0.2 einen gemeinsamen Sync-Hash aus:

- kanonischem Rezeptinhalt,
- sortierten Kategorien,
- Bildinhalt.

Leere Textfelder werden auf beiden Seiten identisch als leere Strings behandelt.

## Authentifizierung

Die Android-App speichert das Web-Passwort nicht mehr dauerhaft.

Beim ersten Verbinden:

1. Server, Benutzername und Passwort werden eingegeben.
2. Die App authentifiziert sich einmalig mit Benutzername/Passwort bei `POST /api/v1/sync/token`.
3. Der Server erzeugt einen zufälligen Geräte-Key.
4. Die App speichert den Geräte-Key und entfernt ein eventuell aus 2.0.0/2.0.1 gespeichertes Passwort.
5. Alle folgenden Sync-Aufrufe verwenden `Authorization: Bearer <Sync-Key>`.

Der Server speichert nur den SHA-256-Hash des Geräte-Keys.

Für Brassica Android 2.0.0/2.0.1 akzeptiert der Server vorläufig weiterhin HTTP Basic an den Sync-Datenendpunkten. Neue App-Versionen verwenden den Geräte-Key.

## Konflikterkennung

Brassica speichert für jede synchronisierte UUID den zuletzt gemeinsamen Sync-Hash.

Die Vorschau vergleicht drei Werte:

```text
lokaler Stand
Serverstand
zuletzt synchronisierter gemeinsamer Stand
```

Daraus folgt:

- lokal = Server → nichts zu tun
- lokal = letzter Stand, Server geändert → Server → App
- Server = letzter Stand, lokal geändert → App → Server
- beide seit dem letzten Sync geändert → echter Konflikt

Damit wird ein unverändertes Rezept nach einem erfolgreichen Sync nicht mehr pauschal als Konflikt angezeigt.

## Ablauf

1. App lädt das Sync-Manifest vom Webserver.
2. Lokale und entfernte UUID/Sync-Hash-Werte werden verglichen.
3. Die App zeigt eine Vorschau.
4. Benutzer wählt einzelne Rezepte und/oder Kategorien.
5. Die Synchronisation zeigt einen determinierten Fortschrittsbalken und das aktuell verarbeitete Rezept.
6. Nach erfolgreichem Abschluss erscheint eine sichtbare Abschlussmeldung.
7. Anschließend wird die Vorschau neu geladen.

## API

```text
POST /api/v1/sync/token
POST /api/v1/sync/token/revoke

GET  /api/v1/sync/manifest
GET  /api/v1/sync/recipes/{uuid}
POST /api/v1/sync/apply
```

## Sicherheit

Sync enthält Rezeptdaten und Authentifizierungsinformationen. Im Produktivbetrieb muss HTTPS verwendet werden.
