<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/session_bootstrap_page.php';
$userId = require_login_page();


require_once __DIR__ . '/../api/db.php';

// Parameter lesen
$archiveId = isset($_GET['archive_id']) ? (int)$_GET['archive_id'] : 0;
$filename  = isset($_GET['filename']) ? (string)$_GET['filename'] : '';

if ($archiveId <= 0) {
	http_response_code(400);
	echo 'Ungültige archive_id.';
	exit;
}

$db = get_db();

// Pfad zur gespeicherten Datei holen
$stmt = $db->prepare('SELECT stored_path, original_name FROM archives WHERE id = :id');
$stmt->execute([':id' => $archiveId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
	http_response_code(404);
	echo 'Upload-Eintrag nicht gefunden.';
	exit;
}

$storedPath   = $row['stored_path'];
$originalName = $row['original_name'];

if (!is_file($storedPath)) {
	http_response_code(404);
	echo 'Datei nicht mehr vorhanden.';
	exit;
}

$lowerPath = strtolower($storedPath);

// Hilfsfunktion: Rezept-JSON aus einer .broccoli-ZIP lesen
function read_recipe_from_broccoli(string $broccoliPath): ?array {
	$zip = new ZipArchive();
	if ($zip->open($broccoliPath) !== true) {
		return null;
	}

	$jsonIndex = null;
	for ($i = 0; $i < $zip->numFiles; $i++) {
		$name = $zip->getNameIndex($i);
		if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'json') {
			$jsonIndex = $i;
			break;
		}
	}

	if ($jsonIndex === null) {
		$zip->close();
		return null;
	}

	$jsonRaw = $zip->getFromIndex($jsonIndex);
	$zip->close();

	if ($jsonRaw === false) {
		return null;
	}

	$data = json_decode($jsonRaw, true);
	if (!is_array($data)) {
		return null;
	}

	return $data;
}

$recipe = null;

if (substr($lowerPath, -17) === '.broccoli-archive') {
	// Multi-Archiv: erst .broccoli-Datei extrahieren
	if ($filename === '') {
		http_response_code(400);
		echo 'filename fehlt.';
		exit;
	}

	$zip = new ZipArchive();
	if ($zip->open($storedPath) !== true) {
		http_response_code(500);
		echo 'Archiv kann nicht geöffnet werden.';
		exit;
	}

	$index = $zip->locateName($filename, ZipArchive::FL_NOCASE);
	if ($index === false) {
		$zip->close();
		http_response_code(404);
		echo 'Rezeptdatei im Archiv nicht gefunden.';
		exit;
	}

	$innerData = $zip->getFromIndex($index);
	$zip->close();

	if ($innerData === false) {
		http_response_code(500);
		echo 'Rezeptdatei konnte nicht gelesen werden.';
		exit;
	}

	$tmpInner = sys_get_temp_dir() . '/' . bin2hex(random_bytes(16)) . '.zip';
	file_put_contents($tmpInner, $innerData);

	$recipe = read_recipe_from_broccoli($tmpInner);
} elseif (substr($lowerPath, -9) === '.broccoli') {
	// Einzelne .broccoli-Datei: direkt aus dieser Datei lesen
	$recipe = read_recipe_from_broccoli($storedPath);
} else {
	http_response_code(400);
	echo 'Nicht unterstützter Dateityp.';
	exit;
}

if ($recipe === null) {
	http_response_code(500);
	echo 'Rezeptdaten konnten nicht gelesen werden.';
	exit;
}

// Felder für Anzeige aufbereiten
$title           = (string)($recipe['title'] ?? '(ohne Titel)');
$description     = (string)($recipe['description'] ?? '');
$ingredients     = (string)($recipe['ingredients'] ?? '');
$directions      = (string)($recipe['directions'] ?? '');
$notes           = (string)($recipe['notes'] ?? '');
$nutritionalVals = (string)($recipe['nutritionalValues'] ?? '');
$preparationTime = (string)($recipe['preparationTime'] ?? '');
$servings        = (string)($recipe['servings'] ?? '');
$source          = (string)($recipe['source'] ?? '');
$categoriesStr   = '';

