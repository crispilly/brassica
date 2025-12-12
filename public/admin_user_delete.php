<?php
// public/admin_user_delete.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../i18n.php';

$currentUserId = require_login_page();

if ($currentUserId !== 1) {
	http_response_code(403);
	echo t('admin_user_delete.forbidden', 'Zugriff verweigert.');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo t('admin_user_delete.method_not_allowed', 'Nur POST erlaubt.');
	exit;
}

$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if ($userId <= 0) {
	http_response_code(400);
	echo t('admin_user_delete.invalid_user_id', 'Ungültige Benutzer-ID.');
	exit;
}

// dich selbst nicht löschen
if ($userId === $currentUserId) {
	http_response_code(400);
	echo t('admin_user_delete.cannot_delete_self', 'Der aktuell angemeldete Benutzer kann nicht gelöscht werden.');
	exit;
}

$db = get_db();

try {
	$db->beginTransaction();

	// Kategorien-Verknüpfungen der Rezepte entfernen
	$stmtCat = $db->prepare(
		'DELETE FROM recipe_categories
		 WHERE recipe_id IN (SELECT id FROM recipes WHERE owner_id = :uid)'
	);
	$stmtCat->execute([':uid' => $userId]);

	// Rezepte löschen
	$stmtRec = $db->prepare('DELETE FROM recipes WHERE owner_id = :uid');
	$stmtRec->execute([':uid' => $userId]);

	// Archive des Nutzers löschen
	$stmtArch = $db->prepare('DELETE FROM archives WHERE owner_id = :uid');
	$stmtArch->execute([':uid' => $userId]);

	// Benutzer löschen
	$stmtUser = $db->prepare('DELETE FROM users WHERE id = :uid');
	$stmtUser->execute([':uid' => $userId]);

	$db->commit();

	header('Location: admin_users.php');
	exit;
} catch (Throwable $e) {
	if ($db->inTransaction()) {
		$db->rollBack();
	}
	http_response_code(500);
	echo t(
	'admin_user_delete.delete_error_prefix',
	'Fehler beim Löschen des Benutzers: '
) . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
