<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/../lib/share_access.php';

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
	$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
	$shareToken = isset($_GET['share_token']) ? trim((string)$_GET['share_token']) : '';
	$collectionToken = isset($_GET['collection_token']) ? trim((string)$_GET['collection_token']) : '';

	if (!brassica_user_can_access_recipe($db, $id, $currentUserId, $shareToken, $collectionToken)) {
		http_response_code(404);
		echo 'Bild nicht gefunden.';
		exit;
	}

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

	$fullPath = legacy_image_file((string)$imagePath);

	if (!is_file($fullPath)) {
		http_response_code(404);
		echo 'Bilddatei nicht gefunden.';
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
