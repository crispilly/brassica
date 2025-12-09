<?php
// public/admin_collection_delete.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';

$currentUserId = require_login_page();

if ($currentUserId !== 1) {
 http_response_code(403);
 echo 'Zugriff verweigert.';
 exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
 http_response_code(405);
 echo 'Nur POST-Anfragen sind erlaubt.';
 exit;
}

$collectionId = isset($_POST['collection_id']) ? (int)$_POST['collection_id'] : 0;
if ($collectionId <= 0) {
 http_response_code(400);
 echo 'Ungültige Collection-ID.';
 exit;
}

$db = get_db();

try {
 $db->beginTransaction();

 $stmtRel = $db->prepare('DELETE FROM collection_recipes WHERE collection_id = :cid');
 $stmtRel->execute([':cid' => $collectionId]);

 $stmtCol = $db->prepare('DELETE FROM collections WHERE id = :cid');
 $stmtCol->execute([':cid' => $collectionId]);

 $db->commit();

 header('Location: admin_collections.php');
 exit;
} catch (Throwable $e) {
 if ($db->inTransaction()) {
     $db->rollBack();
 }
 http_response_code(500);
 echo 'Fehler beim Löschen der Sammlung: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
