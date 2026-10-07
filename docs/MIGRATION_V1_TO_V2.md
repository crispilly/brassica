# Migration von Brassica 1.x auf Brassica Web 2.x

Die Datenbankstruktur von Brassica 1.0, 1.1 und 1.2 ist mit der für Brassica 2.x verwendeten Struktur kompatibel. Für die Migration werden deshalb keine Tabellen oder Rezeptdaten umgebaut.

Brassica 2.x enthält eine automatische Browser-Migration. Eine Shell ist nicht erforderlich.

## Vorher

In Brassica 1.x liegen die Laufzeitdaten typischerweise hier:

```text
data/
├── db.sqlite
├── images/
└── uploads/
    └── archives/
```

In Brassica 2.x liegen sie außerhalb des öffentlichen Webroots:

```text
storage/
├── database/
│   └── brassica.sqlite
├── images/
└── imports/
    └── archives/
```

## Empfohlener Ablauf

1. Die komplette Brassica-1.x-Installation sichern.
2. Brassica Web 2.x in ein **neues leeres Verzeichnis** entpacken.
3. Aus der alten Installation ausschließlich den kompletten Ordner `data/` in den Root der neuen Webinstallation kopieren, also neben `app/`, `config/`, `database/`, `public/` und `storage/`.
4. Den Webserver-DocumentRoot auf `public/` der neuen Installation setzen.
5. Brassica im Browser öffnen.

Beim ersten Browseraufruf erkennt Brassica automatisch `data/db.sqlite` und führt die Migration aus.

Dabei werden:

- `data/db.sqlite` nach `storage/database/brassica.sqlite` kopiert,
- Dateien aus `data/images/` nach `storage/images/` kopiert,
- Dateien aus `data/uploads/archives/` nach `storage/imports/archives/` kopiert,
- die SQLite-Datenbank vor und nach dem Kopieren mit `PRAGMA quick_check` geprüft,
- die erwarteten Brassica-Tabellen geprüft,
- ein Protokoll unter `storage/logs/v1-migration.json` angelegt.

Die alten Daten unter `data/` werden **nicht gelöscht**.

## Wichtig

Nicht die komplette Brassica-2.x-Version einfach über eine vorhandene 1.x-Installation kopieren. Alte PHP-Dateien im früheren öffentlichen Verzeichnis könnten sonst liegen bleiben.

Sauber ist:

- neue V2-Installation,
- nur den alten `data/`-Ordner hinein kopieren,
- danach den DocumentRoot auf das neue `public/` setzen.

## Nach erfolgreicher Migration

Wenn Anmeldung, Rezepte, Bilder und Archive geprüft wurden, kann der alte `data/`-Ordner aus der V2-Installation entfernt oder außerhalb des Webspaces archiviert werden.

Die produktiven V2-Daten liegen danach ausschließlich unter `storage/`.

## Fehlerfall

Wenn die Migration abbricht, wird die alte 1.x-Datenbank nicht verändert. Brassica zeigt den Fehler beim Browseraufruf an.

Typische Ursachen:

- `pdo_sqlite` fehlt,
- `storage/` ist nicht beschreibbar,
- `data/db.sqlite` ist beschädigt,
- eine Datei mit gleichem Namen existiert im neuen Storage bereits mit anderem Inhalt.

In diesem Fall zuerst die Sicherung behalten und den gemeldeten Fehler beheben.
