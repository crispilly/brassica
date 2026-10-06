<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
	$db = get_db();

	// Session ist da, aber kein Login-Zwang:
	$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

	$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
	if ($token === '') {
		http_response_code(400);
		echo json_encode(['error' => 'Parameter "token" fehlt oder ist leer.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$page   = max(1, (int)($_GET['page']  ?? 1));
	$limit  = max(1, min(100, (int)($_GET['limit'] ?? 30)));
	$offset = ($page - 1) * $limit;

	$search   = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
	$category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';

	// Sammlung holen
	$sqlCol = 'SELECT id, owner_id FROM collections WHERE token = :token';
	$stmtCol = $db->prepare($sqlCol);
	$stmtCol->execute([':token' => $token]);
	$collection = $stmtCol->fetch(PDO::FETCH_ASSOC);

	if (!$collection) {
		http_response_code(404);
		echo json_encode(['error' => 'Sammlung nicht gefunden.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$collectionId = (int)$collection['id'];

	// Basis-SQL für Items
	$where = [];
	$params = [
		':collection_id' => $collectionId,
	];

	// Suchfilter
	if ($search !== '') {
		$where[] = 'r.title LIKE :search';
		$params[':search'] = '%' . $search . '%';
	}

	// Kategorienfilter: Existenz einer Kategorie mit diesem Namen
	if ($category !== '') {
		$where[] =
			'EXISTS (
				SELECT 1
				FROM recipe_categories rc2
				JOIN categories c2 ON c2.id = rc2.category_id
				WHERE rc2.recipe_id = r.id
				  AND c2.name = :category
			)';
		$params[':category'] = $category;
	}

	$whereSql = '';
	if (!empty($where)) {
		$whereSql = ' AND ' . implode(' AND ', $where);
	}

	// Gesamtzahl ermitteln
	$sqlCount = '
		SELECT COUNT(DISTINCT r.id) AS cnt
		FROM collection_recipes cr
		JOIN recipes r ON r.id = cr.recipe_id
		LEFT JOIN recipe_categories rc ON rc.recipe_id = r.id
		LEFT JOIN categories c ON c.id = rc.category_id
		WHERE cr.collection_id = :collection_id
	' . $whereSql;

	$stmtCount = $db->prepare($sqlCount);
	foreach ($params as $k => $v) {
		if ($k === ':collection_id') {
			$stmtCount->bindValue($k, (int)$v, PDO::PARAM_INT);
		} else {
			$stmtCount->bindValue($k, $v, PDO::PARAM_STR);
		}
	}
	$stmtCount->execute();
	$totalRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
	$total = $totalRow ? (int)$totalRow['cnt'] : 0;

	// Wenn nichts da: leere Antwort
	if ($total === 0) {
		echo json_encode([
			'items' => [],
			'total' => 0,
			'page'  => $page,
			'pages' => 0,
		], JSON_UNESCAPED_UNICODE);
		return;
	}

	// Items laden
	$sqlItems = '
		SELECT
			r.id,
			r.title,
			r.image_path,
			r.owner_id,
			GROUP_CONCAT(DISTINCT c.name) AS categories
		FROM collection_recipes cr
		JOIN recipes r ON r.id = cr.recipe_id
		LEFT JOIN recipe_categories rc ON rc.recipe_id = r.id
		LEFT JOIN categories c ON c.id = rc.category_id
		WHERE cr.collection_id = :collection_id
	' . $whereSql . '
		GROUP BY r.id
		ORDER BY r.title COLLATE NOCASE ASC
		LIMIT :limit OFFSET :offset
	';

	$stmtItems = $db->prepare($sqlItems);

	foreach ($params as $k => $v) {
		if ($k === ':collection_id') {
			$stmtItems->bindValue($k, (int)$v, PDO::PARAM_INT);
		} else {
			$stmtItems->bindValue($k, $v, PDO::PARAM_STR);
		}
	}
	$stmtItems->bindValue(':limit', $limit, PDO::PARAM_INT);
	$stmtItems->bindValue(':offset', $offset, PDO::PARAM_INT);

	$stmtItems->execute();

	$items = [];
	while ($row = $stmtItems->fetch(PDO::FETCH_ASSOC)) {
		$cats = [];
		if ($row['categories'] !== null && $row['categories'] !== '') {
			foreach (explode(',', (string)$row['categories']) as $name) {
				$name = trim($name);
				if ($name !== '') {
					$cats[] = ['name' => $name];
				}
			}
		}

		$ownerId = isset($row['owner_id']) ? (int)$row['owner_id'] : null;

		$items[] = [
			'id'         => (int)$row['id'],
			'title'      => $row['title'],
			'image_url'  => $row['image_path']
				? ('../api/image.php?id=' . (int)$row['id'])
				: null,
			'categories' => $cats,
			'owner_id'   => $ownerId,
			'is_owner'   => ($currentUserId !== null && $ownerId === $currentUserId),
		];
	}

	$pages = $total > 0 ? (int)ceil($total / $limit) : 0;

	echo json_encode([
		'items' => $items,
		'total' => $total,
		'page'  => $page,
		'pages' => $pages,
	], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'error'   => 'Interner Fehler.',
		'message' => $e->getMessage(),
	], JSON_UNESCAPED_UNICODE);
}
