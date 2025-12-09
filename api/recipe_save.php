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
	$input = json_decode($raw, true);

	if (!is_array($input) || !isset($input['id']) || !isset($input['json_data'])) {
		http_response_code(400);
		echo json_encode(['error' => 'id oder json_data fehlt.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$id = (int)$input['id'];
	if ($id <= 0) {
		http_response_code(400);
		echo json_encode(['error' => 'Ungültige ID.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$data = $input['json_data'];

	$title  = $data['title'] ?? null;
	if ($title === null || trim($title) === '') {
		http_response_code(400);
		echo json_encode(['error' => '"title" fehlt.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$db = get_db();
	$userId = require_login();
	// Kategorien
	$categories = [];
	if (isset($data['categories']) && is_array($data['categories'])) {
		$categories = array_column($data['categories'], 'name');
	}

	$favorite = !empty($data['favorite']) ? 1 : 0;

	// JSON speichern
	$sql = 'UPDATE recipes SET
				title            = :title,
				description      = :description,
				directions       = :directions,
				ingredients      = :ingredients,
				notes            = :notes,
				nutritional_vals = :nutritional_vals,
				preparation_time = :preparation_time,
				servings         = :servings,
				source           = :source,
				favorite         = :favorite,
				json_data        = :json_data,
				updated_at       = :ts
			WHERE id = :id
			AND owner_id = :owner_id';

	$stmt = $db->prepare($sql);

	$stmt->execute([
		':title'            => $data['title'] ?? null,
		':description'      => $data['description'] ?? null,
		':directions'       => $data['directions'] ?? null,
		':ingredients'      => $data['ingredients'] ?? null,
		':notes'            => $data['notes'] ?? null,
		':nutritional_vals' => $data['nutritionalValues'] ?? null,
		':preparation_time' => $data['preparationTime'] ?? null,
		':servings'         => $data['servings'] ?? null,
		':source'           => $data['source'] ?? null,
		':favorite'         => $favorite,
		':json_data'        => json_encode($data, JSON_UNESCAPED_UNICODE),
		':ts'               => (new DateTimeImmutable())->format('c'),
		':id'               => $id,
		':owner_id'         => $userId
	]);

	if ($stmt->rowCount() === 0) {
		http_response_code(404);
		echo json_encode([
			'error' => 'Rezept nicht gefunden oder keine Berechtigung.'
		], JSON_UNESCAPED_UNICODE);
		exit;
	}


	// Kategorien erneuern
	$db->prepare('DELETE FROM recipe_categories WHERE recipe_id = :id')
	   ->execute([':id' => $id]);

	foreach ($categories as $catName) {
		$catName = trim($catName);
		if ($catName === '') continue;

		// Kategorie holen oder anlegen
		$catId = $db->prepare('SELECT id FROM categories WHERE name = :n');
		$catId->execute([':n' => $catName]);
		$cat = $catId->fetchColumn();

		if ($cat === false) {
			$db->prepare('INSERT INTO categories (name) VALUES (:n)')
			   ->execute([':n' => $catName]);
			$cat = $db->lastInsertId();
		}

		$db->prepare(
			'INSERT OR IGNORE INTO recipe_categories (recipe_id, category_id)
			 VALUES (:rid, :cid)'
		)->execute([
			':rid' => $id,
			':cid' => $cat
		]);
	}

	echo json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'error'   => 'Interner Fehler.',
		'message' => $e->getMessage()
	], JSON_UNESCAPED_UNICODE);
}
