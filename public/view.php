<?php
// public/view.php
// erwartet URL wie view.php?id=123
require_once __DIR__ . '/session_bootstrap_page.php';

$logged_in = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Basis-URL bestimmen
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonicalUrl = $scheme . '://' . $host . '/view.php?id=' . $id;
$baseUrl = $scheme . '://' . $host;

function broccoliTimeToIso8601(?string $time): ?string {
	$time = trim((string)$time);
	if ($time === '') {
		return null;
	}

	$hours = 0;
	$minutes = 0;

	if (preg_match('/(\d+)\s*h/i', $time, $m)) {
		$hours = (int)$m[1];
	}
	if (preg_match('/(\d+)\s*m/i', $time, $m)) {
		$minutes = (int)$m[1];
	}

	if ($hours === 0 && $minutes === 0) {
		return null;
	}

	$iso = 'PT';
	if ($hours > 0) {
		$iso .= $hours . 'H';
	}
	if ($minutes > 0) {
		$iso .= $minutes . 'M';
	}
	return $iso;
}

$jsonLd = null;

if ($id > 0) {
	try {
		$db = new PDO('sqlite:' . __DIR__ . '/../data/db.sqlite');
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$stmt = $db->prepare('SELECT * FROM recipes WHERE id = :id LIMIT 1');
		$stmt->bindValue(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		$recipe = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($recipe) {
			// Zutaten in Array wandeln
			$ingredients = [];
			if (!empty($recipe['ingredients'])) {
				$lines = preg_split("/\r\n|\r|\n/", (string)$recipe['ingredients']);
				$ingredients = array_values(array_filter(array_map('trim', $lines), 'strlen'));
			}

			// Zubereitung als ein HowToStep
			$directionsText = trim((string)($recipe['directions'] ?? ''));
			$instructions = [];
			if ($directionsText !== '') {
				$instructions[] = [
					'@type' => 'HowToStep',
					'text'  => $directionsText,
				];
			}

			// Nährwerte parsen
			$nutrition = [];
			$servingSize = null;
			$nutriText = (string)($recipe['nutritional_vals'] ?? '');
			if ($nutriText !== '') {
				foreach (preg_split("/\r\n|\r|\n/", $nutriText) as $line) {
					$line = trim($line);
					if ($line === '') {
						continue;
					}
					if (stripos($line, 'Portion:') === 0) {
						$servingSize = trim(substr($line, strlen('Portion:')));
						continue;
					}
					$pos = strpos($line, ':');
					if ($pos === false) {
						continue;
					}
					$label = trim(substr($line, 0, $pos));
					$value = trim(substr($line, $pos + 1));

					switch (mb_strtolower($label)) {
						case 'kalorien':
							$nutrition['calories'] = $value;
							break;
						case 'fett':
							$nutrition['fatContent'] = $value;
							break;
						case 'kohlenhydrate':
							$nutrition['carbohydrateContent'] = $value;
							break;
						case 'eiweiß':
						case 'eiweiss':
							$nutrition['proteinContent'] = $value;
							break;
					}
				}
				if (!empty($nutrition)) {
					$nutrition['@type'] = 'NutritionInformation';
					if ($servingSize !== null && $servingSize !== '') {
						$nutrition['servingSize'] = $servingSize;
					}
				}
			}

			$data = [
				'@context' => 'https://schema.org',
				'@type'    => 'Recipe',
				'name'     => $recipe['title'] ?? '',
			];
			
			// optional, aber nah am LECKER-Schema
			if (!empty($recipe['title'])) {
				$data['headline'] = $recipe['title'];
			}

			if (!empty($recipe['description'])) {
				$data['description'] = $recipe['description'];
			}
			if (!empty($ingredients)) {
				$data['recipeIngredient'] = $ingredients;
			}
			if (!empty($instructions)) {
				$data['recipeInstructions'] = $instructions;
			}
			$isoTime = broccoliTimeToIso8601($recipe['preparation_time'] ?? null);
			if ($isoTime !== null) {
				$data['totalTime'] = $isoTime;
			}
			if (!empty($recipe['servings'])) {
				$data['recipeYield'] = $recipe['servings'];
			}
			if (!empty($nutrition)) {
				$data['nutrition'] = $nutrition;
			}
			if (!empty($recipe['image_path'])) {
				$data['image'] = [
					[
						'@type' => 'ImageObject',
						'url'   => $baseUrl . '/api/image.php?id=' . $id,
					]
				];
			}
			if (!empty($recipe['source'])) {
				$data['mainEntityOfPage'] = $recipe['source'];
			}
			// mainEntityOfPage: echte URL, niemals "ChatGPT" o.ä.
			$data['mainEntityOfPage'] = [
				'@type' => 'WebPage',
				'@id'   => $canonicalUrl,
			];
						
			$jsonLd = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		}
	} catch (Throwable $e) {
		// Bei Fehlern kein JSON-LD ausgeben
		$jsonLd = null;
	}
}
?>

<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title>Rezept ansehen</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
<?php if ($jsonLd !== null): ?>
	<script type="application/ld+json">
<?php echo $jsonLd; ?>

	</script>
<?php endif; ?>

</head>
<body>
<header class="app-header">
	<h1 id="view-title">Rezept</h1>
<?php if ($logged_in): ?>
<nav class="main-nav">
	<a href="index.php">Übersicht</a>
	<a href="archive.php">Rezept-Import</a>
	<a href="editor_new.php">Neues Rezept schreiben</a>
	<a href="logout.php" class="logout-btn">Logout</a>
</nav>
<?php endif; ?>

</header>

<main class="app-main view-main">
 	<div class="view-actions">
 		<?php if ($logged_in): ?>
 			<button type="button" onclick="goBack()">Zurück</button>
 			<button type="button" id="btn-edit-top" onclick="openEditor()">Bearbeiten</button>
 			<button type="button" id="btn-import-top" onclick="importRecipeToMe()">In meine Rezepte übernehmen</button>
 		<?php endif; ?>
 		<button type="button" onclick="downloadBroccoli()">Als Broccoli-Datei herunterladen</button>
 		<button type="button" onclick="copyLink()">Link kopieren</button>
 	</div>

	<section id="view-image" class="view-image hidden"></section>

	<section id="view-meta" class="view-meta hidden"></section>

	<section id="view-ingredients" class="view-block hidden"></section>

	<section id="view-directions" class="view-block hidden"></section>

	<section id="view-notes" class="view-block hidden"></section>

	<section id="view-nutrition" class="view-block hidden"></section>

	<section id="view-source" class="view-block hidden"></section>

 	<div class="view-actions">
 		<?php if ($logged_in): ?>
 			<button type="button" onclick="goBack()">Zurück</button>
 			<button type="button" id="btn-edit-bottom" onclick="openEditor()">Bearbeiten</button>
 			<button type="button" id="btn-import-bottom" onclick="importRecipeToMe()">In meine Rezepte übernehmen</button>
 		<?php endif; ?>
 		<button type="button" onclick="downloadBroccoli()">Als Broccoli-Datei herunterladen</button>
 		<button type="button" onclick="copyLink()">Link kopieren</button>
 	</div>

</main>

<script>
	const RECIPE_ID = <?php echo json_encode($id, JSON_UNESCAPED_UNICODE); ?>;

 	function importRecipeToMe() {
 		if (!RECIPE_ID || RECIPE_ID <= 0) {
 			alert('Ungültige Rezept-ID.');
 			return;
 		}
 		window.importRecipeToMe && window.importRecipeToMe(RECIPE_ID);
 	}

	function copyLink() {
		const url = window.location.href;
		navigator.clipboard.writeText(url)
			.then(() => {
				alert('Link kopiert');
			})
			.catch(() => {
				alert('Fehler beim Kopieren des Links');
			});
	}
</script>
<script src="view.js"></script>

</body>
</html>
