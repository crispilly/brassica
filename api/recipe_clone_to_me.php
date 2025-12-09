<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
	$userId = require_login();

	$raw = file_get_contents('php://input');
	$data = json_decode($raw, true);

	if (!is_array($data) || !isset($data['id'])) {
		http_response_code(400);
		echo json_encode(['error' => 'id fehlt'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$recipeId = (int)$data['id'];
	if ($recipeId <= 0) {
		http_response_code(400);
		echo json_encode(['error' => 'Ungültige Rezept-ID'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$db = get_db();

	// Originalrezept holen
	$stmt = $db->prepare('SELECT * FROM recipes WHERE id = :id');
	$stmt->execute([':id' => $recipeId]);
	$recipe = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$recipe) {
		http_response_code(404);
		echo json_encode(['error' => 'Rezept nicht gefunden'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	// Wenn bereits eigenes Rezept -> nichts tun
	if ((int)$recipe['owner_id'] === $userId) {
		echo json_encode([
			'success'      => true,
			'imported'     => false,
			'message'      => 'Rezept gehört bereits Dir.',
			'new_recipe_id'=> null,
		], JSON_UNESCAPED_UNICODE);
		return;
	}

	$db->beginTransaction();

	$uuid = bin2hex(random_bytes(16));
	$now  = (new DateTimeImmutable())->format('c');

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
		':owner_id'         => $userId,
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
		':favorite'         => 0,
		':image_name_orig'  => $recipe['image_name_orig'],
		':image_path'       => $recipe['image_path'],
		':json_data'        => $recipe['json_data'],
		':source_type'      => $recipe['source_type'],
		':source_file'      => $recipe['source_file'],
		':created_at'       => $now,
		':updated_at'       => $now,
		':user_id'          => $userId,
		':content_hash'     => $recipe['content_hash'],
	]);

	$newId = (int)$db->lastInsertId();

	// Kategorien übernehmen
	$stmtCats = $db->prepare(
		'INSERT OR IGNORE INTO recipe_categories (recipe_id, category_id)
		 SELECT :new_id, category_id
		 FROM recipe_categories
		 WHERE recipe_id = :old_id'
	);
	$stmtCats->execute([
		':new_id' => $newId,
		':old_id' => $recipeId,
	]);

	$db->commit();

	echo json_encode([
		'success'      => true,
		'imported'     => true,
		'new_recipe_id'=> $newId,
	], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
	if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
		$db->rollBack();
	}
	http_response_code(500);
	echo json_encode([
		'error'   => 'Interner Fehler beim Kopieren.',
		'message' => $e->getMessage(),
	], JSON_UNESCAPED_UNICODE);
}