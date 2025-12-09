<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/../lib/BroccoliImporter.php';

header('Content-Type: application/json; charset=utf-8');

try {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		http_response_code(405);
		echo json_encode(['error' => 'Nur POST erlaubt.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
		http_response_code(400);
		echo json_encode(['error' => 'Kein Upload-Feld "file" gefunden.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$upload = $_FILES['file'];

	if (!isset($upload['error']) || $upload['error'] !== UPLOAD_ERR_OK) {
		http_response_code(400);
		echo json_encode(['error' => 'Fehler beim Datei-Upload.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$tmpPath  = $upload['tmp_name'];
	$origName = $upload['name'] ?? null;

	if (!is_uploaded_file($tmpPath)) {
		http_response_code(400);
		echo json_encode(['error' => 'Ungültige Upload-Datei.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$db = get_db();
	$userId = require_login();
	$imageDir = __DIR__ . '/../data/images';

	// Duplikaterkennung für einzelne .broccoli anhand content_hash
	$isDuplicate = false;
	$recipeId    = null;

	$contentHash = null;
	$zip = new ZipArchive();
	if ($zip->open($tmpPath) === true) {
		$jsonIndex = null;
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = $zip->getNameIndex($i);
			if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'json') {
				$jsonIndex = $i;
				break;
			}
		}
		if ($jsonIndex !== null) {
			$jsonRaw = $zip->getFromIndex($jsonIndex);
			if ($jsonRaw !== false) {
				try {
					$data = json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR);
					$contentHash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
				} catch (Throwable $e) {
					// wenn JSON kaputt ist, machen wir einfach die normale Import-Logik ohne Duplikat-Flag
				}
			}
		}
		$zip->close();
	}

	if ($contentHash !== null) {
		$stmt = $db->prepare(
			'SELECT id
			 FROM recipes
			 WHERE owner_id = :owner_id
			   AND content_hash = :content_hash
			 LIMIT 1'
		);
		$stmt->execute([
			':owner_id'     => $userId,
			':content_hash' => $contentHash,
		]);
		$existingId = $stmt->fetchColumn();
		if ($existingId !== false) {
			$isDuplicate = true;
			$recipeId    = (int)$existingId;
		}
	}

	$importer = new BroccoliImporter($db, $imageDir);

	// Multiuser: hier später ownerId aus Session holen
	$ownerId    = $userId;
	$sourceType = 'broccoli';
	$sourceFile = $origName;

	if (!$isDuplicate) {
 		$recipeId = $importer->importSingleBroccoli($tmpPath, $ownerId, $sourceType, $sourceFile);
 	}

 	// Rezeptdaten direkt wieder aus DB holen

	$stmt = $db->prepare(
		'SELECT
			r.id,
			r.title,
			r.image_path,
			r.json_data
		 FROM recipes r
		 WHERE r.id = :id
		 AND r.owner_id = :owner_id'
	);

	$stmt->execute([
		':id'       => $recipeId,
		':owner_id' => $userId,
	]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($row === false) {
		http_response_code(500);
		echo json_encode(['error' => 'Rezept nach Import nicht gefunden.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$jsonData = [];
	try {
		$jsonData = json_decode((string)$row['json_data'], true, 512, JSON_THROW_ON_ERROR);
	} catch (Throwable $e) {
		// zur Not leeres Array liefern, JSON bleibt aber in DB erhalten
		$jsonData = null;
	}
	echo json_encode([
		'id'        => (int)$row['id'],
		'title'     => $row['title'],
		'image_url' => $row['image_path']
			? ('../api/image.php?id=' . (int)$row['id'])
			: null,
		'json_data' => $jsonData,
		'already_exists' => $isDuplicate,
	], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'error'   => 'Interner Fehler.',
		'message' => $e->getMessage(),
	], JSON_UNESCAPED_UNICODE);
}
