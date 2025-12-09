<?php
// public/index.php
require_once __DIR__ . '/session_bootstrap_page.php';
$userId = require_login_page();
?>
<!doctype html>

<html lang="de">
<head>
	<meta charset="utf-8">
	<title>Brassica Rezeptdatenbank</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="app-header">
    	<h1>Brassica Rezeptdatenbank</h1>
    	<nav class="main-nav">
    		<a href="index.php">Übersicht</a>
    		<a href="archive.php">Rezept-Import</a>
    		<a href="editor_new.php">Neues Rezept schreiben</a>
    		<?php if ($userId === 1): ?>
    			<a href="admin_users.php">Benutzerverwaltung</a>
    			<a href="admin_collections.php">Sammlungen verwalten</a>
    		<?php endif; ?>
    		<a href="logout.php" class="logout-btn">Logout</a>
    	</nav>
    </header>
	<main class="app-main">
		<section class="toolbar">
			<div class="toolbar-group">
				<label for="search-input">Suche im Titel:</label>
				<input type="text" id="search-input" placeholder="z.B. Brot, Suppe, Marmelade">
			</div>

			<div class="toolbar-group">
				<label for="category-select">Kategorie:</label>
				<select id="category-select">
					<option value="">Alle Kategorien</option>
				</select>
			</div>

			<div class="toolbar-group">
				<button id="reload-button" type="button">Neu laden</button>
			</div>
		</section>
		<div class="list-tools">
			<button type="button" id="select-all">Alle auswählen</button>
			<button type="button" id="select-none">Auswahl aufheben</button>
			<button type="button" id="delete-selected" disabled>Ausgewählte löschen</button>
			<button type="button" id="share-selected" disabled>Ausgewählte Rezepte teilen</button>
			<button type="button" id="export-selected" disabled>Ausgewählte exportieren</button>
		</div>

		<section id="recipes-container" class="recipes-grid">
			<!-- Karten werden per JS eingefügt -->
		</section>
		<div class="list-tools">
			<button type="button" id="select-all-bottom">Alle auswählen</button>
			<button type="button" id="select-none-bottom">Auswahl aufheben</button>
			<button type="button" id="delete-selected-bottom" disabled>Ausgewählte löschen</button>
			<button type="button" id="share-selected-bottom" disabled>Ausgewählte Rezepte teilen</button>
			<button type="button" id="export-selected-bottom" disabled>Ausgewählte exportieren</button>
		</div>
		<section class="pagination" id="pagination">
			<button type="button" id="page-prev" disabled>&laquo; Zurück</button>
			<span id="page-info">Seite 1 / 1</span>
			<button type="button" id="page-next" disabled>Weiter &raquo;</button>
		</section>
	</main>

	<script src="app.js"></script>
</body>
</html>