if (isset($recipe['categories']) && is_array($recipe['categories'])) {
	$tmp = [];
	foreach ($recipe['categories'] as $cat) {
		if (is_array($cat) && isset($cat['name']) && trim((string)$cat['name']) !== '') {
			$tmp[] = trim((string)$cat['name']);
		}
	}
	$categoriesStr = implode(', ', $tmp);
}

$hasImage = !empty($recipe['imageName']);

// Bild-URL aus archive_image.php
$imageUrl = null;
if ($hasImage) {
	$imageUrl = '../api/archive_image.php?archive_id=' . $archiveId . '&filename=' . rawurlencode($filename !== '' ? $filename : $originalName);
}

?><!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title>Rezept-Vorschau aus Upload</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
</head>
<body>

<header class="app-header">
	<h1><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
	<nav class="main-nav">
		<a href="index.php">Übersicht</a>
		<a href="archive.php">Archiv-Import</a>
	</nav>
</header>

<main class="app-main view-main">

	<?php if ($imageUrl): ?>
	<section class="view-image">
		<img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="Rezeptbild">
	</section>
	<?php endif; ?>

	<div class="view-actions">
		<button
			type="button"
			id="btn-import-recipe"
			data-archive-id="<?php echo (int)$archiveId; ?>"
			data-filename="<?php echo htmlspecialchars($filename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
		>
			In Datenbank importieren
		</button>
		<a href="archive.php?archive_id=<?php echo (int)$archiveId; ?>">
			<button type="button">Zurück zur Liste</button>
		</a>
	</div>

	<section class="view-meta">
		<?php if ($categoriesStr !== ''): ?>
			<div><strong>Kategorie:</strong> <?php echo htmlspecialchars($categoriesStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
		<?php endif; ?>
		<?php if ($preparationTime !== ''): ?>
			<div><strong>Zeit:</strong> <?php echo htmlspecialchars($preparationTime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
		<?php endif; ?>
		<?php if ($servings !== ''): ?>
			<div><strong>Portionen:</strong> <?php echo htmlspecialchars($servings, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
		<?php endif; ?>
		<div><strong>Quelle Datei:</strong> <?php echo htmlspecialchars($originalName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
	</section>

	<?php if ($description !== ''): ?>
	<section class="view-block">
		<h2>Beschreibung</h2>
		<div class="view-content"><?php echo nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
	</section>
	<?php endif; ?>

	<?php if ($ingredients !== ''): ?>
	<section class="view-block">
		<h2>Zutaten</h2>
		<div class="view-content"><?php echo nl2br(htmlspecialchars($ingredients, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
	</section>
	<?php endif; ?>

	<?php if ($directions !== ''): ?>
	<section class="view-block">
		<h2>Zubereitung</h2>
		<div class="view-content"><?php echo nl2br(htmlspecialchars($directions, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
	</section>
	<?php endif; ?>

	<?php if ($notes !== ''): ?>
	<section class="view-block">
		<h2>Notizen</h2>
		<div class="view-content"><?php echo nl2br(htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
	</section>
	<?php endif; ?>

	<?php if ($nutritionalVals !== ''): ?>
	<section class="view-block">
		<h2>Nährwerte</h2>
		<div class="view-content"><?php echo nl2br(htmlspecialchars($nutritionalVals, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
	</section>
	<?php endif; ?>

	<?php if ($source !== ''): ?>
	<section class="view-block">
		<h2>Quelle</h2>
		<div class="view-content"><?php echo nl2br(htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
	</section>
	<?php endif; ?>

	<div class="view-actions">
		<button
			type="button"
			id="btn-import-recipe"
			data-archive-id="<?php echo (int)$archiveId; ?>"
			data-filename="<?php echo htmlspecialchars($filename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
		>
			In Datenbank importieren
		</button>
		<a href="archive.php?archive_id=<?php echo (int)$archiveId; ?>">
			<button type="button">Zurück zur Liste</button>
		</a>
	</div>

</main>
<script src="archive_view.js"></script>
</body>
</html>

