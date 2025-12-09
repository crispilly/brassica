<?php
// public/index_kopie.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

if ($token === '') {
    http_response_code(400);
    ?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title>Geteilte Rezept-Sammlung – Fehler</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
</head>
<body>
	<header class="app-header">
		<h1>Geteilte Rezept-Sammlung</h1>
    	<nav class="main-nav">
    		<?php if (!isset($_SESSION['user_id'])): ?>
    			<a href="login.php">Login</a>
    		<?php else: ?>
				<a href="index.php">Eigene Rezepte</a>
				<a href="logout.php">Logout</a>
    		<?php endif; ?>
    	</nav>
	</header>
	<main class="app-main">
		<p>Kein gültiger Sammlungs-Token übergeben.</p>
	</main>
</body>
</html>
<?php
    exit;
}

$db = get_db();
$stmt = $db->prepare('SELECT id FROM collections WHERE token = :token');
$stmt->execute([':token' => $token]);
$collection = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$collection) {
    http_response_code(404);
    ?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title>Geteilte Rezept-Sammlung – Nicht gefunden</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
</head>
<body>
	<header class="app-header">
		<h1>Geteilte Rezept-Sammlung</h1>
    	<nav class="main-nav">
    	    <section>
    		<?php if (!isset($_SESSION['user_id'])): ?>
    			<a href="login.php">Login</a>
    		<?php else: ?>
    		    <a href="index.php">Eigene Rezepte</a>
    			<a href="logout.php">Logout</a>
    		<?php endif; ?>
    	</nav>
	</header>
	<main class="app-main">
		<p>Diese Sammlung wurde nicht gefunden oder wurde gelöscht.</p>
	</main>
</body>
</html>
<?php
    exit;
}

// optional: ID, falls später gebraucht
$collectionId = (int)$collection['id'];
?>
<!doctype html>
<html lang="de">
	<head>
		<meta charset="utf-8">
		<title>Geteilte Rezept-Sammlung</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="styles.css">
	</head>
	<body>
		<header class="app-header">
			<h1>Geteilte Rezept-Sammlung</h1>
    	<nav class="main-nav">
    		<?php if (!isset($_SESSION['user_id'])): ?>
    			<a href="login.php">Login</a>
    		<?php else: ?>
    	    	<a href="index.php">Eigene Rezepte</a>
    			<a href="logout.php">Logout</a>
            <?php endif; ?>
        </nav>
		</header>
    	<main class="app-main" data-token="<?php echo htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    
    		<?php if (isset($_SESSION['user_id'])): ?>
    			<section class="status-banner status-banner-logged-in">
    				<p>
    					Du bist eingeloggt. Du kannst markierte Rezepte übernehmen –
    					sie erscheinen dann in Deinen eigenen Rezepten in der Übersicht.
    				</p>
    			</section>
    		<?php else: ?>
    			<section class="status-banner status-banner-guest">
    				<p>
    					Du bist nicht eingeloggt. Du kannst die Rezepte ansehen und als Broccoli-Dateien herunterladen.
    					Um Rezepte in Deinen Account zu übernehmen, melde Dich bitte zuerst an.
    				</p>
    			</section>
    		<?php endif; ?>
    
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
				<button type="button" id="export-selected" disabled>Ausgewählte exportieren</button>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button type="button" id="import-selected" disabled>Markierte Rezepte übernehmen</button>
                <?php endif; ?>
			 </div>

			<section id="recipes-container" class="recipes-grid">
				<!-- Karten werden per JS eingefügt (Sammlungsansicht) -->
			</section>
                <div class="list-tools">
                	<button type="button" id="select-all-bottom">Alle auswählen</button>
                	<button type="button" id="select-none-bottom">Auswahl aufheben</button>
                	<button type="button" id="export-selected-bottom" disabled>Ausgewählte exportieren</button>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button type="button" id="import-selected-bottom" disabled>Markierte Rezepte übernehmen</button>
                    <?php endif; ?>
                </div>
			<section class="pagination" id="pagination">
				<button type="button" id="page-prev" disabled>&laquo; Zurück</button>
				<span id="page-info">Seite 1 / 1</span>
				<button type="button" id="page-next" disabled>Weiter &raquo;</button>
			</section>
		</main>
	 
		<script src="index_open.js"></script>
	</body>
</html>
