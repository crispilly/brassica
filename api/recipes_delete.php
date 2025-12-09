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

	$raw = file_get_contents('php://input');
	$data = json_decode($raw, true);

	if (!is_array($data) || !isset($data['ids']) || !is_array($data['ids'])) {
		http_response_code(400);
		echo json_encode(['error' => 'ids fehlt oder ist ungültig.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$ids = array_values(array_unique(array_map('intval', $data['ids'])));
	$ids = array_filter($ids, static fn(int $v) => $v > 0);

	if (empty($ids)) {
		http_response_code(400);
		echo json_encode(['error' => 'Keine gültigen IDs übergeben.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

$db = get_db();
$userId = require_login();

	// Bildpfade holen, um Dateien ggf. zu löschen – nur eigene Rezepte
	$inPlaceholders = implode(',', array_fill(0, count($ids), '?'));

	$sqlSelect = "SELECT id, image_path
	              FROM recipes
	              WHERE id IN ($inPlaceholders)
	                AND owner_id = ?";

	$params = $ids;
	$params[] = $userId;

	$stmt = $db->prepare($sqlSelect);
	$stmt->execute($params);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// IDs auf tatsächlich zum Benutzer gehörende Rezepte einschränken
	$ids = array_map('intval', array_column($rows, 'id'));
	if (empty($ids)) {
		http_response_code(404);
		echo json_encode(['error' => 'Keine der IDs gefunden.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	// Platzhalter anhand der gefilterten IDs neu aufbauen
	$inPlaceholders = implode(',', array_fill(0, count($ids), '?'));

	$imageBase = realpath(__DIR__ . '/../data/images');



	// Bilddateien löschen (wenn innerhalb von data/images)
	foreach ($rows as $row) {
		if (!empty($row['image_path'])) {
			$fullPath = realpath(__DIR__ . '/../' . $row['image_path']);
			if ($fullPath !== false && $imageBase !== false && strpos($fullPath, $imageBase) === 0 && is_file($fullPath)) {
				@unlink($fullPath);
			}
		}
	}

	// Beziehungen löschen
	$stmtDelRel = $db->prepare("DELETE FROM recipe_categories WHERE recipe_id IN ($inPlaceholders)");
	$stmtDelRel->execute($ids);

	// Rezepte löschen
	$stmtDel = $db->prepare("DELETE FROM recipes WHERE id IN ($inPlaceholders)");
	$stmtDel->execute($ids);

	echo json_encode([
		'deleted_ids' => $ids
	], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'error'   => 'Interner Fehler.',
		'message' => $e->getMessage()
	], JSON_UNESCAPED_UNICODE);
}
