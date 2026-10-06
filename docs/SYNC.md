# Brassica Sync

## Grundprinzip

Web und Android synchronisieren keine SQLite-Dateien und keine internen numerischen IDs. Ein Rezept wird über eine stabile UUID identifiziert. Inhaltsänderungen werden über Hashes erkannt.

## Ablauf

1. App lädt das Sync-Manifest vom Webserver.
2. Lokale und entfernte UUID/Hash-Werte werden verglichen.
3. Die App zeigt eine Vorschau.
4. Benutzer wählt einzelne Rezepte und/oder Kategorien.
5. Neue oder geänderte Datensätze werden in die gewünschte Richtung übertragen.
6. Bei echten Konflikten wird die Richtung explizit gewählt: App → Server oder Server → App.

## API

```text
GET  /api/v1/sync/manifest
GET  /api/v1/sync/recipes/{uuid}
POST /api/v1/sync/apply
```

## Sicherheit

Sync enthält Anmeldedaten und Rezeptdaten. Im Produktivbetrieb muss HTTPS verwendet werden.
