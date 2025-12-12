<?php
declare(strict_types=1);

// einfache Fehleranzeige für Entwicklung
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../i18n.php';

// öffentlicher Download: GET + download=1
$isPublicDownload = isset($_GET['download'])
	&& $_GET['download'] === '1'
	&& $_SERVER['REQUEST_METHOD'] === 'GET';

$userId = null;
if (!$isPublicDownload) {
	// für alles außer öffentlichem Download: Login erzwingen
	$userId = require_login_page();
}

require_once __DIR__ . '/../api/db.php';
$db = get_db();

function send_broccoli_download(array $data, string $title, ?string $imagePathRel): void {
	// imageName ggf. aus Pfad setzen
	if ($imagePathRel) {
		$data['imageName'] = basename($imagePathRel);
	}

	$json = json_encode($data, JSON_UNESCAPED_UNICODE);

	$zip = new ZipArchive();
	$tmpFile = tempnam(sys_get_temp_dir(), 'broccoli_');
	if ($tmpFile === false) {
		throw new RuntimeException('Konnte temporäre Datei nicht anlegen.');
	}

	if ($zip->open($tmpFile, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
		throw new RuntimeException('Konnte ZIP-Archiv nicht erstellen.');
	}

	// Dateibasisname aus Titel
	$baseName = preg_replace('/[^\p{L}\p{N}]+/u', '_', $title);
	$baseName = trim((string)$baseName, '_');
	if ($baseName === '') {
		$baseName = 'rezept';
	}

	// JSON in ZIP legen
	$jsonName = $baseName . '.json';
	$zip->addFromString($jsonName, $json);

	// Bild hinzufügen, falls vorhanden
	if ($imagePathRel) {
		$fullPath = realpath(__DIR__ . '/../' . ltrim($imagePathRel, '/'));
		if ($fullPath !== false && is_file($fullPath)) {
			$zip->addFile($fullPath, basename($imagePathRel));
		}
	}

	$zip->close();

	$downloadName = $baseName . '.broccoli';
	$safeName = str_replace(['"', '\\'], ['_', '_'], $downloadName);

	header('Content-Type: application/x-broccoli');
	header(
		'Content-Disposition: attachment; filename="' . $safeName .
		'"; filename*=UTF-8\'\'' . rawurlencode($downloadName)
	);
	header('Content-Length: ' . filesize($tmpFile));

	readfile($tmpFile);
	@unlink($tmpFile);
	exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
}

if ($id <= 0) {
	http_response_code(400);
	echo t('editor.invalid_id', 'Ungültige Rezept-ID.');
	exit;
}

// vorhandenes Rezept laden
if ($isPublicDownload) {
	// öffentlicher Download: ohne owner-Filter
	$stmt = $db->prepare('SELECT * FROM recipes WHERE id = :id');
	$stmt->execute([
		':id' => $id,
	]);
} else {
	// Bearbeiten / Speichern nur für eigenen Datensatz
	$stmt = $db->prepare('SELECT * FROM recipes WHERE id = :id AND owner_id = :owner_id');
	$stmt->execute([
		':id'       => $id,
		':owner_id' => $userId,
	]);
}

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
	http_response_code(404);
	echo t('editor.not_found_or_forbidden', 'Rezept nicht gefunden oder keine Berechtigung.');
	exit;
}

