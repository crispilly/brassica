# Brassica – The Broccoli Web App

Brassica is a free web app for managing, editing, and exchanging recipes in the
`.broccoli` format used by the Broccoli mobile app. The web app allows you to
maintain recipes comfortably in the browser, create collections, and export
content again as `.broccoli` files.

The software is released under the **GNU GPL v3**.  
This guarantees that all further development will also remain open source.

---

## Features

- Import recipes in the `.broccoli` format  
- Import complete recipe collections  
- Edit all recipe data directly in the browser  
- Export modified recipes as `.broccoli` files  
- Create and share collections via public links  
- Multi-user login system  
- Upload and manage recipe images  
- Compatible with the official Broccoli mobile app  

---

## Compatibility with the Broccoli App

Brassica uses the same data structure as the Broccoli app.

You can:

- Export recipes → import them into Brassica → edit → export again  
- Create new recipes in the browser → open them in the app  
- Create and share collections  

This keeps recipes flexible and usable across mobile devices and the browser.

---

## Self-Hosting Requirements

- Web server (Apache, Nginx, or standard shared hosting)  
- PHP 8.x  
- PHP extensions:
  - `pdo_sqlite` or `sqlite3`
  - `zip` / `ZipArchive`
- Write permissions for:
  - `data/` (SQLite database)
  - `uploads/` (images)

Backups consist of the file `data/db.sqlite` and the `uploads/` directory.

---

## Installation

Clone the repository:

```
git clone https://github.com/YOUR-GITHUB-NAME/brassica.git
```

Upload the files to your server or web space.

Set write permissions for:

```
data/
uploads/
```

Open the application in your browser → set the admin password → done.

---

## Project Structure

```
public/      public web root  
api/         API endpoints and database handling  
views/       pages and UI templates  
data/        SQLite database  
uploads/     recipe images  
assets/      icons, styles, logos  
```

---

## License

This project is licensed under the **GNU General Public License Version 3 (GPLv3)**.  
The full license text can be found in the `LICENSE` file.

Important:

- Any further development must also be released under GPLv3.  
- Proprietary or closed-source forks are not permitted.  
- The software is provided without any warranty.  

---

## Contributing

Contributions are welcome.  
See `CONTRIBUTING.md` for details.

---

**Brassica – free software for free recipes.**
