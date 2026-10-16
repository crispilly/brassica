# Broccoli Helper (Android)

Kleine Offline-App: Vollständiges Rezept-JSON einfügen, JPG/PNG/WebP-Bild auswählen und direkt an Brassica Android senden.

## Verwendung

1. Komplettes Rezept-JSON einfügen.
2. Bild über den Android-Dateiauswahldialog auswählen.
3. **An Brassica senden** drücken.
4. Im geöffneten Brassica-Rezepteditor den Import kontrollieren und speichern.

Das JSON-Feld \`imageName\` erhält den Bilddateinamen. Die App erzeugt ein ZIP mit den Einträgen \`rezept.json\` und der Bilddatei direkt im Stammverzeichnis. Diese ZIP-Datei erhält die Dateiendung \`.broccoli\`, liegt nur im privaten App-Cache und wird durch Androids FileProvider als \`content://\`-URI an das Paket \`de.crispilly.brassica\` übergeben.

Das Projekt ist eigenständig und ändert den Brassica-Code nicht.

## APK

Im Repository unter **Actions → Broccoli Helper APK → jüngster erfolgreicher Build → Artifacts → broccoli-helper-debug-apk** herunterladen. Die APK ist eine automatisch von Android signierte Debug-Version, kein Release-Build.

Build lokal: Java 17, Android SDK Platform 35, Gradle 8.7; \`gradle assembleDebug\`.
