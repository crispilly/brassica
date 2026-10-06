<?php
// public/admin_collection_delete.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../i18n.php';

$currentUserId = require_login_page();

if ($currentUserId !== 1) {
	http_response_code(403);
	echo t('admin_collection_delete.error_forbidden', 'Zugriff verweigert.');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo t('admin_collection_delete.error_method_not_allowed', 'Nur POST-Anfragen sind erlaubt.');
	exit;
}

$collectionId = isset($_POST['collection_id']) ? (int)$_POST['collection_id'] : 0;
if ($collectionId <= 0) {
	http_response_code(400);
	echo t('admin_collection_delete.error_invalid_id', 'Ungültige Collection-ID.');
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
	$prefix  = t('admin_collection_delete.error_delete_failed_prefix', 'Fehler beim Löschen der Sammlung: ');
	$message = $prefix . $e->getMessage();
	echo htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
