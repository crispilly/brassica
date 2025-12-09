<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
	$db = get_db();
	$userId = require_login();

	$page   = max(1, (int)($_GET['page']  ?? 1));
	$limit  = max(1, min(100, (int)($_GET['limit'] ?? 30)));
	$offset = ($page - 1) * $limit;

	$category = isset($_GET['category']) && $_GET['category'] !== ''
		? trim((string)$_GET['category'])
		: null;

	$q = isset($_GET['q']) && $_GET['q'] !== ''
		? trim((string)$_GET['q'])
		: null;

	$where  = [];
	$params = [];

	// Multiuser: nur eigene Rezepte
	$where[]        = 'r.owner_id = :uid';
	$params[':uid'] = $userId;

	if ($q !== null) {
		$where[] = 'r.title LIKE :q';
		$params[':q'] = '%' . $q . '%';
	}

	$joinCategory = '';
	if ($category !== null) {
		$joinCategory = 'JOIN recipe_categories rc_filter ON rc_filter.recipe_id = r.id
		                 JOIN categories c_filter ON c_filter.id = rc_filter.category_id';
		$where[] = 'c_filter.name = :category';
		$params[':category'] = $category;
	}

	$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

	// Gesamtanzahl für Pagination
	$countSql = 'SELECT COUNT(DISTINCT r.id)
	             FROM recipes r
	             ' . $joinCategory . '
	             ' . $whereSql;

	$stmt = $db->prepare($countSql);
	foreach ($params as $k => $v) {
		$stmt->bindValue($k, $v, PDO::PARAM_STR);
	}
	$stmt->execute();
	$total = (int)$stmt->fetchColumn();

	// Liste der Rezepte
	$listSql = 'SELECT
	                r.id,
	                r.title,
	                r.image_path,
	                GROUP_CONCAT(DISTINCT c.name) AS categories
	            FROM recipes r
	            LEFT JOIN recipe_categories rc ON rc.recipe_id = r.id
	            LEFT JOIN categories c ON c.id = rc.category_id
	            ' . $joinCategory . '
	            ' . $whereSql . '
	            GROUP BY r.id
	            ORDER BY r.created_at DESC
	            LIMIT :limit OFFSET :offset';

	$stmt = $db->prepare($listSql);

	foreach ($params as $k => $v) {
		$stmt->bindValue($k, $v, PDO::PARAM_STR);
	}
	$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
	$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

	$stmt->execute();

	$items = [];
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$categories = [];
		if ($row['categories'] !== null && $row['categories'] !== '') {
			foreach (explode(',', $row['categories']) as $catName) {
				$catName = trim($catName);
				if ($catName !== '') {
					$categories[] = ['name' => $catName];
				}
			}
		}

		$items[] = [
			'id'         => (int)$row['id'],
			'title'      => $row['title'],
			'image_url'  => $row['image_path']
				? ('../api/image.php?id=' . (int)$row['id'])
				: null,
			'categories' => $categories,
		];
	}


	echo json_encode([
		'items' => $items,
		'total' => $total,
		'page'  => $page,
		'pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
	], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'error'   => 'Interner Fehler.',
		'message' => $e->getMessage(),
	], JSON_UNESCAPED_UNICODE);
}
