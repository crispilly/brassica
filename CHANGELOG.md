# Changelog

## 2.0.1 — 2026-10-07

### Web
- Automatische, shellfreie Migration von Brassica 1.0–1.2 beim ersten Browserstart.
- Die alte `data/db.sqlite` wird vor der Übernahme mit SQLite `PRAGMA quick_check` geprüft.
- Rezeptbilder und gespeicherte Importarchive werden in die neue private `storage/`-Struktur kopiert.
- Quelldaten aus V1 werden bei der Migration nicht gelöscht.
- Migrationsprotokoll unter `storage/logs/v1-migration.json`.
- Ausführliche Migrationsanleitung ergänzt.

### Projekt / Website
- Infotexte auf Brassica Web 2.x aktualisiert.
- Brassica Android 2.x mit Funktionen und Downloads ergänzt.
- Deutsche und englische Informationsseite als versionierte Quelle unter `website/` aufgenommen.

## 2.0.0 — 2026-10-05

### Web
- Refactoring auf zentralen Front Controller und Router.
- `public/` ist der einzige vorgesehene DocumentRoot.
- SQLite und Laufzeitdaten liegen außerhalb des öffentlichen Verzeichnisses.
- Bestehendes DB-Schema unverändert übernommen.
- Multikategorie-Filter mit ODER (Standard) und UND.
- Kochmodus mit großen seitenweisen Schritten und Wake Lock.
- Sync-API v1 für Brassica Android.
- Browser-Erstinstallation: Datenbank wird beim ersten Aufruf automatisch erzeugt; der erste Benutzer wird über `/setup` angelegt.

### Android
- Fork von Broccoli 1.4.7 als Brassica.
- `applicationId` auf `de.crispilly.brassica` geändert.
- Multikategorie-Filter ODER/UND.
- Import von `.broccoli` und `.broccoli-archive` aus der App.
- Bildimport für `.jpg`, `.jpeg`, `.png`, `.webp`; fehlende Bilder blockieren den Rezeptimport nicht.
- Export eines Rezeptes als `.broccoli` und mehrerer Rezepte als `.broccoli-archive`.
- Bestehende Backup-/Restore-Funktion bleibt erhalten.
- Brassica-Web-Sync mit Vorschau und Einzel-/Kategorieauswahl.
