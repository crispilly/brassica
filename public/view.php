<?php
// public/view.php
// erwartet URL wie view.php?id=123
require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../i18n.php';

$logged_in = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
$userId = $logged_in ? (int)$_SESSION['user_id'] : null;
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
			// mainEntityOfPage: echte URL
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
$availableLanguages = available_languages();
$currentLanguage    = current_language();
$currentParams      = $_GET;
?>

<!doctype html>
<html lang="<?php echo htmlspecialchars(current_language(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    <head>
    	<meta charset="utf-8">
    	<title><?php echo htmlspecialchars(t('view.page_title', 'Rezept ansehen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
    	<meta name="viewport" content="width=device-width, initial-scale=1">
    	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-256.png" sizes="256x256" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
    	<link rel="stylesheet" href="styles.css">
    <?php if ($jsonLd !== null): ?>
    	<script type="application/ld+json">
    <?php echo $jsonLd; ?>
    	</script>
    <?php endif; ?>
    
    </head>
    <body>
        <header class="app-header">
        	<div class="app-header-top">
        		<h1 id="view-title">
        			<?php echo htmlspecialchars(t('view.heading', 'Rezept'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</h1>
        
            <?php if (count($availableLanguages) > 1): ?>
            	<div class="language-switch">
            		<?php echo htmlspecialchars(t('auth.language_switch_label', 'Sprache:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            		<?php foreach ($availableLanguages as $code): ?>
            			<?php
            				$isActive = ($code === $currentLanguage);
            				$params   = $currentParams;
            				$params['lang'] = $code;
            				$queryString = http_build_query($params);
            			?>
            			<?php if ($isActive): ?>
            				<strong><?php echo htmlspecialchars(strtoupper($code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
            			<?php else: ?>
            				<a href="?<?php echo htmlspecialchars($queryString, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            					<?php echo htmlspecialchars(strtoupper($code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            				</a>
            			<?php endif; ?>
            		<?php endforeach; ?>
            	</div>
            <?php endif; ?>
        	</div>

        	<?php if ($logged_in): ?>
        	<nav class="main-nav">
        		<a href="index.php">
        			<?php echo htmlspecialchars(t('nav.overview', 'Übersicht'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</a>
        		<a href="archive.php">
        			<?php echo htmlspecialchars(t('nav.import', 'Rezept-Import'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</a>
        		<a href="editor_new.php">
        			<?php echo htmlspecialchars(t('nav.new_recipe', 'Neues Rezept schreiben'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</a>
        		<?php if ($userId === 1): ?>
        			<a href="admin_users.php">
        				<?php echo htmlspecialchars(t('nav.admin_users', 'Benutzerverwaltung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</a>
        			<a href="admin_collections.php">
        				<?php echo htmlspecialchars(t('nav.admin_collections', 'Sammlungen verwalten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</a>
        		<?php endif; ?>
        		<a href="logout.php" class="logout-btn">
        			<?php echo htmlspecialchars(t('nav.logout', 'Logout'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</a>
        	</nav>
        	<?php endif; ?>
        </header>
        <main class="app-main view-main">
         	<div class="view-actions">
         		<?php if ($logged_in): ?>
         			<button type="button" onclick="goBack()">
        				<?php echo htmlspecialchars(t('view.back_button', 'Zurück'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
         			<button type="button" id="btn-edit-top" onclick="openEditor()">
        				<?php echo htmlspecialchars(t('view.edit_button', 'Bearbeiten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
         			<button type="button" id="btn-import-top" onclick="importRecipeToMe()">
        				<?php echo htmlspecialchars(t('view.import_button', 'In meine Rezepte übernehmen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
         		<?php endif; ?>
         		<button type="button" onclick="downloadBroccoli()">
        			<?php echo htmlspecialchars(t('view.download_button', 'Als Broccoli-Datei herunterladen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</button>
         		<button type="button" onclick="copyLink()">
        			<?php echo htmlspecialchars(t('view.copy_button', 'Link kopieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</button>
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
         			<button type="button" onclick="goBack()">
        				<?php echo htmlspecialchars(t('view.back_button', 'Zurück'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
         			<button type="button" id="btn-edit-bottom" onclick="openEditor()">
        				<?php echo htmlspecialchars(t('view.edit_button', 'Bearbeiten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
         			<button type="button" id="btn-import-bottom" onclick="importRecipeToMe()">
        				<?php echo htmlspecialchars(t('view.import_button', 'In meine Rezepte übernehmen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
         		<?php endif; ?>
         		<button type="button" onclick="downloadBroccoli()">
        			<?php echo htmlspecialchars(t('view.download_button', 'Als Broccoli-Datei herunterladen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</button>
         		<button type="button" onclick="copyLink()">
        			<?php echo htmlspecialchars(t('view.copy_button', 'Link kopieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</button>
         	</div>
        
        </main>
        
        <script>
        	const RECIPE_ID = <?php echo json_encode($id, JSON_UNESCAPED_UNICODE); ?>;
        
        	window.viewMessages = {
        		import_invalid_id: <?php echo json_encode(t('view.import_invalid_id', 'Ungültige Rezept-ID.'), JSON_UNESCAPED_UNICODE); ?>,
        		copy_success: <?php echo json_encode(t('view.copy_success', 'Link kopiert'), JSON_UNESCAPED_UNICODE); ?>,
        		copy_error: <?php echo json_encode(t('view.copy_error', 'Fehler beim Kopieren des Links'), JSON_UNESCAPED_UNICODE); ?>,
        
        		load_error: <?php echo json_encode(t('view.load_error', 'Fehler beim Laden des Rezepts.'), JSON_UNESCAPED_UNICODE); ?>,
        		load_error_log: <?php echo json_encode(t('view.load_error_log', 'Fehler beim Laden des Rezepts:'), JSON_UNESCAPED_UNICODE); ?>,
        
        		title_fallback: <?php echo json_encode(t('view.title_fallback', 'Rezept'), JSON_UNESCAPED_UNICODE); ?>,
        
        		meta_category_label: <?php echo json_encode(t('view.meta_category_label', 'Kategorie:'), JSON_UNESCAPED_UNICODE); ?>,
        		meta_time_label: <?php echo json_encode(t('view.meta_time_label', 'Zeit:'), JSON_UNESCAPED_UNICODE); ?>,
        		meta_servings_label: <?php echo json_encode(t('view.meta_servings_label', 'Portionen:'), JSON_UNESCAPED_UNICODE); ?>,
        
        		section_ingredients: <?php echo json_encode(t('view.section_ingredients', 'Zutaten'), JSON_UNESCAPED_UNICODE); ?>,
        		section_directions: <?php echo json_encode(t('view.section_directions', 'Zubereitung'), JSON_UNESCAPED_UNICODE); ?>,
        		section_notes: <?php echo json_encode(t('view.section_notes', 'Notizen'), JSON_UNESCAPED_UNICODE); ?>,
        		section_nutrition: <?php echo json_encode(t('view.section_nutrition', 'Nährwerte'), JSON_UNESCAPED_UNICODE); ?>,
        		section_source: <?php echo json_encode(t('view.section_source', 'Quelle'), JSON_UNESCAPED_UNICODE); ?>,
        
        		login_required_import: <?php echo json_encode(t('view.login_required_import', 'Bitte melde Dich an, um das Rezept zu übernehmen.'), JSON_UNESCAPED_UNICODE); ?>,
        		import_failed_prefix: <?php echo json_encode(t('view.import_failed_prefix', 'Import fehlgeschlagen: '), JSON_UNESCAPED_UNICODE); ?>,
        		import_unknown_error: <?php echo json_encode(t('view.import_unknown_error', 'Unbekannter Fehler'), JSON_UNESCAPED_UNICODE); ?>,
        		import_success: <?php echo json_encode(t('view.import_success', 'Rezept wurde in Deine Rezepte übernommen.'), JSON_UNESCAPED_UNICODE); ?>,
        		import_already_owned: <?php echo json_encode(t('view.import_already_owned', 'Rezept gehört bereits Dir.'), JSON_UNESCAPED_UNICODE); ?>,
        		import_error_log: <?php echo json_encode(t('view.import_error_log', 'Fehler beim Einzelimport:'), JSON_UNESCAPED_UNICODE); ?>,
        		import_generic_error: <?php echo json_encode(t('view.import_generic_error', 'Fehler beim Übernehmen des Rezepts.'), JSON_UNESCAPED_UNICODE); ?>
        	};
        
        
        	function vmsg(key, fallback) {
        		if (window.viewMessages && Object.prototype.hasOwnProperty.call(window.viewMessages, key)) {
        			return window.viewMessages[key];
        		}
        		return fallback;
        	}
        
         	function importRecipeToMe() {
         		if (!RECIPE_ID || RECIPE_ID <= 0) {
         			alert(vmsg('import_invalid_id', 'Ungültige Rezept-ID.'));
         			return;
         		}
         		window.importRecipeToMe && window.importRecipeToMe(RECIPE_ID);
         	}
        
        	function copyLink() {
        		const url = window.location.href;
        		navigator.clipboard.writeText(url)
        			.then(() => {
        				alert(vmsg('copy_success', 'Link kopiert'));
        			})
        			.catch(() => {
        				alert(vmsg('copy_error', 'Fehler beim Kopieren des Links'));
        			});
        	}
        </script>
        <script src="view.js"></script>
    </body>
</html>
