Brassica – Die Broccoli WebApp

Brassica ist eine freie WebApp zur Verwaltung, Bearbeitung und dem Austausch von Rezepten im .broccoli-Format der Broccoli-Handy-App. Die WebApp ermöglicht es, Rezepte bequem im Browser zu pflegen, Sammlungen zu erstellen und Inhalte wieder als .broccoli-Dateien zu exportieren.

Die Software wird unter der GNU GPL v3 veröffentlicht. Damit bleibt garantiert, dass alle Weiterentwicklungen ebenfalls Open Source bleiben.

Funktionen

• Import von Rezepten im .broccoli-Format
• Import kompletter Rezeptsammlungen
• Bearbeiten aller Rezeptdaten im Browser
• Export von angepassten Rezepten als .broccoli
• Anlegen und Teilen von Sammlungen über öffentliche Links
• Mehrbenutzer-Login-System
• Upload und Verwaltung von Rezeptbildern
• Kompatibel mit der offiziellen Broccoli-Handy-App

Kompatibilität mit der Broccoli-App

Brassica verwendet dieselbe Datenstruktur wie die Broccoli-App.

Du kannst:

• Rezepte exportieren → in Brassica importieren → bearbeiten → wieder exportieren
• neue Rezepte im Browser erstellen → in der App öffnen
• Sammlungen erstellen und teilen

Damit bleiben Rezepte flexibel zwischen Mobilgerät und Browser nutzbar.

Voraussetzungen zum Self-Hosting

• Webserver (Apache, Nginx oder Standard-Webspace)
• PHP 8.x
• PHP-Erweiterungen: pdo_sqlite oder sqlite3, zip bzw. ZipArchive
• Schreibrechte für:
– data/ (SQLite-Datenbank)
– uploads/ (Bilder)

Backups bestehen aus der Datei data/db.sqlite und dem Ordner uploads/.

Installation

Repository klonen:
git clone https://github.com/DEIN-GITHUB-NAME/brassica.git

Dateien auf Server/Webspace hochladen.

Schreibrechte setzen:
data/
uploads/

Browser öffnen → Admin-Passwort setzen → fertig.

Projektstruktur

public/ Öffentlicher Webroot
api/ API-Endpunkte und DB-Handling
views/ Seiten und UI-Templates
data/ SQLite-Datenbank
uploads/ Rezeptbilder
assets/ Icons, Styles, Logos

Lizenz

Dieses Projekt steht unter der GNU General Public License Version 3 (GPLv3).
Der vollständige Lizenztext befindet sich in der Datei LICENSE.

Wichtig:

• Jede Weiterentwicklung muss ebenfalls unter GPLv3 veröffentlicht werden.
• Proprietäre oder geschlossene Forks sind nicht erlaubt.
• Die Software wird ohne Garantie bereitgestellt.

Beiträge

Beiträge sind willkommen. Details stehen in der Datei CONTRIBUTING.md.

Brassica – freie Software für freie Rezepte.