try {
	$currentJson = json_decode((string)$row['json_data'], true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
	$currentJson = [];
}

/**
 * Hilfsfunktion: aktuelle Felder aus JSON bzw. DB für Formular befüllen
 */
function get_field(array $json, array $row, string $jsonKey, string $dbKey = null): string {
	// JSON-Feld bevorzugen
	if (array_key_exists($jsonKey, $json) && $json[$jsonKey] !== null) {
		return (string)$json[$jsonKey];
	}
	if ($dbKey !== null && array_key_exists($dbKey, $row) && $row[$dbKey] !== null) {
		return (string)$row[$dbKey];
	}
	return '';
}

// vorhandene Kategorien für Dropdown laden
$allCategories = [];
try {
	$stmtCats = $db->query('SELECT name FROM categories ORDER BY name');
	$allCategories = $stmtCats->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
	$allCategories = [];
}

// POST: speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$title            = trim((string)($_POST['title'] ?? ''));
	$description      = trim((string)($_POST['description'] ?? ''));
	$ingredients      = trim((string)($_POST['ingredients'] ?? ''));
	$directions       = trim((string)($_POST['directions'] ?? ''));
	$notes            = trim((string)($_POST['notes'] ?? ''));
	$nutritionalVals  = trim((string)($_POST['nutritionalValues'] ?? ''));
	$preparationTime  = trim((string)($_POST['preparationTime'] ?? ''));
	$servings         = trim((string)($_POST['servings'] ?? ''));
	$source           = trim((string)($_POST['source'] ?? ''));
	$categoriesInput  = trim((string)($_POST['categories'] ?? ''));
 	$favorite         = isset($_POST['favorite']) ? 1 : 0;
 	$deleteImage      = isset($_POST['delete_image']) && $_POST['delete_image'] === '1';
 
 	// aktueller Bildpfad aus DB
 	$newImagePath = $row['image_path'] ?? null;
 
 	// Bild löschen, falls gewünscht
 	if ($deleteImage && $newImagePath) {
 		$fullPath = realpath(__DIR__ . '/../' . ltrim($newImagePath, '/'));
 		if ($fullPath !== false && is_file($fullPath)) {
 			@unlink($fullPath);
 		}
 		$newImagePath = null;
 	}

	// Bild-Upload prüfen (optional)
	if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
		if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
			$tmp      = $_FILES['image']['tmp_name'];
			$origName = (string)($_FILES['image']['name'] ?? 'image');
			$ext      = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));

			$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
			if (!in_array($ext, $allowed, true)) {
				$error = t('editor.image_type_error', 'Nur JPG, PNG, WEBP oder GIF sind als Bild erlaubt.');
			} else {
				$imageDir = __DIR__ . '/../data/images';
				if (!is_dir($imageDir)) {
					mkdir($imageDir, 0775, true);
				}

				$newFileName  = bin2hex(random_bytes(16)) . '.' . $ext;
				$targetPathFs = $imageDir . '/' . $newFileName;

				if (!move_uploaded_file($tmp, $targetPathFs)) {
					$error = t('editor.image_save_error', 'Fehler beim Speichern des Bildes.');
				} else {
					$newImagePath = 'data/images/' . $newFileName;
				}
			}
		} else {
			$error = t('editor.image_upload_error', 'Fehler beim Upload des Bildes.');
		}
	}

	if ($title === '') {
		$error = t('editor.title_required', 'Titel darf nicht leer sein.');
	}

	if (!isset($error)) {
		// Kategorien-Array aufbauen (Broccoli-Format: [{name: "..."}])
		$categoriesArr = [];
		if ($categoriesInput !== '') {
			$parts = explode(',', $categoriesInput);
			foreach ($parts as $part) {
				$name = trim($part);
				if ($name !== '') {
					$categoriesArr[] = ['name' => $name];
				}
			}
		}

		// bestehendes JSON als Basis, nur relevante Felder überschreiben
		$data = is_array($currentJson) ? $currentJson : [];

		$data['title']            = $title;
		$data['description']      = $description !== '' ? $description : null;
		$data['ingredients']      = $ingredients !== '' ? $ingredients : null;
		$data['directions']       = $directions !== '' ? $directions : null;
		$data['notes']            = $notes !== '' ? $notes : null;
		$data['nutritionalValues']= $nutritionalVals !== '' ? $nutritionalVals : null;
		$data['preparationTime']  = $preparationTime !== '' ? $preparationTime : null;
		$data['servings']         = $servings !== '' ? $servings : null;
		$data['source']           = $source !== '' ? $source : null;
		$data['categories']       = $categoriesArr;
		$data['favorite']         = (bool)$favorite;

		// Bild-Info im JSON aktualisieren
		if ($newImagePath) {
			$data['imageName'] = basename($newImagePath);
		} else {
			unset($data['imageName']);
		}

		$jsonEncoded = json_encode($data, JSON_UNESCAPED_UNICODE);

		// DB-Update
		$sql = 'UPDATE recipes SET
					title            = :title,
					description      = :description,
					directions       = :directions,
					ingredients      = :ingredients,
					notes            = :notes,
					nutritional_vals = :nutritional_vals,
					preparation_time = :preparation_time,
					servings         = :servings,
					source           = :source,
					favorite         = :favorite,
					image_path       = :image_path,
					json_data        = :json_data,
					updated_at       = :ts
				WHERE id = :id
				  AND owner_id = :owner_id';

		$stmt2 = $db->prepare($sql);
		$stmt2->execute([
			':title'            => $title,
			':description'      => $description !== '' ? $description : null,
			':directions'       => $directions !== '' ? $directions : null,
			':ingredients'      => $ingredients !== '' ? $ingredients : null,
			':notes'            => $notes !== '' ? $notes : null,
			':nutritional_vals' => $nutritionalVals !== '' ? $nutritionalVals : null,
			':preparation_time' => $preparationTime !== '' ? $preparationTime : null,
			':servings'         => $servings !== '' ? $servings : null,
			':source'           => $source !== '' ? $source : null,
			':favorite'         => $favorite,
			':image_path'       => $newImagePath,
			':json_data'        => $jsonEncoded,
			':ts'               => (new DateTimeImmutable())->format('c'),
			':id'               => $id,
			':owner_id'         => $userId,
		]);

 		if ($stmt2->rowCount() === 0) {
 			http_response_code(404);
 			echo t('editor.not_found_or_forbidden', 'Rezept nicht gefunden oder keine Berechtigung.');
 			exit;
 		}

		// Kategorien-Tabelle aktualisieren
		$db->prepare('DELETE FROM recipe_categories WHERE recipe_id = :id')
		   ->execute([':id' => $id]);

		foreach ($categoriesArr as $cat) {
			$name = $cat['name'];
			// Kategorie holen oder anlegen
			$cs = $db->prepare('SELECT id FROM categories WHERE name = :n');
			$cs->execute([':n' => $name]);
			$catId = $cs->fetchColumn();

			if ($catId === false) {
				$db->prepare('INSERT INTO categories (name) VALUES (:n)')
				   ->execute([':n' => $name]);
				$catId = $db->lastInsertId();
			}

			$db->prepare(
				'INSERT OR IGNORE INTO recipe_categories (recipe_id, category_id)
				 VALUES (:rid, :cid)'
			)->execute([
				':rid' => $id,
				':cid' => $catId
			]);
		}

		// nach erfolgreichem Speichern zurück zur Ansicht
		header('Location: view.php?id=' . $id);
		exit;
	}
}

