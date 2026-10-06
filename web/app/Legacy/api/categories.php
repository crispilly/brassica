<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $db = get_db();
    $userId = require_login();
    $stmt = $db->prepare('SELECT DISTINCT c.name FROM categories c JOIN recipe_categories rc ON rc.category_id=c.id JOIN recipes r ON r.id=rc.recipe_id WHERE r.owner_id=:uid ORDER BY c.name COLLATE NOCASE');
    $stmt->execute([':uid'=>$userId]);
    echo json_encode(['items'=>array_map(static fn($n)=>['name'=>$n], $stmt->fetchAll(PDO::FETCH_COLUMN))], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['error'=>'Interner Fehler.','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
