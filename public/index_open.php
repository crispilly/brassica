<?php
// public/index_open.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../i18n.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

if ($token === '') {
	http_response_code(400);
	?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(current_language(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
<head>
	<meta charset="utf-8">
	<title><?php echo htmlspecialchars(t('index_open.page_title_error', 'Geteilte Rezept-Sammlung – Fehler'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
	<link rel="icon" href="/favicon-256.png" sizes="256x256" type="image/png">
	<link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
</head>
<body>
	<header class="app-header">
		<h1><?php echo htmlspecialchars(t('index_open.heading', 'Geteilte Rezept-Sammlung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
    	<nav class="main-nav">
    		<?php if (!isset($_SESSION['user_id'])): ?>
    			<a href="login.php">
					<?php echo htmlspecialchars(t('auth.login_button', 'Login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</a>
    		<?php else: ?>
				<a href="index.php">
					<?php echo htmlspecialchars(t('index_open.nav_own_recipes', 'Eigene Rezepte'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</a>
				<a href="logout.php">
					<?php echo htmlspecialchars(t('nav.logout', 'Logout'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</a>
    		<?php endif; ?>
    	</nav>
	</header>
	<main class="app-main">
		<p>
			<?php echo htmlspecialchars(t('index_open.error_invalid_token', 'Kein gültiger Sammlungs-Token übergeben.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</p>
	</main>
</body>
</html>
<?php
	exit;
}

$db = get_db();
$stmt = $db->prepare('SELECT id FROM collections WHERE token = :token');
$stmt->execute([':token' => $token]);
$collection = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$collection) {
	http_response_code(404);
	?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(current_language(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
<head>
	<meta charset="utf-8">
	<title><?php echo htmlspecialchars(t('index_open.page_title_not_found', 'Geteilte Rezept-Sammlung – Nicht gefunden'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
    	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-256.png" sizes="256x256" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
</head>
<body>
	<header class="app-header">
		<h1><?php echo htmlspecialchars(t('index_open.heading', 'Geteilte Rezept-Sammlung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
    	<nav class="main-nav">
    		<?php if (!isset($_SESSION['user_id'])): ?>
    			<a href="login.php">
					<?php echo htmlspecialchars(t('auth.login_button', 'Login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</a>
    		<?php else: ?>
    		    <a href="index.php">
					<?php echo htmlspecialchars(t('index_open.nav_own_recipes', 'Eigene Rezepte'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</a>
    			<a href="logout.php">
					<?php echo htmlspecialchars(t('nav.logout', 'Logout'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</a>
    		<?php endif; ?>
    	</nav>
	</header>
	<main class="app-main">
		<p>
			<?php echo htmlspecialchars(t('index_open.error_not_found', 'Diese Sammlung wurde nicht gefunden oder wurde gelöscht.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</p>
	</main>
</body>
</html>
<?php
	exit;
}

// optionale ID der Sammlung
$collectionId = (int)$collection['id'];

// Sprachumschalter vorbereiten
$languages   = available_languages();
$currentLang = current_language();

// Query-Parameter für Lang-Switcher (Token erhalten)
$currentParams = $_GET;
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($currentLang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
	<head>
		<meta charset="utf-8">
		<title><?php echo htmlspecialchars(t('index_open.page_title', 'Geteilte Rezept-Sammlung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="styles.css">
    	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-256.png" sizes="256x256" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
	</head>
	<body>
		<header class="app-header">
			<div class="app-header-top">
				<h1>
					<?php echo htmlspecialchars(
						t('index_open.heading', 'Geteilte Rezept-Sammlung'),
						ENT_QUOTES | ENT_SUBSTITUTE,
						'UTF-8'
					); ?>
				</h1>

				<?php if (!empty($languages) && count($languages) > 1): ?>
				<div class="language-switch">
					<span class="language-switch-label">
						<?php echo htmlspecialchars(
							t('auth.language_switch_label', 'Sprache:'),
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						); ?>
					</span>
					<?php foreach ($languages as $langCode): ?>
						<?php
							$isActive       = ($langCode === $currentLang);
							$params         = $currentParams;
							$params['lang'] = $langCode; // token bleibt erhalten
							$queryString    = http_build_query($params);
						?>

						<?php if ($isActive): ?>
							<span class="language-switch-link language-switch-link--active">
								<?php echo htmlspecialchars(strtoupper($langCode), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
							</span>
						<?php else: ?>
							<a href="?<?php echo htmlspecialchars($queryString, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
							class="language-switch-link">
								<?php echo htmlspecialchars(strtoupper($langCode), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>

			<nav class="main-nav">
				<?php if (!isset($_SESSION['user_id'])): ?>
					<a href="login.php">
						<?php echo htmlspecialchars(t('auth.login_button', 'Login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</a>
				<?php else: ?>
					<a href="index.php">
						<?php echo htmlspecialchars(t('index_open.nav_own_recipes', 'Eigene Rezepte'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</a>
					<a href="logout.php" class="logout-btn">
						<?php echo htmlspecialchars(t('nav.logout', 'Logout'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</a>
				<?php endif; ?>
			</nav>
		</header>
    	<main class="app-main" data-token="<?php echo htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

    		<?php if (isset($_SESSION['user_id'])): ?>
    			<section class="status-banner status-banner-logged-in">
    				<p>
						<?php echo htmlspecialchars(
							t(
								'index_open.banner_logged_in',
								'Du bist eingeloggt. Du kannst markierte Rezepte übernehmen – sie erscheinen dann in Deinen eigenen Rezepten in der Übersicht.'
							),
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						); ?>
    				</p>
    			</section>
    		<?php else: ?>
    			<section class="status-banner status-banner-guest">
    				<p>
						<?php echo htmlspecialchars(
							t(
								'index_open.banner_guest',
								'Du bist nicht eingeloggt. Du kannst die Rezepte ansehen und als Broccoli-Dateien herunterladen. Um Rezepte in Deinen Account zu übernehmen, melde Dich bitte zuerst an.'
							),
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						); ?>
    				</p>
    			</section>
    		<?php endif; ?>

    		<section class="toolbar">
				<div class="toolbar-group">
					<label for="search-input">
						<?php echo htmlspecialchars(t('index_open.search_label', 'Suche im Titel:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</label>
					<input
						type="text"
						id="search-input"
						placeholder="<?php echo htmlspecialchars(t('index_open.search_placeholder', 'z.B. Brot, Suppe, Marmelade'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
					>
				</div>

				<div class="toolbar-group">
					<label for="category-select">
						<?php echo htmlspecialchars(t('index_open.category_label', 'Kategorie:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</label>
					<select id="category-select">
						<option value="">
							<?php echo htmlspecialchars(t('index_open.category_all', 'Alle Kategorien'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
						</option>
					</select>
				</div>

				<div class="toolbar-group">
					<button id="reload-button" type="button">
						<?php echo htmlspecialchars(t('index_open.reload_button', 'Neu laden'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</button>
				</div>
			</section>

			<div class="list-tools">
				<button type="button" id="select-all">
					<?php echo htmlspecialchars(t('index_open.select_all', 'Alle auswählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</button>
				<button type="button" id="select-none">
					<?php echo htmlspecialchars(t('index_open.select_none', 'Auswahl aufheben'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</button>
				<button type="button" id="export-selected" disabled>
					<?php echo htmlspecialchars(t('index_open.export_selected', 'Ausgewählte exportieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</button>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button type="button" id="import-selected" disabled>
						<?php echo htmlspecialchars(t('index_open.import_selected', 'Markierte Rezepte übernehmen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</button>
                <?php endif; ?>
			</div>

			<section id="recipes-container" class="recipes-grid">
				<!-- Karten werden per JS eingefügt (Sammlungsansicht) -->
			</section>

			<div class="list-tools">
				<button type="button" id="select-all-bottom">
					<?php echo htmlspecialchars(t('index_open.select_all', 'Alle auswählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</button>
				<button type="button" id="select-none-bottom">
					<?php echo htmlspecialchars(t('index_open.select_none', 'Auswahl aufheben'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</button>
				<button type="button" id="export-selected-bottom" disabled>
					<?php echo htmlspecialchars(t('index_open.export_selected', 'Ausgewählte exportieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</button>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button type="button" id="import-selected-bottom" disabled>
						<?php echo htmlspecialchars(t('index_open.import_selected', 'Markierte Rezepte übernehmen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</button>
                <?php endif; ?>
			</div>

			<section class="pagination" id="pagination">
				<button type="button" id="page-prev" disabled>
					<?php echo htmlspecialchars(t('index_open.page_prev', '« Zurück'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</button>
				<span id="page-info">
					<?php echo htmlspecialchars(t('index_open.page_info_default', 'Seite 1 / 1'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</span>
				<button type="button" id="page-next" disabled>
					<?php echo htmlspecialchars(t('index_open.page_next', 'Weiter »'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</button>
			</section>
		</main>
<script>
window.indexOpenMessages = {
	empty_hint:          <?php echo json_encode(t('index.empty_hint', 'Keine Rezepte gefunden.'), JSON_UNESCAPED_UNICODE); ?>,
	page_info:           <?php echo json_encode(t('index.page_info', 'Seite {page} / {pages}'), JSON_UNESCAPED_UNICODE); ?>,
	no_image:            <?php echo json_encode(t('index.no_image', 'Kein Bild'), JSON_UNESCAPED_UNICODE); ?>,
	image_alt_fallback:  <?php echo json_encode(t('index.image_alt_fallback', 'Rezeptbild'), JSON_UNESCAPED_UNICODE); ?>,
	title_fallback:      <?php echo json_encode(t('index.title_fallback', 'Unbenanntes Rezept'), JSON_UNESCAPED_UNICODE); ?>,
	no_category:         <?php echo json_encode(t('index.no_category', 'Keine Kategorie'), JSON_UNESCAPED_UNICODE); ?>,
	category_all:        <?php echo json_encode(t('index.category_all', 'Alle Kategorien'), JSON_UNESCAPED_UNICODE); ?>,

	load_error:          <?php echo json_encode(t('index_open.load_error', 'Fehler beim Laden der Rezepte.'), JSON_UNESCAPED_UNICODE); ?>,
	load_error_log:      <?php echo json_encode(t('index_open.load_error_log', 'Fehler beim Laden der Sammlungs-Rezepte:'), JSON_UNESCAPED_UNICODE); ?>,

	login_required_import:       <?php echo json_encode(t('index_open.login_required_import', 'Bitte melde Dich an, um Rezepte zu übernehmen.'), JSON_UNESCAPED_UNICODE); ?>,
	import_not_successful_prefix:<?php echo json_encode(t('index_open.import_not_successful_prefix', 'Import nicht erfolgreich: '), JSON_UNESCAPED_UNICODE); ?>,
	import_unknown_error:        <?php echo json_encode(t('index_open.import_unknown_error', 'Unbekannter Fehler'), JSON_UNESCAPED_UNICODE); ?>,
	import_result:               <?php echo json_encode(t('index_open.import_result', 'Import abgeschlossen. Übernommen: {imported}, übersprungen (eigene): {skipped_own}.'), JSON_UNESCAPED_UNICODE); ?>,
	import_error_log:            <?php echo json_encode(t('index_open.import_error_log', 'Fehler beim Import der Rezepte:'), JSON_UNESCAPED_UNICODE); ?>,
	import_error:                <?php echo json_encode(t('index_open.import_error', 'Fehler beim Import der Rezepte.'), JSON_UNESCAPED_UNICODE); ?>
};

function iomsg(key, fallback) {
	if (window.indexOpenMessages && Object.prototype.hasOwnProperty.call(window.indexOpenMessages, key)) {
		return window.indexOpenMessages[key];
	}
	return fallback;
}
</script>
<script src="index_open.js"></script>

	</body>
</html>