// Für GET oder bei Fehler: Formularwerte aus aktuellem JSON/DB
$title           = get_field($currentJson, $row, 'title', 'title');
$description     = get_field($currentJson, $row, 'description', 'description');
$ingredients     = get_field($currentJson, $row, 'ingredients', 'ingredients');
$directions      = get_field($currentJson, $row, 'directions', 'directions');
$notes           = get_field($currentJson, $row, 'notes', 'notes');
$nutritionalVals = get_field($currentJson, $row, 'nutritionalValues', 'nutritional_vals');
$preparationTime = get_field($currentJson, $row, 'preparationTime', 'preparation_time');
$servings        = get_field($currentJson, $row, 'servings', 'servings');
$source          = get_field($currentJson, $row, 'source', 'source');

// Kategorien aus JSON als kommaseparierte Liste
$categoriesStr = '';
if (isset($currentJson['categories']) && is_array($currentJson['categories'])) {
	$tmp = [];
	foreach ($currentJson['categories'] as $cat) {
		if (is_array($cat) && isset($cat['name']) && trim((string)$cat['name']) !== '') {
			$tmp[] = trim((string)$cat['name']);
		}
	}
	$categoriesStr = implode(', ', $tmp);
}

$favoriteChecked = !empty($currentJson['favorite']) || (!empty($row['favorite']) && $row['favorite'] == 1);

// Bild-Preview aus DB/JSON
$currentImagePath = $row['image_path'] ?? null;
$imagePreviewUrl  = $currentImagePath ? ('../api/image.php?id=' . (int)$id) : null;

// Download als Broccoli-Datei?
if (isset($_GET['download']) && $_GET['download'] === '1') {
	// Kategorien-Array im Broccoli-Format [{name: "..."}]
	$categoriesArr = [];

	if ($categoriesStr !== '') {
		$parts = explode(',', $categoriesStr);
		foreach ($parts as $part) {
			$name = trim((string)$part);
			if ($name !== '') {
				$categoriesArr[] = ['name' => $name];
			}
		}
	} elseif (isset($currentJson['categories']) && is_array($currentJson['categories'])) {
		foreach ($currentJson['categories'] as $cat) {
			if (is_array($cat) && isset($cat['name']) && trim((string)$cat['name']) !== '') {
				$categoriesArr[] = ['name' => trim((string)$cat['name'])];
			}
		}
	}

	// Basis: vorhandenes JSON, Felder aus DB/Feldern überschreiben
	$data = is_array($currentJson) ? $currentJson : [];

	$data['title']             = $title !== '' ? $title : '';
	$data['description']       = $description !== '' ? $description : '';
	$data['ingredients']       = $ingredients !== '' ? $ingredients : '';
	$data['directions']        = $directions !== '' ? $directions : '';
	$data['notes']             = $notes !== '' ? $notes : '';
	$data['nutritionalValues'] = $nutritionalVals !== '' ? $nutritionalVals : '';
	$data['preparationTime']   = $preparationTime !== '' ? $preparationTime : '';
	$data['servings']          = $servings !== '' ? $servings : '';
	$data['source']            = $source !== '' ? $source : '';
	$data['categories']        = $categoriesArr;
	$data['favorite']          = (bool)$favoriteChecked;

	$imagePathRel = $currentImagePath ?: null;
	$downloadTitle = $title !== '' ? $title : t('editor.download_title_fallback', 'Rezept');

	send_broccoli_download($data, $downloadTitle, $imagePathRel);
}

