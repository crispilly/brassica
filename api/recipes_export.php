<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';

function make_slug(string $title): string {
    // alle Buchstaben/Ziffern (inkl. Umlaute) erlauben, Rest -> "_"
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '_', $title);
    $slug = trim((string)$slug, '_');
    return $slug !== '' ? $slug : 'rezept';
}

function sanitize_broccoli_data(array $data): array
{
    // Felder, die als Strings gedacht sind:
    $stringFields = [
        'title',
        'description',
        'directions',
        'ingredients',
        'notes',
        'nutritionalValues',
        'preparationTime',
        'servings',
        'source',
    ];

    foreach ($stringFields as $field) {
        if (!array_key_exists($field, $data)) {
            // Feld bleibt einfach weg – Struktur wie bisher.
            continue;
        }

        if ($data[$field] === null) {
            // Keine null-Werte mehr -> leere Strings
            $data[$field] = '';
        } elseif (!is_string($data[$field])) {
            // zur Sicherheit alles auf String casten
            $data[$field] = (string)$data[$field];
        }
    }

    // favorite sicher auf bool bringen
    if (array_key_exists('favorite', $data)) {
        $data['favorite'] = !empty($data['favorite']);
    }

    return $data;
}


try {
	$userId = require_login();
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		http_response_code(405);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['error' => 'Nur POST erlaubt.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$idsRaw = $_POST['ids'] ?? '';
	if (is_array($idsRaw)) {
		$idsList = $idsRaw;
	} else {
		$idsList = explode(',', (string)$idsRaw);
	}

	$ids = [];
	foreach ($idsList as $id) {
		$id = (int)trim((string)$id);
		if ($id > 0) {
			$ids[$id] = $id;
		}
	}
	$ids = array_values($ids);

	if (empty($ids)) {
		http_response_code(400);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['error' => 'Keine gültigen IDs übergeben.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$db = get_db();
	$userId = require_login();

	$placeholders = implode(',', array_fill(0, count($ids), '?'));
	$sql = "SELECT id, title, json_data, image_path
	        FROM recipes
	        WHERE id IN ($placeholders)
	          AND owner_id = ?
	        ORDER BY id";

	$stmt = $db->prepare($sql);
	$params = $ids;
	$params[] = $userId;
	$stmt->execute($params);

	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (!$rows) {
		http_response_code(404);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['error' => 'Keine Rezepte gefunden.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$imagesBase = realpath(__DIR__ . '/../data/images');

	// Für Dateiname / Exportmodus
	$isSingle   = count($rows) === 1;
	$firstTitle = $rows[0]['title'] ?? 'rezept';

	$tmpFile = tempnam(
		sys_get_temp_dir(),
		$isSingle ? 'broccoli_single_' : 'broccoli_export_'
	);
	if ($tmpFile === false) {
		throw new RuntimeException('Konnte temporäre Datei nicht anlegen.');
	}

	$zip = new ZipArchive();
	if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
		throw new RuntimeException('Konnte ZIP-Archiv nicht erstellen.');
	}

	if ($isSingle) {
		// EINZELNES REZEPT -> direkt eine .broccoli (ZIP mit JSON + Bild)
		$row   = $rows[0];
		$id    = (int)$row['id'];
		$title = $row['title'] ?? ('Rezept_' . $id);
		$slug  = make_slug($title);

		$data = [];
		if (!empty($row['json_data'])) {
			$tmp = json_decode($row['json_data'], true);
			if (is_array($tmp)) {
 				$data = $tmp;
 			}
 		}
 
 		if (!isset($data['title']) || $data['title'] === '') {
 			$data['title'] = $title;
 		}

        $data = sanitize_broccoli_data($data);
 
 		$imagePathRel  = $row['image_path'] ?: null;
 		$imageBasename = null;
 		$imageFullPath = null;
 
 		if ($imagePathRel) {
 			$fullPath = realpath(__DIR__ . '/../' . $imagePathRel);
 			if ($fullPath !== false
 				&& $imagesBase !== false
 				&& strpos($fullPath, $imagesBase) === 0
 				&& is_file($fullPath)
 			) {
 				$imageBasename        = basename($fullPath);
 				$data['imageName']    = $imageBasename;
 				$imageFullPath        = $fullPath;
 			} else {
 				$imagePathRel = null;
 			}
 		}
 
 		$jsonName = $slug . '.json';
 		$zip->addFromString($jsonName, json_encode($data, JSON_UNESCAPED_UNICODE));
 
 		if ($imageFullPath !== null && $imageBasename !== null && is_file($imageFullPath)) {
 			$zip->addFile($imageFullPath, $imageBasename);
 		}
 
 		$zip->close();
 		$downloadName = make_slug($firstTitle) . '.broccoli';
 
 	} else {
 		// MEHRERE REZEPTE -> .broccoli-archive mit vielen .broccoli + categories.json
 		$categoriesSet = [];
 
 		foreach ($rows as $row) {
 			$id    = (int)$row['id'];
 			$title = $row['title'] ?? ('Rezept_' . $id);
 			$slug  = make_slug($title);
 
 			$data = [];
 			if (!empty($row['json_data'])) {
 				$tmp = json_decode($row['json_data'], true);
 				if (is_array($tmp)) {
 					$data = $tmp;
 				}
 			}
 
 			if (!isset($data['title']) || $data['title'] === '') {
 				$data['title'] = $title;
 			}

            $data = sanitize_broccoli_data($data);
 
 			// Kategorien aus dem Rezept sammeln (nur dieses Users, da rows bereits nach owner_id gefiltert)
 			if (isset($data['categories']) && is_array($data['categories'])) {
 				foreach ($data['categories'] as $cat) {
 					if (is_array($cat) && isset($cat['name'])) {
 						$name = trim((string)$cat['name']);
 					} elseif (is_string($cat)) {
 						$name = trim($cat);
 					} else {
 						continue;
 					}
 					if ($name !== '') {
 						$categoriesSet[$name] = true;
 					}
 				}
 			}
 
 			$imagePathRel  = $row['image_path'] ?: null;
 			$imageBasename = null;
 			$imageFullPath = null;
 
 			if ($imagePathRel) {
 				$fullPath = realpath(__DIR__ . '/../' . $imagePathRel);
 				if ($fullPath !== false
 					&& $imagesBase !== false
 					&& strpos($fullPath, $imagesBase) === 0
 					&& is_file($fullPath)
 				) {
 					$imageBasename     = basename($fullPath);
 					$data['imageName'] = $imageBasename;
 					$imageFullPath     = $fullPath;
 				} else {
 					$imagePathRel = null;
 				}
 			}
 
// Inneres .broccoli-ZIP für dieses Rezept erzeugen
$innerTmp = tempnam(sys_get_temp_dir(), 'broccoli_recipe_');
if ($innerTmp === false) {
    throw new RuntimeException('Konnte temporäre Datei für Rezept nicht anlegen.');
}

$innerZip = new ZipArchive();
if ($innerZip->open($innerTmp, ZipArchive::OVERWRITE) !== true) {
    @unlink($innerTmp);
    throw new RuntimeException('Konnte Rezept-Archiv nicht erstellen.');
}

$jsonName = $slug . '.json';
$innerZip->addFromString($jsonName, json_encode($data, JSON_UNESCAPED_UNICODE));

if ($imageFullPath !== null && $imageBasename !== null && is_file($imageFullPath)) {
    $innerZip->addFile($imageFullPath, $imageBasename);
}

$innerZip->close();

$outerName = $id . '_' . $slug . '.broccoli';

// Inhalt der inneren .broccoli-Datei lesen
$innerData = file_get_contents($innerTmp);
@unlink($innerTmp);

if ($innerData === false) {
    throw new RuntimeException('Konnte temporäre Rezept-Datei nicht lesen.');
}

// .broccoli als Dateiinhalt ins äußere Archiv schreiben
$zip->addFromString($outerName, $innerData);

 		}
 
 		// categories.json aus gesammelten Kategorien schreiben
 		$categoriesList = [];
 		if (!empty($categoriesSet)) {
 			$names = array_keys($categoriesSet);
 			sort($names, SORT_NATURAL | SORT_FLAG_CASE);
 			foreach ($names as $name) {
 				$categoriesList[] = ['name' => $name];
 			}
 		}
 
 		$zip->addFromString(
 			'categories.json',
 			json_encode($categoriesList, JSON_UNESCAPED_UNICODE)
 		);
 
 		$zip->close();
 		$downloadName = 'EXPORT_' . date('Ymd_His') . '.broccoli-archive';
 	}


header('Content-Type: application/x-broccoli');

// einfache ASCII-Variante für alte Clients
$downloadNameHeader = str_replace(['"', '\\'], ['_', '_'], $downloadName);

// RFC 5987 – UTF-8-Dateiname
header(
    'Content-Disposition: attachment; filename="' . $downloadNameHeader . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName)
);

header('Content-Length: ' . filesize($tmpFile));


	readfile($tmpFile);
	@unlink($tmpFile);
	exit;

} catch (Throwable $e) {
	if (isset($tmpFile) && is_file($tmpFile)) {
		@unlink($tmpFile);
	}
	http_response_code(500);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode([
		'error'   => 'Interner Fehler beim Export.',
		'message' => $e->getMessage(),
	], JSON_UNESCAPED_UNICODE);
}
