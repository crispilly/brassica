<?php
declare(strict_types=1);

class BroccoliImporter
{
	private PDO $db;
	private string $imageDir;

	public function __construct(PDO $db, string $imageDir)
	{
		$this->db = $db;
		$this->imageDir = rtrim($imageDir, '/');
	}

	/**
	 * Einzelne .broccoli-Datei einlesen und in DB speichern.
	 *
	 * @param string      $broccoliPath Pfad zur .broccoli-ZIP-Datei
	 * @param int|null    $ownerId      User-ID oder null
	 * @param string      $sourceType   z.B. 'broccoli' oder 'archive'
	 * @param string|null $sourceFile   Ursprungsdatei (Name oder Pfad)
	 *
	 * @return int ID des angelegten Datensatzes in recipes
	 */
	public function importSingleBroccoli(
		string $broccoliPath,
		?int $ownerId,
		string $sourceType,
		?string $sourceFile
	): int {
		$zip = new ZipArchive();
		if ($zip->open($broccoliPath) !== true) {
			throw new RuntimeException('Kann Broccoli-Archiv nicht öffnen: ' . $broccoliPath);
		}

		// JSON-Datei finden
		$jsonIndex = null;
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = $zip->getNameIndex($i);
			if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'json') {
				$jsonIndex = $i;
				break;
			}
		}

		if ($jsonIndex === null) {
			$zip->close();
			throw new RuntimeException('Keine JSON-Datei in Broccoli-Archiv gefunden.');
		}

		$jsonRaw = $zip->getFromIndex($jsonIndex);
		if ($jsonRaw === false) {
			$zip->close();
			throw new RuntimeException('JSON-Datei konnte nicht gelesen werden.');
		}

		try {
			$data = json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR);
		} catch (Throwable $e) {
			$zip->close();
			throw new RuntimeException('Ungültiges JSON in Broccoli-Archiv: ' . $e->getMessage(), 0, $e);
		}

		// Hash über den JSON-Inhalt berechnen (zur Duplikat-Erkennung)
		$contentHash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));

		// Wenn derselbe User und derselbe Inhalt schon existieren: kein neues Rezept anlegen
		if ($ownerId !== null) {
			$stmt = $this->db->prepare(
				'SELECT id
				 FROM recipes
				 WHERE owner_id = :owner_id
				   AND content_hash = :content_hash
				 LIMIT 1'
			);
			$stmt->execute([
				':owner_id'     => $ownerId,
				':content_hash' => $contentHash,
			]);
			$existingId = $stmt->fetchColumn();
			if ($existingId !== false) {
				$zip->close();
				return (int)$existingId;
			}
		}

		$title         = isset($data['title']) ? (string)$data['title'] : '';
		$imageNameOrig = isset($data['imageName']) ? (string)$data['imageName'] : null;
		$categoriesArr = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];
		$favorite      = !empty($data['favorite']) ? 1 : 0;

		if ($title === '') {
			$zip->close();
			throw new RuntimeException('Feld "title" fehlt oder ist leer.');
		}

		// Bild extrahieren (optional)
		$imagePath = null;
		if ($imageNameOrig !== null && $imageNameOrig !== '') {
			$imgIndex = $zip->locateName($imageNameOrig, ZipArchive::FL_NOCASE);
			if ($imgIndex !== false) {
				$imgData = $zip->getFromIndex($imgIndex);
				if ($imgData !== false) {
					$ext = strtolower(pathinfo($imageNameOrig, PATHINFO_EXTENSION));
					if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
						$ext = 'jpg';
					}

					if (!is_dir($this->imageDir)) {
						if (!mkdir($this->imageDir, 0775, true) && !is_dir($this->imageDir)) {
							$zip->close();
							throw new RuntimeException('Bildverzeichnis konnte nicht erstellt werden: ' . $this->imageDir);
						}
					}

					$newName = bin2hex(random_bytes(16)) . '.' . $ext;
					$target  = $this->imageDir . '/' . $newName;

					if (file_put_contents($target, $imgData) === false) {
						$zip->close();
						throw new RuntimeException('Bild konnte nicht geschrieben werden: ' . $target);
					}

					// Pfad relativ zum Webroot (unter project-root)
					$imagePath = 'data/images/' . $newName;
				}
			}
		}

		$zip->close();

		$now = (new DateTimeImmutable())->format('c');

		$this->db->beginTransaction();

		$stmt = $this->db->prepare(
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
				:content_hash
			)'
		);

		// eigene UUID, falls du später brauchst – basierend auf random_bytes
		$uuid = bin2hex(random_bytes(16));

		$stmt->execute([
			':owner_id'         => $ownerId,
			':uuid'             => $uuid,
			':title'            => $title,
			':description'      => $data['description']       ?? null,
			':directions'       => $data['directions']        ?? null,
			':ingredients'      => $data['ingredients']       ?? null,
			':notes'            => $data['notes']             ?? null,
			':nutritional_vals' => $data['nutritionalValues'] ?? null,
			':preparation_time' => $data['preparationTime']   ?? null,
			':servings'         => $data['servings']          ?? null,
			':source'           => $data['source']            ?? null,
			':favorite'         => $favorite,
			':image_name_orig'  => $imageNameOrig,
			':image_path'       => $imagePath,
			':json_data'        => json_encode($data, JSON_UNESCAPED_UNICODE),
			':source_type'      => $sourceType,
			':source_file'      => $sourceFile,
			':created_at'       => $now,
			':updated_at'       => $now,
			':content_hash'     => $contentHash,
		]);

		$recipeId = (int)$this->db->lastInsertId();

		// Kategorien übernehmen
		foreach ($categoriesArr as $cat) {
			if (!is_array($cat) || !isset($cat['name'])) {
				continue;
			}
			$this->attachCategory($recipeId, (string)$cat['name']);
		}

		$this->db->commit();

		return $recipeId;
	}


	/**
	 * Kategorie holen/erstellen und mit Rezept verknüpfen.
	 */
	private function attachCategory(int $recipeId, string $categoryName): void
	{
		$categoryName = trim($categoryName);
		if ($categoryName === '') {
			return;
		}

		$stmt = $this->db->prepare('SELECT id FROM categories WHERE name = :name');
		$stmt->execute([':name' => $categoryName]);
		$catId = $stmt->fetchColumn();

		if ($catId === false) {
			$stmt = $this->db->prepare('INSERT INTO categories (name) VALUES (:name)');
			$stmt->execute([':name' => $categoryName]);
			$catId = (int)$this->db->lastInsertId();
		} else {
			$catId = (int)$catId;
		}

		$stmt = $this->db->prepare(
			'INSERT OR IGNORE INTO recipe_categories (recipe_id, category_id)
			 VALUES (:recipe_id, :category_id)'
		);
		$stmt->execute([
			':recipe_id'   => $recipeId,
			':category_id' => $catId,
		]);
	}
}