// Sprachumschalter: aktuelle GET-Parameter ohne "lang" als Basis übernehmen
$langBaseParams = $_GET;
unset($langBaseParams['lang']);

if (!empty($langBaseParams)) {
	$langQueryPrefix = http_build_query($langBaseParams) . '&';
} else {
	$langQueryPrefix = '';
}

?><!doctype html>
<html lang="<?php echo htmlspecialchars(current_language(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    <head>
    	<meta charset="utf-8">
    	<title><?php echo htmlspecialchars(t('editor.page_title', 'Rezept bearbeiten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
    	<meta name="viewport" content="width=device-width, initial-scale=1">
    	<link rel="stylesheet" href="styles.css">
    	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-256.png" sizes="256x256" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
    </head>
    <body>
    
    <header class="app-header">
    	<div class="app-header-top">
    		<h1><?php echo htmlspecialchars(t('editor.heading', 'Rezept bearbeiten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
    
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
    		<a href="archive.php">
    			<?php echo htmlspecialchars(t('nav.import', 'Rezept-Import'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    		</a>
    		<a href="editor_new.php">
    			<?php echo htmlspecialchars(t('nav.new_recipe', 'Neues Rezept schreiben'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    		</a>
    		<?php if ($userId === 1): ?>
    			<a href="admin_users.php">
    				<?php echo htmlspecialchars(t('nav.admin_users', 'Benutzerverwaltung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</a>
    			<a href="admin_collections.php">
    				<?php echo htmlspecialchars(t('nav.admin_collections', 'Sammlungen verwalten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</a>
    		<?php endif; ?>
    		<a href="logout.php" class="logout-btn">
    			<?php echo htmlspecialchars(t('nav.logout', 'Logout'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    		</a>
    	</nav>
    </header>
    
    <main class="app-main editor-main">
    	<?php if (isset($error)): ?>
    		<div class="error-message" style="margin-bottom:1rem;"><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    	<?php endif; ?>
    
    	<form method="post" action="editor.php" enctype="multipart/form-data">
    		<div class="editor-actions-top">
            	<a href="<?php echo 'view.php?id=' . $id; ?>">
    				<button type="button">
    					<?php echo htmlspecialchars(t('editor.back_button', 'Zurück'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    				</button>
    			</a>
            	<button type="submit">
    				<?php echo htmlspecialchars(t('editor.save_button', 'Speichern'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    		</div>
    		<input type="hidden" name="id" value="<?php echo (int)$id; ?>">
    
    		<div class="view-block">
    			<h2><?php echo htmlspecialchars(t('editor.section_maindata', 'Stammdaten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    
    			<label>
    				<?php echo htmlspecialchars(t('editor.title_label', 'Titel'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><br>
    				<input type="text" name="title" value="<?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width:100%;">
    			</label>
    			<br><br>
    
    			<label>
    				<?php echo htmlspecialchars(t('editor.categories_label', 'Kategorien (kommagetrennt)'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><br>
    				<input
    					type="text"
    					name="categories"
    					id="categories-input"
    					value="<?php echo htmlspecialchars($categoriesStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
    					style="width:100%;"
    				>
    			</label>
    			<br><br>
    
    			<label>
    				<?php echo htmlspecialchars(t('editor.categories_select_label', 'Kategorie aus vorhandenen wählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><br>
    				<select id="categories-select">
    					<option value=""><?php echo htmlspecialchars(t('editor.categories_select_placeholder', '– Kategorie auswählen –'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
    					<?php foreach ($allCategories as $catName): ?>
    						<option value="<?php echo htmlspecialchars($catName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    							<?php echo htmlspecialchars($catName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    						</option>
    					<?php endforeach; ?>
    				</select>
    				<small><?php echo htmlspecialchars(t('editor.categories_select_hint', 'Ausgewählte Kategorie wird dem Textfeld oben hinzugefügt.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small>
    			</label>
    			<br><br>
    
    			<label>
    				<?php echo htmlspecialchars(t('editor.favorite_label', 'Favorit'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    				<input type="checkbox" name="favorite" <?php echo $favoriteChecked ? 'checked' : ''; ?>>
    			</label>
    		</div>
    
     		<div class="view-block">
     			<h2><?php echo htmlspecialchars(t('editor.section_image', 'Bild'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
     			<?php if ($imagePreviewUrl): ?>
     				<div style="margin-bottom:0.75rem;">
     					<img src="<?php echo htmlspecialchars($imagePreviewUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(t('editor.image_alt', 'Rezeptbild'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="max-width:200px; height:auto; display:block;">
     				</div>
     				<label style="display:block; margin-bottom:0.5rem;">
     					<input type="checkbox" name="delete_image" value="1">
     					<?php echo htmlspecialchars(t('editor.delete_image_label', 'Bild löschen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
     				</label>
     			<?php else: ?>
     				<p><?php echo htmlspecialchars(t('editor.no_image', 'Kein Bild vorhanden.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
     			<?php endif; ?>
     
     			<label>
     				<?php echo htmlspecialchars(t('editor.upload_image_label', 'Neues Bild hochladen (optional)'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><br>
     				<input type="file" name="image" accept="image/*">
     			</label>
     		</div>
    
    		<div class="view-block">
    			<h2><?php echo htmlspecialchars(t('editor.section_description', 'Beschreibung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    			<textarea name="description" rows="4" style="width:100%;"><?php
    				echo htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    			?></textarea>
    		</div>
    
    		<div class="view-block">
    			<h2><?php echo htmlspecialchars(t('editor.section_ingredients', 'Zutaten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    			<textarea name="ingredients" rows="8" style="width:100%;"><?php
    				echo htmlspecialchars($ingredients, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    			?></textarea>
    		</div>
    
    		<div class="view-block">
    			<h2><?php echo htmlspecialchars(t('editor.section_directions', 'Zubereitung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    			<textarea name="directions" rows="10" style="width:100%;"><?php
    				echo htmlspecialchars($directions, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    			?></textarea>
    		</div>
    
    		<div class="view-block">
    			<h2><?php echo htmlspecialchars(t('editor.section_notes', 'Notizen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    			<textarea name="notes" rows="4" style="width:100%;"><?php
    				echo htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    			?></textarea>
    		</div>
    
    		<div class="view-block">
    			<h2><?php echo htmlspecialchars(t('editor.section_nutrition', 'Nährwerte'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    			<textarea name="nutritionalValues" rows="4" style="width:100%;"><?php
    				echo htmlspecialchars($nutritionalVals, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    			?></textarea>
    		</div>
    
    		<div class="view-block">
    			<h2><?php echo htmlspecialchars(t('editor.section_time_servings', 'Zeit &amp; Portionen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    
    			<label>
    				<?php echo htmlspecialchars(t('editor.preparation_time_label', 'Zubereitungszeit'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><br>
    				<input type="text" name="preparationTime" value="<?php echo htmlspecialchars($preparationTime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width:100%;">
    			</label>
    			<br><br>
    
    			<label>
    				<?php echo htmlspecialchars(t('editor.servings_label', 'Portionen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><br>
    				<input type="text" name="servings" value="<?php echo htmlspecialchars($servings, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width:100%;">
    			</label>
    		</div>
    
    		<div class="view-block">
    			<h2><?php echo htmlspecialchars(t('editor.section_source', 'Quelle'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
    			<input type="text" name="source" value="<?php echo htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" style="width:100%;">
    		</div>
    
            <div class="editor-actions-bottom">
            	<a href="<?php echo 'view.php?id=' . $id; ?>">
    				<button type="button">
    					<?php echo htmlspecialchars(t('editor.back_button', 'Zurück'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    				</button>
    			</a>
            	<button type="submit">
    				<?php echo htmlspecialchars(t('editor.save_button', 'Speichern'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
            </div>
    
    	</form>
    
    </main>
    
    <script>
    document.addEventListener('DOMContentLoaded', () => {
    	const input  = document.getElementById('categories-input');
    	const select = document.getElementById('categories-select');
    
    	if (input && select) {
    		select.addEventListener('change', () => {
    			const val = select.value.trim();
    			if (!val) return;
    
    			let parts = input.value
    				.split(',')
    				.map(v => v.trim())
    				.filter(v => v.length > 0);
    
    			if (!parts.includes(val)) {
    				parts.push(val);
    				input.value = parts.join(', ');
    			}
    		});
    	}
    });
    </script>
    
    </body>
</html>
