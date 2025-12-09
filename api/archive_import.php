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
	$userId = require_login();
	$raw = file_get_contents('php://input');
	$data = json_decode($raw, true);

	if (!is_array($data) || !isset($data['archive_id']) || !isset($data['filenames'])) {
		http_response_code(400);
		echo json_encode(['error' => 'archive_id oder filenames fehlt.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$archiveId = (int)$data['archive_id'];
	$filenames = $data['filenames'];
	if ($archiveId <= 0 || !is_array($filenames) || empty($filenames)) {
		http_response_code(400);
		echo json_encode(['error' => 'Ungültige Parameter.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$db = get_db();

	// Archivpfad laden – nur eigenes Archiv
	$stmt = $db->prepare(
		'SELECT stored_path
		 FROM archives
		 WHERE id = :id AND owner_id = :owner_id'
	);
	$stmt->execute([
		':id'       => $archiveId,
		':owner_id' => $userId,
	]);
	$storedPath = $stmt->fetchColumn();


	if ($storedPath === false || !is_file($storedPath)) {
		http_response_code(404);
		echo json_encode(['error' => 'Archiv nicht gefunden.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$imageDir = __DIR__ . '/../data/images';
	$importer = new BroccoliImporter($db, $imageDir);

	$importedIds = [];
	$duplicateIds = [];

	$lowerPath = strtolower($storedPath);

	if (substr($lowerPath, -17) === '.broccoli-archive') {
		// Multi-Archiv: wie bisher, jede gewählte .broccoli-Datei importieren
		$zip = new ZipArchive();
		if ($zip->open($storedPath) !== true) {
			http_response_code(500);
			echo json_encode(['error' => 'Archiv kann nicht geöffnet werden.'], JSON_UNESCAPED_UNICODE);
			exit;
		}

		foreach ($filenames as $file) {
			$index = $zip->locateName($file, ZipArchive::FL_NOCASE);
			if ($index === false) {
				continue;
			}

			$inner = $zip->getFromIndex($index);
			if ($inner === false) {
				continue;
			}

			$tmpInner = sys_get_temp_dir() . '/' . bin2hex(random_bytes(16)) . '.zip';
			file_put_contents($tmpInner, $inner);

			// Hash dieses inneren Rezepts berechnen, um Duplikate zu erkennen
			$contentHash = null;
			$innerZip = new ZipArchive();
			if ($innerZip->open($tmpInner) === true) {
				$jsonIndex = null;
				for ($i2 = 0; $i2 < $innerZip->numFiles; $i2++) {
					$innerName = $innerZip->getNameIndex($i2);
					if (strtolower(pathinfo($innerName, PATHINFO_EXTENSION)) === 'json') {
						$jsonIndex = $i2;
						break;
					}
				}
				if ($jsonIndex !== null) {
					$jsonRaw = $innerZip->getFromIndex($jsonIndex);
					if ($jsonRaw !== false) {
 						try {
 							$data = json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR);
 							$contentHash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
 						} catch (Throwable $e) {
 							// ignorieren, dann keine Duplikat-Erkennung
 						}
 					}
 				}
 				$innerZip->close();
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
 					$duplicateIds[] = (int)$existingId;
 					@unlink($tmpInner);
 					continue;
 				}
 			}

 			$recipeId = $importer->importSingleBroccoli(
 				$tmpInner,
 				$userId,
 				'archive',
 				basename($storedPath)
 			);

 			$importedIds[] = $recipeId;

 			@unlink($tmpInner);

		}

		$zip->close();
 	} elseif (substr($lowerPath, -9) === '.broccoli') {
 		// Einzelne Broccoli-Datei: wenn etwas markiert wurde, importieren wir genau diese eine
 		if (!empty($filenames)) {
 			// Hash über die JSON-Daten ermitteln, um Duplikate wie bei .broccoli-archive zu erkennen
 			$contentHash = null;
 			$zipSingle = new ZipArchive();
 			if ($zipSingle->open($storedPath) === true) {
 				$jsonIndex = null;
 				for ($i = 0; $i < $zipSingle->numFiles; $i++) {
 					$name = $zipSingle->getNameIndex($i);
 					if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'json') {
 						$jsonIndex = $i;
 						break;
 					}
 				}
 				if ($jsonIndex !== null) {
 					$jsonRaw = $zipSingle->getFromIndex($jsonIndex);
 					if ($jsonRaw !== false) {
 						try {
 							$data = json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR);
 							if (is_array($data)) {
 								$contentHash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
 							}
 						} catch (Throwable $e) {
 							// Ignorieren – fällt auf normalen Import zurück
 						}
 					}
 				}
 				$zipSingle->close();
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
 					$duplicateIds[] = (int)$existingId;
 				} else {
 					$recipeId = $importer->importSingleBroccoli(
 						$storedPath,
 						$userId,
 						'archive',
 						basename($storedPath)
 					);
 					$importedIds[] = $recipeId;
 				}
 			} else {
 				// Fallback: Hash konnte nicht ermittelt werden, normal importieren
 				$recipeId = $importer->importSingleBroccoli(
 					$storedPath,
 					$userId,
 					'archive',
 					basename($storedPath)
 				);
 				$importedIds[] = $recipeId;
 			}
 		}
	} else {
		http_response_code(400);
		// Archiv-Datei und -Eintrag nach erfolgreichem Import entfernen
		if (isset($storedPath) && is_string($storedPath) && $storedPath !== '' && file_exists($storedPath)) {
			@unlink($storedPath);
		}

		if (isset($archiveId) && $archiveId > 0) {
			$stmt = $db->prepare('DELETE FROM archives WHERE id = :id AND owner_id = :owner_id');
			$stmt->execute([
				':id'        => $archiveId,
				':owner_id'  => $userId,
			]);
		}

		echo json_encode(['error' => 'Nicht unterstützter Archivtyp.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	echo json_encode([
		'imported_ids' => $importedIds,
		'duplicate_ids' => $duplicateIds,
	], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'error'   => 'Interner Fehler.',
		'message' => $e->getMessage()
	], JSON_UNESCAPED_UNICODE);
}
