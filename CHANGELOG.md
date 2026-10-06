# Changelog

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
