<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';

try {
	if (!isset($_GET['id'])) {
		http_response_code(400);
		echo 'Parameter "id" fehlt.';
		exit;
	}

	$id = (int)$_GET['id'];
	if ($id <= 0) {
		http_response_code(400);
		echo 'Ungültige ID.';
		exit;
	}

	$db = get_db();
	$stmt = $db->prepare(
		'SELECT image_path
		 FROM recipes
		 WHERE id = :id'
	);
	$stmt->execute([
		':id' => $id,
	]);

	$imagePath = $stmt->fetchColumn();

	if ($imagePath === false || $imagePath === null || $imagePath === '') {
		http_response_code(404);
		echo 'Kein Bild für dieses Rezept.';
		exit;
	}

	// image_path ist z.B. "data/images/abcdef123456.jpg" relativ zum Projekt-Root
	$baseDir  = realpath(__DIR__ . '/..');                // project-root
	$fullPath = realpath($baseDir . '/' . $imagePath);

	if ($fullPath === false || !is_file($fullPath)) {
		http_response_code(404);
		echo 'Bilddatei nicht gefunden.';
		exit;
	}

	// Sicherheit: Pfad muss innerhalb von data/images liegen
	$imagesDir = realpath($baseDir . '/data/images');
	if ($imagesDir === false || strpos($fullPath, $imagesDir) !== 0) {
		http_response_code(403);
		echo 'Zugriff verweigert.';
		exit;
	}

	$ext = strtolower((string)pathinfo($fullPath, PATHINFO_EXTENSION));
	$mime = 'image/jpeg';
	if ($ext === 'png') {
		$mime = 'image/png';
	} elseif ($ext === 'webp') {
		$mime = 'image/webp';
	} elseif ($ext === 'gif') {
		$mime = 'image/gif';
	}

	header('Content-Type: ' . $mime);
	header('Cache-Control: max-age=86400, public');
	readfile($fullPath);
} catch (Throwable $e) {
	http_response_code(500);
	echo 'Fehler: ' . $e->getMessage();
}
