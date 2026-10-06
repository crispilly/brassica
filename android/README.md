# Brassica Android

Brassica Android ist ein Fork von **Broccoli 1.4.7** und steht unter GPLv3.

## Kennung

```text
applicationId: de.crispilly.brassica
```

Die Java-/Kotlin-Package-Struktur des Upstream-Projekts wurde bewusst nicht vollständig umbenannt, um einen unnötig großen mechanischen Fork zu vermeiden.

## Zusätzliche Funktionen

- Multikategorie-Filter mit ODER/UND.
- Import von `.broccoli` und `.broccoli-archive` direkt aus der App.
- Bildimport für `.jpg`, `.jpeg`, `.png` und `.webp`.
- Ein fehlendes referenziertes Bild verhindert den Rezeptimport nicht.
- Fehlende Kategorien werden beim Dateiimport angelegt.
- Export eines Rezeptes als `.broccoli`.
- Export mehrerer ausgewählter Rezepte als `.broccoli-archive`.
- Datensicherung/Wiederherstellung bleibt als separate Funktion erhalten.
- Brassica-Web-Sync mit Serverdaten, Vorschau, Einzel- und Kategorieauswahl sowie expliziter Konfliktrichtung.

## Offline-First

Die lokale Room-Datenbank bleibt Hauptdatenbank der App. Der Webserver ist eine optionale Synchronisationsquelle und keine Voraussetzung für die lokale Nutzung.

## Build

Voraussetzungen:

- JDK 17
- Android SDK / compileSdk 37
- Gradle gemäß `gradle/wrapper/gradle-wrapper.properties`

Debug-APK:

```bash
./gradlew assembleFdroidDebug
```

Ergebnis:

```text
app/build/outputs/apk/fdroid/debug/app-fdroid-debug.apk
```

GitHub Actions baut dieselbe Variante über `.github/workflows/android.yml`.

## Upstream

Siehe [`../docs/UPSTREAM.md`](../docs/UPSTREAM.md).
