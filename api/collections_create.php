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
	$ids = array_filter($ids, static fn (int $v) => $v > 0);

	if (empty($ids)) {
		http_response_code(400);
		echo json_encode(['error' => 'Keine gültigen IDs übergeben.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$db     = get_db();
	$userId = require_login();

	// Prüfen, ob alle Rezepte dem aktuellen Nutzer gehören
	$placeholders = implode(',', array_fill(0, count($ids), '?'));
	$sql = "SELECT COUNT(*) AS cnt
	        FROM recipes
	        WHERE id IN ($placeholders)
	          AND owner_id = ?";

	$stmt   = $db->prepare($sql);
	$params = $ids;
	$params[] = $userId;
	$stmt->execute($params);

	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row || (int)$row['cnt'] !== count($ids)) {
		http_response_code(403);
		echo json_encode(['error' => 'Ein oder mehrere Rezepte gehören nicht zum aktuellen Benutzer.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$token = bin2hex(random_bytes(16));
	$now   = (new DateTimeImmutable())->format('c');

	try {
		$db->beginTransaction();

		// Sammlung anlegen
		$stmtIns = $db->prepare(
			'INSERT INTO collections (owner_id, token, created_at)
			 VALUES (:owner_id, :token, :created_at)'
		);

		$stmtIns->execute([
			':owner_id'   => $userId,
			':token'      => $token,
			':created_at' => $now,
		]);

		$collectionId = (int)$db->lastInsertId();

		// Zuordnungen anlegen
		$stmtRel = $db->prepare(
			'INSERT INTO collection_recipes (collection_id, recipe_id)
			 VALUES (:collection_id, :recipe_id)'
		);

		foreach ($ids as $rid) {
			$stmtRel->execute([
				':collection_id' => $collectionId,
				':recipe_id'     => $rid,
			]);
		}

		$db->commit();
	} catch (Throwable $inner) {
		if ($db->inTransaction()) {
			$db->rollBack();
		}
		throw $inner;
	}

	// URL für die öffentliche Ansicht der Sammlung zurückgeben
	echo json_encode([
		'success' => true,
		'url'     => 'index_open.php?token=' . $token,
	], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'error'   => 'Interner Fehler.',
		'message' => $e->getMessage(),
	], JSON_UNESCAPED_UNICODE);
}