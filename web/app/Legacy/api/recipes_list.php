<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();
    $userId = require_login();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));
    $offset = ($page - 1) * $limit;
    $q = isset($_GET['q']) && trim((string)$_GET['q']) !== '' ? trim((string)$_GET['q']) : null;

    $rawCategories = $_GET['categories'] ?? ($_GET['category'] ?? []);
    if (!is_array($rawCategories)) {
        $rawCategories = explode(',', (string)$rawCategories);
    }
    $categories = [];
    foreach ($rawCategories as $name) {
        $name = trim((string)$name);
        if ($name !== '') $categories[$name] = true;
    }
    $categories = array_keys($categories);

    $mode = strtolower(trim((string)($_GET['category_mode'] ?? 'or')));
    if (!in_array($mode, ['or', 'and'], true)) $mode = 'or';

    $where = ['r.owner_id = :uid'];
    $params = [':uid' => $userId];
    if ($q !== null) {
        $where[] = 'r.title LIKE :q';
        $params[':q'] = '%' . $q . '%';
    }

    if ($categories !== []) {
        $placeholders = [];
        foreach ($categories as $i => $category) {
            $key = ':cat' . $i;
            $placeholders[] = $key;
            $params[$key] = $category;
        }
        $in = implode(',', $placeholders);
        if ($mode === 'and') {
            $where[] = 'r.id IN (
                SELECT rcx.recipe_id
                FROM recipe_categories rcx
                INNER JOIN categories cx ON cx.id = rcx.category_id
                WHERE cx.name IN (' . $in . ')
                GROUP BY rcx.recipe_id
                HAVING COUNT(DISTINCT cx.name) = ' . count($categories) . '
            )';
        } else {
            $where[] = 'r.id IN (
                SELECT rcx.recipe_id
                FROM recipe_categories rcx
                INNER JOIN categories cx ON cx.id = rcx.category_id
                WHERE cx.name IN (' . $in . ')
            )';
        }
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare('SELECT COUNT(*) FROM recipes r ' . $whereSql);
    foreach ($params as $key => $value) $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        'SELECT r.id, r.title, r.image_path
         FROM recipes r ' . $whereSql . '
         ORDER BY r.created_at DESC
         LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $key => $value) $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $categoryStmt = $db->prepare(
        'SELECT c.name
         FROM recipe_categories rc
         INNER JOIN categories c ON c.id = rc.category_id
         WHERE rc.recipe_id = :recipe_id
         ORDER BY c.name COLLATE NOCASE'
    );

    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categoryStmt->execute([':recipe_id' => (int)$row['id']]);
        $itemCategories = [];
        while (($name = $categoryStmt->fetchColumn()) !== false) {
            $itemCategories[] = ['name' => (string)$name];
        }
        $items[] = [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'image_url' => !empty($row['image_path']) ? '/api/image.php?id=' . (int)$row['id'] : null,
            'categories' => $itemCategories,
        ];
    }

    echo json_encode([
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
        'category_mode' => $mode,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler.', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
