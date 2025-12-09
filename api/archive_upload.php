<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		http_response_code(405);
		echo json_encode(['error' => 'Nur POST erlaubt.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	if (!isset($_FILES['file'])) {
		http_response_code(400);
		echo json_encode(['error' => 'Upload-Feld "file" fehlt.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$upload = $_FILES['file'];
	if ($upload['error'] !== UPLOAD_ERR_OK) {
		http_response_code(400);
		echo json_encode(['error' => 'Upload fehlgeschlagen.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$tmpPath  = $upload['tmp_name'];
	$filename = $upload['name'] ?? 'upload';

	if (!is_uploaded_file($tmpPath)) {
		http_response_code(400);
		echo json_encode(['error' => 'Ungültiger Upload.'], JSON_UNESCAPED_UNICODE);
		exit;
	}
	$userId = require_login();
	$origLower = strtolower($filename);
	$isMulti   = false; // .broccoli-archive
	$isSingle  = false; // .broccoli

	if (substr($origLower, -17) === '.broccoli-archive') {
		$isMulti = true;
	} elseif (substr($origLower, -9) === '.broccoli') {
		$isSingle = true;
	} else {
		http_response_code(400);
		echo json_encode(['error' => 'Nur .broccoli und .broccoli-archive werden unterstützt.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$db = get_db();

	$archiveDir = __DIR__ . '/../data/uploads/archives';
	if (!is_dir($archiveDir)) {
		mkdir($archiveDir, 0775, true);
	}

	$suffix = $isMulti ? '.broccoli-archive' : '.broccoli';
	$storedPath = $archiveDir . '/' . bin2hex(random_bytes(16)) . $suffix;

	if (!move_uploaded_file($tmpPath, $storedPath)) {
		http_response_code(500);
		echo json_encode(['error' => 'Konnte die Datei nicht speichern.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	// Archiveintrag speichern
	$stmt = $db->prepare(
		'INSERT INTO archives (owner_id, original_name, stored_path, imported_at)
		 VALUES (:owner_id, :orig, :path, :ts)'
	);
	$now = (new DateTimeImmutable())->format('c');

	$stmt->execute([
		':owner_id' => $userId,
		':orig'     => $filename,
		':path'     => $storedPath,
		':ts'       => $now
	]);

	$archiveId = (int)$db->lastInsertId();

	$items = [];

	if ($isMulti) {
		// klassisches .broccoli-archive: enthält mehrere .broccoli-Dateien
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

			// innere .broccoli ist wieder ZIP
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
	} elseif ($isSingle) {
		// einzelne .broccoli-Datei: direkt das Rezept lesen
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
		if (!is_array($recipe) || !isset($recipe['title'])) {
			$zip->close();
			http_response_code(400);
			echo json_encode(['error' => 'Ungültiges Broccoli-JSON.'], JSON_UNESCAPED_UNICODE);
			exit;
		}

		$hasImage = isset($recipe['imageName']) && $recipe['imageName'] !== '';

		$zip->close();

		$cats = [];
		if (isset($recipe['categories']) && is_array($recipe['categories'])) {
			foreach ($recipe['categories'] as $c) {
				if (is_array($c) && isset($c['name']) && trim($c['name']) !== '') {
					$cats[] = ['name' => trim($c['name'])];
				}
			}
		}

		// wir behandeln die einzelne Datei wie ein "Archiv mit einem Eintrag"
		$items[] = [
			'temp_id'    => '0001',
			'filename'   => $filename, // wird in Import/Image nicht strikt benötigt
			'title'      => $recipe['title'] ?? '',
			'categories' => $cats,
			'favorite'   => !empty($recipe['favorite']) ? 1 : 0,
			'has_image'  => $hasImage,
			'image_url'  => $hasImage
				? ('../api/archive_image.php?archive_id=' . $archiveId . '&filename=' . rawurlencode($filename))
				: null,
		];
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
