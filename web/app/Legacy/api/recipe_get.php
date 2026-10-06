<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
	if (!isset($_GET['id'])) {
		http_response_code(400);
		echo json_encode(['error' => 'Parameter "id" fehlt.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$id = (int)$_GET['id'];
	if ($id <= 0) {
		http_response_code(400);
		echo json_encode(['error' => 'Ungültige ID.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

    $db = get_db();
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;


	// Multiuser: Rezepte gehören einem Benutzer (owner_id)
	$ownerId = $userId;

	// Rezept + Kategorien laden
	$sql = 'SELECT
	            r.id,
	            r.owner_id,
	            r.title,
	            r.description,
	            r.directions,
	            r.ingredients,
	            r.notes,
	            r.nutritional_vals,
	            r.preparation_time,
	            r.servings,
	            r.source,
	            r.favorite,
	            r.image_path,
	            r.json_data,
	            GROUP_CONCAT(DISTINCT c.name) AS categories
	        FROM recipes r
	        LEFT JOIN recipe_categories rc ON rc.recipe_id = r.id
	        LEFT JOIN categories c ON c.id = rc.category_id
	        WHERE r.id = :id';

	$params = [':id' => $id];

	// if ($ownerId !== null) {
	// 	$sql .= ' AND r.owner_id = :owner_id';
	// 	$params[':owner_id'] = $ownerId;
	// }

	$sql .= ' GROUP BY r.id';

	$stmt = $db->prepare($sql);
	foreach ($params as $k => $v) {
		if ($k === ':id') {
			$stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
		} else {
			$stmt->bindValue($k, $v, PDO::PARAM_STR);
		}
	}
	$stmt->execute();

	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if ($row === false) {
		http_response_code(404);
		echo json_encode(['error' => 'Rezept nicht gefunden.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$jsonData = null;
	try {
		$jsonData = json_decode((string)$row['json_data'], true, 512, JSON_THROW_ON_ERROR);
	} catch (Throwable $e) {
		$jsonData = null;
	}

	$categories = [];
	if ($row['categories'] !== null && $row['categories'] !== '') {
		foreach (explode(',', $row['categories']) as $name) {
			$name = trim($name);
			if ($name !== '') {
				$categories[] = ['name' => $name];
			}
		}
	}

	echo json_encode([
		'id'               => (int)$row['id'],
		'owner_id'         => isset($row['owner_id']) ? (int)$row['owner_id'] : null,
		'is_owner'         => ($currentUserId !== null && isset($row['owner_id']) && (int)$row['owner_id'] === $currentUserId),
		'title'            => $row['title'],
		'description'      => $row['description'],
		'directions'       => $row['directions'],
		'ingredients'      => $row['ingredients'],
		'notes'            => $row['notes'],
		'nutritional_vals' => $row['nutritional_vals'],
		'preparation_time' => $row['preparation_time'],
		'servings'         => $row['servings'],
		'source'           => $row['source'],
		'favorite'         => (int)$row['favorite'],
		'image_url'        => $row['image_path']
			? ('../api/image.php?id=' . (int)$row['id'])
			: null,
		'categories'       => $categories,
		'json_data'        => $jsonData,
	], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'error'   => 'Interner Fehler.',
		'message' => $e->getMessage(),
	], JSON_UNESCAPED_UNICODE);
}
