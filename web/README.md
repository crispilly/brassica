# Brassica Web 2.0

Brassica Web ist die selbst gehostete Webanwendung für die Verwaltung von `.broccoli`-Rezepten und `.broccoli-archive`-Sammlungen.

## Installation

Es ist **keine Shell und kein Installationsskript** erforderlich.

1. Verzeichnis entpacken bzw. hochladen.
2. Den Webserver-DocumentRoot auf `web/public/` setzen.
3. Sicherstellen, dass PHP PDO SQLite verfügbar ist und der Webserver in `web/storage/` schreiben darf.
4. Brassica im Browser öffnen.
5. Beim ersten Aufruf erscheint automatisch `/setup`. Dort den ersten Benutzer anlegen.

Beim ersten Browseraufruf legt Brassica automatisch `storage/database/brassica.sqlite` aus `database/schema.sql` an. Der erste Benutzer erhält die ID 1 und ist damit der Administrator der bestehenden Brassica-Rechteverwaltung.

Nach erfolgreicher Einrichtung ist `/setup` automatisch gesperrt und leitet nur noch zum Login weiter. Es gibt keine produktive Datenbank, Zugangsdaten oder Benutzer-Hashes im Repository.

## Anforderungen

- PHP 8.2 oder neuer
- PDO SQLite (`pdo_sqlite`)
- Apache mit `mod_rewrite` oder eine äquivalente Rewrite-Konfiguration
- Schreibrecht für `storage/`

## Öffentlicher Webroot

Nur `public/` gehört ins Web. Insbesondere `app/`, `config/`, `database/` und `storage/` dürfen nicht direkt öffentlich ausgeliefert werden.

## Brassica 2.0

Enthalten sind unter anderem:

- zentraler Front Controller und Routing
- nur notwendige Dateien im öffentlichen Webroot
- Kochmodus mit schrittweiser Großansicht und Screen Wake Lock
- Multikategorie-Filter mit ODER/UND
- `.broccoli`- und `.broccoli-archive`-Import/Export
- Sync-API v1 für Brassica Android
- SQLite-Datenbank außerhalb des öffentlichen Ordners

Die Struktur der ursprünglichen Brassica-Datenbanktabellen wurde beim Refactoring nicht fachlich umgebaut.

## Migration von Brassica 1.x

Brassica 2.x kann eine vorhandene 1.x-Datenbasis beim ersten Browseraufruf automatisch übernehmen. Dafür wird der alte `data/`-Ordner in den Root der neuen V2-Webinstallation kopiert. Die Quelldaten bleiben unverändert erhalten.

Ausführliche Anleitung: [`docs/MIGRATION_V1_TO_V2.md`](../docs/MIGRATION_V1_TO_V2.md)
