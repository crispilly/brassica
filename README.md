# Brassica

Brassica ist eine Rezeptverwaltung mit Webanwendung und Android-App. Das Repository enthält beide Komponenten als Monorepo.

## Komponenten

- `web/` — Brassica Web **2.0** (PHP + SQLite)
- `android/` — Brassica Android, Fork von Broccoli 1.4.7
- `docs/` — Architektur, Sync und Dateiformate

## Brassica Web 2.0

Wesentliche Funktionen:

- zentraler Front Controller und Routing
- nur `web/public/` als DocumentRoot
- private SQLite-Datenbank und private Laufzeitdaten unter `web/storage/`
- Rezeptverwaltung, Kategorien, Sammlungen, Import/Export
- Multikategorie-Filter mit ODER/UND
- Kochmodus mit seitenweisen Schritten und Screen Wake Lock
- Sync-API v1 für Brassica Android

Installation: [`web/README.md`](web/README.md)

## Brassica Android

Basis: Broccoli 1.4.7, GPLv3.

Zusätzlich zu den vorhandenen Broccoli-Funktionen:

- App-ID `de.crispilly.brassica`
- Multikategorie-Filter mit ODER/UND
- Import von `.broccoli` und `.broccoli-archive`
- Export einzelner oder ausgewählter Rezepte
- robuste Bildbehandlung für JPG/JPEG/PNG/WebP
- Servereinstellungen und Sync-Vorschau
- selektiver bidirektionaler Sync mit Brassica Web

Details: [`android/README.md`](android/README.md)

## Datenschutz / Repository-Inhalt

Produktive Daten gehören nicht ins Repository. Insbesondere werden nicht versioniert:

- SQLite-Datenbanken
- Rezeptbilder aus dem laufenden Betrieb
- Importarchive
- Passwörter oder Zugangsdaten
- lokale Android-Konfigurationen

## Lizenz

Dieses Repository steht unter GPLv3. Der Android-Teil basiert auf Broccoli und behält die entsprechenden Lizenz- und Herkunftshinweise bei. Siehe [`LICENSE`](LICENSE) und [`docs/UPSTREAM.md`](docs/UPSTREAM.md).


## Web-Erstinstallation

Brassica Web 2.0 benötigt keine Shell-Installation. Nach dem Entpacken und Setzen des DocumentRoot auf `web/public/` wird die SQLite-Datenbank beim ersten Browseraufruf automatisch angelegt und `/setup` führt durch das Anlegen des ersten Administrators.

## Upgrade von Brassica 1.x

Für Brassica Web 1.0–1.2 gibt es eine automatische, shellfreie Migration beim ersten Start von V2. Datenbank, Bilder und gespeicherte Importarchive werden aus dem alten `data/`-Ordner in den neuen privaten `storage/`-Bereich kopiert.

Anleitung: [Migration 1.x → 2.x](docs/MIGRATION_V1_TO_V2.md)
