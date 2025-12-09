<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
	if (!isset($_GET['archive_id'], $_GET['filename'])) {
		http_response_code(400);
		echo 'archive_id oder filename fehlt.';
		exit;
	}

	$archiveId = (int)$_GET['archive_id'];
	$filename  = (string)$_GET['filename'];

	if ($archiveId <= 0) {
		http_response_code(400);
		echo 'Ungültige Parameter.';
		exit;
	}

	$db = get_db();

	$stmt = $db->prepare('SELECT stored_path FROM archives WHERE id = :id');
	$stmt->execute([':id' => $archiveId]);
	$storedPath = $stmt->fetchColumn();

	if ($storedPath === false || !is_file($storedPath)) {
		http_response_code(404);
		echo 'Archiv nicht gefunden.';
		exit;
	}

	$lowerPath = strtolower($storedPath);

	// Hilfsfunktion: Bild aus einem .broccoli-ZIP holen
	$readImageFromBroccoli = function (string $broccoliPath): ?array {
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
		if ($jsonRaw === false) {
			$zip->close();
			return null;
		}

		$data = json_decode($jsonRaw, true);
		$imageName = $data['imageName'] ?? null;
		if (!$imageName) {
			$zip->close();
			return null;
		}

		$imgIndex = $zip->locateName($imageName, ZipArchive::FL_NOCASE);
		if ($imgIndex === false) {
			$zip->close();
			return null;
		}

		$imgData = $zip->getFromIndex($imgIndex);
		if ($imgData === false) {
			$zip->close();
			return null;
		}

		$zip->close();

		$ext = strtolower((string)pathinfo($imageName, PATHINFO_EXTENSION));
		$mime = 'image/jpeg';
		if ($ext === 'png') {
			$mime = 'image/png';
		} elseif ($ext === 'webp') {
			$mime = 'image/webp';
		} elseif ($ext === 'gif') {
			$mime = 'image/gif';
		}

		return ['data' => $imgData, 'mime' => $mime];
	};

	$result = null;

	if (substr($lowerPath, -17) === '.broccoli-archive') {
		// Multi-Archiv: inneres .broccoli extrahieren und daraus das Bild lesen
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
			echo 'Datei im Archiv nicht gefunden.';
			exit;
		}

		$innerData = $zip->getFromIndex($index);
		$zip->close();

		if ($innerData === false) {
			http_response_code(500);
			echo 'Innere Datei konnte nicht gelesen werden.';
			exit;
		}

		$tmpInner = sys_get_temp_dir() . '/' . bin2hex(random_bytes(16)) . '.zip';
		file_put_contents($tmpInner, $innerData);

		$result = $readImageFromBroccoli($tmpInner);
	} elseif (substr($lowerPath, -9) === '.broccoli') {
		// Einzelne .broccoli: direkt daraus das Bild lesen (filename ist egal)
		$result = $readImageFromBroccoli($storedPath);
	} else {
		http_response_code(400);
		echo 'Nicht unterstützter Archivtyp.';
		exit;
	}

	if ($result === null) {
		http_response_code(404);
		echo 'Kein Bild gefunden.';
		exit;
	}

	header('Content-Type: ' . $result['mime']);
	header('Cache-Control: max-age=86400, public');
	echo $result['data'];

} catch (Throwable $e) {
	http_response_code(500);
	echo 'Fehler: ' . $e->getMessage();
}
