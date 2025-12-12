<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
	if (!isset($_GET['archive_id'])) {
		http_response_code(400);
		echo json_encode(['error' => 'archive_id fehlt.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$archiveId = (int)$_GET['archive_id'];
	if ($archiveId <= 0) {
		http_response_code(400);
		echo json_encode(['error' => 'Ungültige archive_id.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$userId = require_login();
	$db = get_db();

	$stmt = $db->prepare('SELECT stored_path, original_name
                      FROM archives
                      WHERE id = :id AND owner_id = :owner_id');
	$stmt->execute([
	':id'       => $archiveId,
	':owner_id' => $userId,
	]);

	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$row) {
		http_response_code(404);
		echo json_encode(['error' => 'Archiv-Eintrag nicht gefunden.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$storedPath   = $row['stored_path'];
	$originalName = $row['original_name'];

	if (!is_file($storedPath)) {
		http_response_code(404);
		echo json_encode(['error' => 'Datei nicht mehr vorhanden.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$lowerPath = strtolower($storedPath);
	$items = [];

	if (substr($lowerPath, -17) === '.broccoli-archive') {
		// Multi-Archiv wie in archive_upload.php
		$zip = new ZipArchive();
		if ($zip->open($storedPath) !== true) {
			http_response_code(500);
			echo json_encode(['error' => 'Archiv konnte nicht geöffnet werden.'], JSON_UNESCAPED_UNICODE);
			exit;
		}

		$tempId = 1;

		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = $zip->getNameIndex($i);

			if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'broccoli') {
				continue;
			}

			$innerData = $zip->getFromIndex($i);
			if ($innerData === false) {
				continue;
			}

			$innerZip = new ZipArchive();
			$tmpInner = sys_get_temp_dir() . '/' . bin2hex(random_bytes(16)) . '.zip';
			file_put_contents($tmpInner, $innerData);

			if ($innerZip->open($tmpInner) !== true) {
				continue;
			}

			$jsonIndex = null;
			for ($j = 0; $j < $innerZip->numFiles; $j++) {
				$n2 = $innerZip->getNameIndex($j);
				if (strtolower(pathinfo($n2, PATHINFO_EXTENSION)) === 'json') {
					$jsonIndex = $j;
					break;
				}
			}

			if ($jsonIndex === null) {
				$innerZip->close();
				continue;
			}

			$jsonRaw = $innerZip->getFromIndex($jsonIndex);
			$innerZip->close();

			if ($jsonRaw === false) {
				continue;
			}

			$recipe = json_decode($jsonRaw, true);
			if (!is_array($recipe) || !isset($recipe['title'])) {
				continue;
			}

            $hasImage = isset($recipe['imageName']) && $recipe['imageName'] !== '';
            
            // Kategorien VOR dem Array aufbauen
            $cats = [];
            if (isset($recipe['categories']) && is_array($recipe['categories'])) {
            	foreach ($recipe['categories'] as $c) {
            		if (is_array($c) && isset($c['name']) && trim($c['name']) !== '') {
            			$cats[] = ['name' => trim($c['name'])];
            		}
            	}
            }
            
            $items[] = [
            	'temp_id'    => str_pad((string)$tempId++, 4, '0', STR_PAD_LEFT),
            	'filename'   => $name,
            	'title'      => $recipe['title'] ?? '',
            	'categories' => $cats,
            	'favorite'   => !empty($recipe['favorite']) ? 1 : 0,
            	'has_image'  => $hasImage,
            	'image_url'  => $hasImage
            		? ('../api/archive_image.php?archive_id=' . $archiveId . '&filename=' . rawurlencode($name))
            		: null,
            ];
		}

		$zip->close();
	} elseif (substr($lowerPath, -9) === '.broccoli') {
		// Einzelne .broccoli-Datei → ein Eintrag
		$zip = new ZipArchive();
		if ($zip->open($storedPath) !== true) {
			http_response_code(500);
			echo json_encode(['error' => 'Broccoli-Datei konnte nicht geöffnet werden.'], JSON_UNESCAPED_UNICODE);
			exit;
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
			http_response_code(400);
			echo json_encode(['error' => 'Keine JSON-Datei in der Broccoli-Datei gefunden.'], JSON_UNESCAPED_UNICODE);
			exit;
		}

		$jsonRaw = $zip->getFromIndex($jsonIndex);
		if ($jsonRaw === false) {
			$zip->close();
			http_response_code(500);
			echo json_encode(['error' => 'JSON konnte nicht gelesen werden.'], JSON_UNESCAPED_UNICODE);
			exit;
		}

		$recipe = json_decode($jsonRaw, true);
		$zip->close();

		if (!is_array($recipe) || !isset($recipe['title'])) {
			http_response_code(400);
			echo json_encode(['error' => 'Ungültiges Broccoli-JSON.'], JSON_UNESCAPED_UNICODE);
			exit;
		}

 		$hasImage = isset($recipe['imageName']) && $recipe['imageName'] !== '';

 		$cats = [];
 		if (isset($recipe['categories']) && is_array($recipe['categories'])) {
 			foreach ($recipe['categories'] as $c) {
 				if (is_array($c) && isset($c['name']) && trim($c['name']) !== '') {
 					$cats[] = ['name' => trim($c['name'])];
 				}
 			}
 		}

 		$items[] = [
 			'temp_id'    => '0001',
 			'filename'   => $originalName,
 			'title'      => $recipe['title'] ?? '',
 			'categories' => $cats,
 			'favorite'   => !empty($recipe['favorite']) ? 1 : 0,
 			'has_image'  => $hasImage,
 			'image_url'  => $hasImage
 				? ('../api/archive_image.php?archive_id=' . $archiveId . '&filename=' . rawurlencode($originalName))
 				: null,
 		];

	} else {
		http_response_code(400);
		echo json_encode(['error' => 'Nicht unterstützter Archivtyp.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	echo json_encode([
		'archive_id' => $archiveId,
		'items'      => $items
	], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'error'   => 'Interner Fehler.',
		'message' => $e->getMessage()
	], JSON_UNESCAPED_UNICODE);
}
