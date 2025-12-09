<?php
declare(strict_types=1);
require_once __DIR__ . '/session_bootstrap_page.php';
$userId = require_login_page();

// Fehleranzeige für Entwicklung
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../api/db.php';

$db = get_db();

/**
 * Broccoli-Datenstruktur aus Formularfeldern aufbauen
 */
function build_broccoli_data(
	string $title,
	string $description,
	string $ingredients,
	string $directions,
	string $notes,
	string $nutritionalVals,
	string $preparationTime,
	string $servings,
	string $source,
	array $categoriesArr,
	bool $favorite,
	?string $imageName
): array {
 	$data = [
 		'categories'        => $categoriesArr,
 		'description'       => $description !== '' ? $description : '',
 		'directions'        => $directions !== '' ? $directions : '',
 		'ingredients'       => $ingredients !== '' ? $ingredients : '',
 		'notes'             => $notes !== '' ? $notes : '',
 		'nutritionalValues' => $nutritionalVals !== '' ? $nutritionalVals : '',
 		'preparationTime'   => $preparationTime !== '' ? $preparationTime : '',
 		'servings'          => $servings !== '' ? $servings : '',
 		'source'            => $source !== '' ? $source : '',
 		'title'             => $title,
 		'favorite'          => $favorite,
 	];


	if ($imageName !== null && $imageName !== '') {
		$data['imageName'] = $imageName;
	}

	return $data;
}

/**
 * Slug aus Titel bilden
 */
function make_slug(string $title): string {
    // alle Buchstaben/Ziffern (inkl. Umlaute) erlauben, Rest -> "_"
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '_', $title);
    $slug = trim((string)$slug, '_');
    return $slug !== '' ? $slug : 'rezept';
}

/**
 * Broccoli-Datei zum Download senden (inkl. Bild, falls vorhanden)
 */
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

	if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
		throw new RuntimeException('Konnte ZIP-Archiv nicht erstellen.');
	}

	$baseName = make_slug($title);
	$jsonName = $baseName . '.json';
	$zip->addFromString($jsonName, $json);

	// Bild einfügen, falls vorhanden
	if ($imagePathRel) {
		$fullPath = realpath(__DIR__ . '/../' . $imagePathRel);
		if ($fullPath !== false && is_file($fullPath)) {
			$zip->addFile($fullPath, basename($imagePathRel));
		}
	}

	$zip->close();

	$downloadName = $baseName . '.broccoli';

	header('Content-Type: application/x-broccoli');
    $downloadNameHeader = str_replace(['"', '\\'], ['_', '_'], $downloadName);
    header(
        'Content-Disposition: attachment; filename="' . $downloadNameHeader . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName)
    );
    header('Content-Length: ' . filesize($tmpFile));
	readfile($tmpFile);
	@unlink($tmpFile);
	exit;
}

// vorhandene Kategorien für Dropdown laden
$allCategories = [];
try {
	$stmtCats = $db->query('SELECT name FROM categories ORDER BY name');
	$allCategories = $stmtCats->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
	$allCategories = [];
}

