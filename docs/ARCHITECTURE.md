# Architektur

## Web

```text
Browser
  ↓
web/public/index.php
  ↓
Router
  ↓
Controller / Legacy-Adapter
  ↓
Services
  ↓
PDO / SQLite + privater Storage
```

Nur `web/public/` ist öffentlich. Datenbank, PHP-Klassen, Sprachressourcen, Bilder und Importarchive liegen außerhalb des DocumentRoot.

Die bestehende Fachlogik wurde beim V2-Refactoring zunächst über interne Legacy-Handler weiterverwendet. Dadurch konnte Routing/Storage modernisiert werden, ohne gleichzeitig das Datenmodell umzubauen.

## Android

```text
UI
  ↓
ViewModel / Repository
  ↓
Room
  ↕
Sync Service
  ↕ HTTPS
Brassica Web Sync API
```

Die App bleibt offline-first. Sync arbeitet über UUIDs und Inhalts-Hashes statt interner Datenbank-IDs.
