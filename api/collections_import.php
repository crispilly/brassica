<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
	$userId = require_login();

	$raw = file_get_contents('php://input');
	$data = json_decode($raw, true);

	if (!is_array($data) || empty($data['token']) || !isset($data['ids']) || !is_array($data['ids'])) {
		http_response_code(400);
		echo json_encode(['error' => 'token oder ids fehlen/ungültig'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$token = trim((string)$data['token']);
	$ids   = array_values(array_unique(array_map('intval', $data['ids'])));
	$ids   = array_filter($ids, static fn (int $v) => $v > 0);

	if ($token === '' || empty($ids)) {
		http_response_code(400);
		echo json_encode(['error' => 'Ungültiger Token oder leere ID-Liste'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$db = get_db();

	// Sammlung bestimmen
	$stmtCol = $db->prepare('SELECT id FROM collections WHERE token = :token');
	$stmtCol->execute([':token' => $token]);
	$collection = $stmtCol->fetch(PDO::FETCH_ASSOC);

	if (!$collection) {
		http_response_code(404);
		echo json_encode(['error' => 'Sammlung nicht gefunden'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$collectionId = (int)$collection['id'];

	// Nur Rezepte importieren, die wirklich zur Sammlung gehören
	$placeholders = implode(',', array_fill(0, count($ids), '?'));
	$sqlValid = "SELECT recipe_id
	             FROM collection_recipes
	             WHERE collection_id = ?
	               AND recipe_id IN ($placeholders)";
	$stmtValid = $db->prepare($sqlValid);
	$stmtValid->execute(array_merge([$collectionId], $ids));

	$validIds = [];
	while ($row = $stmtValid->fetch(PDO::FETCH_ASSOC)) {
		$validIds[] = (int)$row['recipe_id'];
	}
	$validIds = array_values(array_unique($validIds));

	if (empty($validIds)) {
		echo json_encode([
			'success'        => false,
			'imported'       => 0,
			'skipped_own'    => 0,
			'skipped_missing'=> count($ids),
			'message'        => 'Keine passenden Rezepte in der Sammlung gefunden.',
		], JSON_UNESCAPED_UNICODE);
		return;
	}

	$imported     = 0;
	$skippedOwn   = 0;
	$skippedOther = 0;

	$db->beginTransaction();

	$stmtRecipe = $db->prepare('SELECT * FROM recipes WHERE id = :id');
	$stmtInsert = $db->prepare(
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

	$stmtCats = $db->prepare(
		'INSERT OR IGNORE INTO recipe_categories (recipe_id, category_id)
		 SELECT :new_id, category_id
		 FROM recipe_categories
		 WHERE recipe_id = :old_id'
	);

	$now = (new DateTimeImmutable())->format('c');

	foreach ($validIds as $rid) {
		$stmtRecipe->execute([':id' => $rid]);
		$recipe = $stmtRecipe->fetch(PDO::FETCH_ASSOC);

		if (!$recipe) {
			$skippedOther++;
			continue;
		}

		if ((int)$recipe['owner_id'] === $userId) {
			$skippedOwn++;
			continue;
		}

		$uuid = bin2hex(random_bytes(16));

		$stmtInsert->execute([
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

		$stmtCats->execute([
			':new_id' => $newId,
			':old_id' => $rid,
		]);

		$imported++;
	}

	$db->commit();

	echo json_encode([
		'success'         => true,
		'imported'        => $imported,
		'skipped_own'     => $skippedOwn,
		'skipped_missing' => $skippedOther,
	], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
	if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
		$db->rollBack();
	}
	http_response_code(500);
	echo json_encode([
		'error'   => 'Interner Fehler beim Import.',
		'message' => $e->getMessage(),
	], JSON_UNESCAPED_UNICODE);
}