// Formular-Defaults
$title           = '';
$description     = '';
$ingredients     = '';
$directions      = '';
$notes           = '';
$nutritionalVals = '';
$preparationTime = '';
$servings        = '';
$source          = '';
$categoriesInput = '';
$favoriteChecked = false;
$error           = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$title           = trim((string)($_POST['title'] ?? ''));
	$description     = trim((string)($_POST['description'] ?? ''));
	$ingredients     = trim((string)($_POST['ingredients'] ?? ''));
	$directions      = trim((string)($_POST['directions'] ?? ''));
	$notes           = trim((string)($_POST['notes'] ?? ''));
	$nutritionalVals = trim((string)($_POST['nutritionalValues'] ?? ''));
	$preparationTime = trim((string)($_POST['preparationTime'] ?? ''));
	$servings        = trim((string)($_POST['servings'] ?? ''));
	$source          = trim((string)($_POST['source'] ?? ''));
	$categoriesInput = trim((string)($_POST['categories'] ?? ''));
	$favoriteChecked = isset($_POST['favorite']);
	$favorite        = $favoriteChecked ? 1 : 0;

	$action = (string)($_POST['action'] ?? 'save');

	if ($title === '') {
		$error = 'Titel darf nicht leer sein.';
	}

	// Kategorien-Array im Broccoli-Format [{name:"…"}]
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

	// Bild-Upload
	$newImagePathRel = null;
	if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
		if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
			$tmp      = $_FILES['image']['tmp_name'];
			$origName = (string)($_FILES['image']['name'] ?? 'image');
			$ext      = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));

			$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
			if (!in_array($ext, $allowed, true)) {
				$error = 'Nur JPG, PNG, WEBP oder GIF sind als Bild erlaubt.';
			} else {
				$imageDir = __DIR__ . '/../data/images';
				if (!is_dir($imageDir)) {
					mkdir($imageDir, 0775, true);
				}

				$newFileName  = bin2hex(random_bytes(16)) . '.' . $ext;
				$targetPathFs = $imageDir . '/' . $newFileName;

				if (!move_uploaded_file($tmp, $targetPathFs)) {
					$error = 'Fehler beim Speichern des Bildes.';
				} else {
					$newImagePathRel = 'data/images/' . $newFileName;
				}
			}
		} else {
			$error = 'Fehler beim Upload des Bildes.';
		}
	}

	if ($error === null) {
		$imageName = $newImagePathRel ? basename($newImagePathRel) : null;

		$data = build_broccoli_data(
			$title,
			$description,
			$ingredients,
			$directions,
			$notes,
			$nutritionalVals,
			$preparationTime,
			$servings,
			$source,
			$categoriesArr,
			(bool)$favorite,
			$imageName
		);

		if ($action === 'download') {
			// Nur Broccoli-Datei erzeugen, keine DB-Änderung
			send_broccoli_download($data, $title, $newImagePathRel);
		} elseif ($action === 'save') {
			// In DB speichern
			$jsonEncoded = json_encode($data, JSON_UNESCAPED_UNICODE);

			$sql = 'INSERT INTO recipes (
						owner_id,
						title,
						description,
						directions,
						ingredients,
						notes,
						nutritional_vals,
						preparation_time,
						servings,
						source,
						favorite,
						image_path,
						json_data,
						source_type,
						source_file,
						created_at,
						updated_at
					) VALUES (
						:owner_id,
						:title,
						:description,
						:directions,
						:ingredients,
						:notes,
						:nutritional_vals,
						:preparation_time,
						:servings,
						:source,
						:favorite,
						:image_path,
						:json_data,
						:source_type,
						:source_file,
						:ts,
						:ts
					)';


			$now = (new DateTimeImmutable())->format('c');

			$stmt = $db->prepare($sql);
			$stmt->execute([
				':owner_id'         => $userId,
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
				':image_path'       => $newImagePathRel,
				':json_data'        => $jsonEncoded,
				':source_type'      => 'editor',
				':source_file'      => null,
				':ts'               => $now,
			]);


			$recipeId = (int)$db->lastInsertId();

			// Kategorien-Verknüpfungen anlegen
			foreach ($categoriesArr as $cat) {
				$name = $cat['name'];

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
					':rid' => $recipeId,
					':cid' => $catId
				]);
			}

			header('Location: view.php?id=' . $recipeId);
			exit;
		}
	}
}

?><!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title>Neues Rezept erstellen</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
</head>
<body>

<header class="app-header">
	<h1>Neues Rezept schreiben</h1>
	<nav class="main-nav">
		<a href="index.php">Übersicht</a>
		<a href="archive.php">Rezept-Import</a>
		<?php if ($userId === 1): ?>
			<a href="admin_users.php">Benutzerverwaltung</a>
			<a href="admin_collections.php">Sammlungen verwalten</a>
		<?php endif; ?>
		<a href="logout.php" class="logout-btn">Logout</a>
	</nav>
