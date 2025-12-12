<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/session_bootstrap_page.php';
$userId = require_login_page();

require_once __DIR__ . '/../i18n.php';
require_once __DIR__ . '/../api/db.php';

// aktuelle GET-Parameter für Sprachumschalter vorbereiten
$langBaseParams = $_GET;
unset($langBaseParams['lang']);

if (!empty($langBaseParams)) {
	$langQueryPrefix = http_build_query($langBaseParams) . '&';
} else {
	$langQueryPrefix = '';
}

// Parameter lesen
$archiveId = isset($_GET['archive_id']) ? (int)$_GET['archive_id'] : 0;
$filename  = isset($_GET['filename']) ? (string)$_GET['filename'] : '';

if ($archiveId <= 0) {
	http_response_code(400);
	echo t('archive_view.error_invalid_archive_id', 'Ungültige archive_id.');
	exit;
}

$db = get_db();

// Pfad zur gespeicherten Datei holen
$stmt = $db->prepare('SELECT stored_path, original_name FROM archives WHERE id = :id');
$stmt->execute([':id' => $archiveId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
	http_response_code(404);
	echo t('archive_view.error_upload_not_found', 'Upload-Eintrag nicht gefunden.');
	exit;
}

$storedPath   = $row['stored_path'];
$originalName = $row['original_name'];

if (!is_file($storedPath)) {
	http_response_code(404);
	echo t('archive_view.error_file_missing', 'Datei nicht mehr vorhanden.');
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
		echo t('archive_view.error_filename_missing', 'filename fehlt.');
		exit;
	}

	$zip = new ZipArchive();
	if ($zip->open($storedPath) !== true) {
		http_response_code(500);
		echo t('archive_view.error_archive_open', 'Archiv kann nicht geöffnet werden.');
		exit;
	}

	$index = $zip->locateName($filename, ZipArchive::FL_NOCASE);
	if ($index === false) {
		$zip->close();
		http_response_code(404);
		echo t('archive_view.error_recipe_not_found', 'Rezeptdatei im Archiv nicht gefunden.');
		exit;
	}

	$innerData = $zip->getFromIndex($index);
	$zip->close();

	if ($innerData === false) {
		http_response_code(500);
		echo t('archive_view.error_recipe_read', 'Rezeptdatei konnte nicht gelesen werden.');
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
	echo t('archive_view.error_unsupported_type', 'Nicht unterstützter Dateityp.');
	exit;
}

if ($recipe === null) {
	http_response_code(500);
	echo t('archive_view.error_recipe_data', 'Rezeptdaten konnten nicht gelesen werden.');
	exit;
}

// Felder für Anzeige aufbereiten
$titleDefault    = t('archive_view.title_fallback', '(ohne Titel)');
$title           = (string)($recipe['title'] ?? $titleDefault);
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
<html lang="<?php echo htmlspecialchars(current_language(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    <head>
    	<meta charset="utf-8">
    	<title><?php echo htmlspecialchars(t('archive_view.page_title', 'Rezept-Vorschau aus Upload'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
    	<meta name="viewport" content="width=device-width, initial-scale=1">
    	<link rel="stylesheet" href="styles.css">
    	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-256.png" sizes="256x256" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
    </head>
    <body>
        <header class="app-header">
        	<div class="app-header-top">
        		<h1><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
        
        		<?php
        		$availableLanguages = available_languages();
        		if (count($availableLanguages) > 1):
        		?>
        		<div class="language-switch">
        			<?php echo htmlspecialchars(t('auth.language_switch_label', 'Sprache:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			<?php foreach ($availableLanguages as $code): ?>
        				<?php if ($code === current_language()): ?>
        					<strong><?php echo htmlspecialchars(strtoupper($code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
        				<?php else: ?>
        					<a href="?<?php
        						echo htmlspecialchars(
        							$langQueryPrefix . 'lang=' . urlencode($code),
        							ENT_QUOTES | ENT_SUBSTITUTE,
        							'UTF-8'
        						);
        					?>">
        						<?php echo htmlspecialchars(strtoupper($code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        					</a>
        				<?php endif; ?>
        			<?php endforeach; ?>
        		</div>
        		<?php endif; ?>
        	</div>
        
        	<nav class="main-nav">
        		<a href="index.php">
        			<?php echo htmlspecialchars(t('nav.overview', 'Übersicht'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</a>
        		<a href="archive.php?archive_id=<?php echo (int)$archiveId; ?>">
        			<?php echo htmlspecialchars(t('archive_view.nav_archive_link', 'Archiv-Import'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</a>
        	</nav>
        </header>
        
        <main class="app-main view-main">
        
        	<?php if ($imageUrl): ?>
        	<section class="view-image">
        		<img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(t('archive_view.image_alt', 'Rezeptbild'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        	</section>
        	<?php endif; ?>
        
        	<div class="view-actions">
        		<button
        			type="button"
        			id="btn-import-recipe"
        			data-archive-id="<?php echo (int)$archiveId; ?>"
        			data-filename="<?php echo htmlspecialchars($filename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
        		>
        			<?php echo htmlspecialchars(t('archive_view.import_button', 'In Datenbank importieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</button>
        		<a href="archive.php?archive_id=<?php echo (int)$archiveId; ?>">
        			<button type="button">
        				<?php echo htmlspecialchars(t('archive_view.back_button', 'Zurück zur Liste'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
        		</a>
        	</div>
        
        	<section class="view-meta">
        		<?php if ($categoriesStr !== ''): ?>
        			<div>
        				<strong><?php echo htmlspecialchars(t('archive_view.meta_category_label', 'Kategorie:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
        				<?php echo htmlspecialchars($categoriesStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</div>
        		<?php endif; ?>
        		<?php if ($preparationTime !== ''): ?>
        			<div>
        				<strong><?php echo htmlspecialchars(t('archive_view.meta_time_label', 'Zeit:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
        				<?php echo htmlspecialchars($preparationTime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</div>
        		<?php endif; ?>
        		<?php if ($servings !== ''): ?>
        			<div>
        				<strong><?php echo htmlspecialchars(t('archive_view.meta_servings_label', 'Portionen:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
        				<?php echo htmlspecialchars($servings, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</div>
        		<?php endif; ?>
        		<div>
        			<strong><?php echo htmlspecialchars(t('archive_view.meta_source_file_label', 'Quelle Datei:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
        			<?php echo htmlspecialchars($originalName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</div>
        	</section>
        
        	<?php if ($description !== ''): ?>
        	<section class="view-block">
        		<h2><?php echo htmlspecialchars(t('archive_view.section_description', 'Beschreibung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
        		<div class="view-content"><?php echo nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
        	</section>
        	<?php endif; ?>
        
        	<?php if ($ingredients !== ''): ?>
        	<section class="view-block">
        		<h2><?php echo htmlspecialchars(t('archive_view.section_ingredients', 'Zutaten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
        		<div class="view-content"><?php echo nl2br(htmlspecialchars($ingredients, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
        	</section>
        	<?php endif; ?>
        
        	<?php if ($directions !== ''): ?>
        	<section class="view-block">
        		<h2><?php echo htmlspecialchars(t('archive_view.section_directions', 'Zubereitung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
        		<div class="view-content"><?php echo nl2br(htmlspecialchars($directions, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
        	</section>
        	<?php endif; ?>
        
        	<?php if ($notes !== ''): ?>
        	<section class="view-block">
        		<h2><?php echo htmlspecialchars(t('archive_view.section_notes', 'Notizen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
        		<div class="view-content"><?php echo nl2br(htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
        	</section>
        	<?php endif; ?>
        
        	<?php if ($nutritionalVals !== ''): ?>
        	<section class="view-block">
        		<h2><?php echo htmlspecialchars(t('archive_view.section_nutrition', 'Nährwerte'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
        		<div class="view-content"><?php echo nl2br(htmlspecialchars($nutritionalVals, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
        	</section>
        	<?php endif; ?>
        
        	<?php if ($source !== ''): ?>
        	<section class="view-block">
        		<h2><?php echo htmlspecialchars(t('archive_view.section_source', 'Quelle'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
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
        			<?php echo htmlspecialchars(t('archive_view.import_button', 'In Datenbank importieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</button>
        		<a href="archive.php?archive_id=<?php echo (int)$archiveId; ?>">
        			<button type="button">
        				<?php echo htmlspecialchars(t('archive_view.back_button', 'Zurück zur Liste'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
        		</a>
        	</div>
        
        </main>
        <script>
        window.archiveViewMessages = {
        	missing_archive_or_filename: <?php echo json_encode(t('archive_view.missing_archive_or_filename', 'Archiv-ID oder Dateiname fehlt.'), JSON_UNESCAPED_UNICODE); ?>,
        	duplicate_only_message:      <?php echo json_encode(t('archive.duplicate_only_message', 'Rezept existiert bereits. Kein Rezept übernommen.'), JSON_UNESCAPED_UNICODE); ?>,
        	import_done:                 <?php echo json_encode(t('archive.import_done', 'Import abgeschlossen. {count} Rezepte übernommen.'), JSON_UNESCAPED_UNICODE); ?>,
        	import_error:                <?php echo json_encode(t('archive.import_error', 'Fehler beim Importieren.'), JSON_UNESCAPED_UNICODE); ?>
        };
        </script>
        <script src="archive_view.js"></script>
    </body>
</html>
