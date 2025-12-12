<?php
// public/admin_recipe_clone.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../i18n.php';

$currentUserId = require_login_page();

if ($currentUserId !== 1) {
	http_response_code(403);
	echo t('admin_recipe_clone.forbidden', 'Zugriff verweigert.');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo t('admin_recipe_clone.method_not_allowed', 'Nur POST erlaubt.');
	exit;
}

$recipeId = isset($_POST['recipe_id']) ? (int)$_POST['recipe_id'] : 0;
if ($recipeId <= 0) {
	http_response_code(400);
	echo t('admin_recipe_clone.invalid_recipe_id', 'Ungültige Rezept-ID.');
	exit;
}

$db = get_db();

// Originalrezept holen (inkl. owner_id)
$stmt = $db->prepare('SELECT * FROM recipes WHERE id = :id');
$stmt->execute([':id' => $recipeId]);
$recipe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipe) {
	http_response_code(404);
	echo t('admin_recipe_clone.not_found', 'Rezept nicht gefunden.');
	exit;
}

try {
	$db->beginTransaction();

	// neue UUID generieren – analog BroccoliImporter
	$uuid = bin2hex(random_bytes(16));
	$now  = (new DateTimeImmutable())->format('c');

	// neues Rezept einfügen
	$stmtIns = $db->prepare(
		'INSERT INTO recipes (
			owner_id,
			uuid,
			title,
			description,
			directions,
			ingredients,
			notes,
			nutritional_vals,
			preparation_time,
			servings,
			source,
			favorite,
			image_name_orig,
			image_path,
			json_data,
			source_type,
			source_file,
			created_at,
			updated_at,
			user_id,
			content_hash
		) VALUES (
			:owner_id,
			:uuid,
			:title,
			:description,
			:directions,
			:ingredients,
			:notes,
			:nutritional_vals,
			:preparation_time,
			:servings,
			:source,
			:favorite,
			:image_name_orig,
			:image_path,
			:json_data,
			:source_type,
			:source_file,
			:created_at,
			:updated_at,
			:user_id,
			:content_hash
		)'
	);

	$stmtIns->execute([
		':owner_id'         => $currentUserId,
		':uuid'             => $uuid,
		':title'            => $recipe['title'],
		':description'      => $recipe['description'],
		':directions'       => $recipe['directions'],
		':ingredients'      => $recipe['ingredients'],
		':notes'            => $recipe['notes'],
		':nutritional_vals' => $recipe['nutritional_vals'],
		':preparation_time' => $recipe['preparation_time'],
		':servings'         => $recipe['servings'],
		':source'           => $recipe['source'],
		// beim Import nicht automatisch als Favorit markieren
		':favorite'         => 0,
		':image_name_orig'  => $recipe['image_name_orig'],
		':image_path'       => $recipe['image_path'],
		':json_data'        => $recipe['json_data'],
		':source_type'      => $recipe['source_type'],
		':source_file'      => $recipe['source_file'],
		':created_at'       => $now,
		':updated_at'       => $now,
		':user_id'          => $currentUserId,
		':content_hash'     => $recipe['content_hash'],
	]);

	$newRecipeId = (int)$db->lastInsertId();

	// Kategorien des Originalrezepts übernehmen
	$stmtCats = $db->prepare(
		'INSERT OR IGNORE INTO recipe_categories (recipe_id, category_id)
		 SELECT :new_id, category_id
		 FROM recipe_categories
		 WHERE recipe_id = :old_id'
	);
	$stmtCats->execute([
		':new_id' => $newRecipeId,
		':old_id' => $recipeId,
	]);

	$db->commit();

	// Zurück zur Rezeptliste des ursprünglichen Benutzers
	$ownerId = (int)$recipe['owner_id'];
	header('Location: admin_user_recipes.php?user_id=' . $ownerId);
	exit;
} catch (Throwable $e) {
	if ($db->inTransaction()) {
		$db->rollBack();
	}
	http_response_code(500);
	echo t(
		'admin_recipe_clone.clone_error_prefix',
		'Fehler beim Kopieren des Rezepts: '
	) . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