</header>

<main class="app-main editor-main">
	<?php if ($error !== null): ?>
		<div class="error-message" style="margin-bottom:1rem;">
			<?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</div>
	<?php endif; ?>

	<form method="post" action="editor_new.php" enctype="multipart/form-data">
    	<div class="editor-actions-top">
    		<a href="index.php"><button type="button">Zurück zur Übersicht</button></a>
    		<button type="submit" name="action" value="save">In Datenbank speichern</button>
    		<button type="reset">Formular löschen</button>
    		<button type="submit" name="action" value="download">Als Broccoli-Datei herunterladen</button>
    	</div>
		<div class="view-block">
			<h2>Neues Rezept schreiben</h2>

			<label>
				Titel<br>
				<input type="text" name="title" value="<?php
					echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
				?>" style="width:100%;" required>
			</label>
			<br><br>

			<label>
				Kategorien (kommagetrennt)<br>
				<input type="text" name="categories" id="categories-input" value="<?php
					echo htmlspecialchars($categoriesInput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
				?>" style="width:100%;">
			</label>
			<br><br>

			<label>
				Kategorie aus vorhandenen wählen<br>
				<select id="categories-select">
					<option value="">– Kategorie auswählen –</option>
					<?php foreach ($allCategories as $catName): ?>
						<option value="<?php echo htmlspecialchars($catName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
							<?php echo htmlspecialchars($catName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<small>Ausgewählte Kategorie wird dem Textfeld oben hinzugefügt.</small>
			</label>
			<br><br>

			<label>
				Favorit
				<input type="checkbox" name="favorite" <?php echo $favoriteChecked ? 'checked' : ''; ?>>
			</label>
		</div>

		<div class="view-block">
			<h2>Bild</h2>
			<p>Optionales Bild hochladen, das im Rezept und in der Broccoli-Datei verwendet wird.</p>
			<label>
				Bilddatei<br>
				<input type="file" name="image" accept="image/*">
			</label>
		</div>

		<div class="view-block">
			<h2>Beschreibung</h2>
			<textarea name="description" rows="4" style="width:100%;"><?php
				echo htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			?></textarea>
		</div>

		<div class="view-block">
			<h2>Zutaten</h2>
			<textarea name="ingredients" rows="8" style="width:100%;"><?php
				echo htmlspecialchars($ingredients, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			?></textarea>
		</div>

		<div class="view-block">
			<h2>Zubereitung</h2>
			<textarea name="directions" rows="10" style="width:100%;"><?php
				echo htmlspecialchars($directions, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			?></textarea>
		</div>

		<div class="view-block">
			<h2>Notizen</h2>
			<textarea name="notes" rows="4" style="width:100%;"><?php
				echo htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			?></textarea>
		</div>

		<div class="view-block">
			<h2>Nährwerte</h2>
			<textarea name="nutritionalValues" rows="4" style="width:100%;"><?php
				echo htmlspecialchars($nutritionalVals, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			?></textarea>
		</div>

		<div class="view-block">
			<h2>Zeit &amp; Portionen</h2>

			<label>
				Zubereitungszeit<br>
				<input type="text" name="preparationTime" value="<?php
					echo htmlspecialchars($preparationTime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
				?>" style="width:100%;">
			</label>
			<br><br>

			<label>
				Portionen<br>
				<input type="text" name="servings" value="<?php
					echo htmlspecialchars($servings, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
				?>" style="width:100%;">
			</label>
		</div>

		<div class="view-block">
			<h2>Quelle</h2>
			<input type="text" name="source" value="<?php
				echo htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			?>" style="width:100%;">
		</div>

		<div class="editor-actions-bottom">
    		<a href="index.php"><button type="button">Zurück zur Übersicht</button></a>
			<button type="submit" name="action" value="save">In Datenbank speichern</button>
			<button type="reset">Formular löschen</button>
			<button type="submit" name="action" value="download">Als Broccoli-Datei herunterladen</button>
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
