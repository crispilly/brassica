# Broccoli-Dateiformate

Brassica unterstützt die etablierten Broccoli-Dateiformate:

- `.broccoli` — ZIP-basiertes Format für **ein einzelnes Rezept**; enthält JSON und optional ein Bild.
- `.broccoli-archive` — Archivformat für **mehrere Rezepte**.

Brassica Android kann beide Typen importieren. Beim Export wird ein einzelnes ausgewähltes Rezept als `.broccoli`, eine Mehrfachauswahl als `.broccoli-archive` geschrieben.

Die bestehende Backup-/Restore-Funktion der Android-App bleibt davon getrennt erhalten.
