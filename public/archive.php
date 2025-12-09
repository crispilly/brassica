<?php
require_once __DIR__ . '/session_bootstrap_page.php';
$userId = require_login_page();

?><!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title>Rezept-Import – Broccoli</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="app-header">
	<h1>Rezept-Import</h1>
	<nav class="main-nav">
		<a href="index.php">Übersicht</a>
		<a href="editor_new.php">Neues Rezept schreiben</a>
		<?php if ($userId === 1): ?>
			<a href="admin_users.php">Benutzerverwaltung</a>
			<a href="admin_collections.php">Sammlungen verwalten</a>
		<?php endif; ?>
		<a href="logout.php" class="logout-btn">Logout</a>
	</nav>
</header>

<main class="app-main">

	<section class="import-box">
		<h2>.broccoli-archive oder .broccoli hochladen</h2>
		<input type="file" id="archive-file" accept=".broccoli,.broccoli-archive">
		<button id="upload-btn">Upload & Analysieren</button>
	</section>

<section id="archive-results" class="hidden">
	<h2>Gefundene Rezepte</h2>

	<div id="archive-meta"></div>

	<div class="archive-tools">
		<button id="select-all-btn-top" type="button">Alle auswählen</button>
		<button id="select-none-btn-top" type="button">Alle abwählen</button>
 		<button id="import-selected-top" disabled>Ausgewählte importieren</button>
	</div>
 	<div class="import-actions">
 	</div>
	<div id="recipes-list" class="archive-list"></div>

	<div class="archive-tools">
		<button id="select-all-btn" type="button">Alle auswählen</button>
		<button id="select-none-btn" type="button">Alle abwählen</button>
		<button id="import-selected" disabled>Ausgewählte importieren</button>
	</div>
</section>

</main>

<script src="archive.js"></script>
</body>
</html>
