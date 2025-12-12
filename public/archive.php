<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
$userId = require_login_page();

require_once __DIR__ . '/../i18n.php';

// Sprachumschalter: aktuelle GET-Parameter ohne "lang" als Basis übernehmen
$langBaseParams = $_GET;
unset($langBaseParams['lang']);

if (!empty($langBaseParams)) {
	$langQueryPrefix = http_build_query($langBaseParams) . '&';
} else {
	$langQueryPrefix = '';
}

?><!doctype html>
<html lang="<?php echo htmlspecialchars(current_language(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    <head>
    	<meta charset="utf-8">
    	<title><?php echo htmlspecialchars(t('archive.page_title', 'Rezept-Import – Broccoli'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
    	<meta name="viewport" content="width=device-width, initial-scale=1">
    	<link rel="stylesheet" href="styles.css">
    </head>
    <body>
        <header class="app-header">
        	<div class="app-header-top">
        		<h1><?php echo htmlspecialchars(t('archive.heading', 'Rezept-Import'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
        
        		<?php
        		$availableLanguages = available_languages();
        		if (count($availableLanguages) > 1):
        		?>
        		<div class="language-switch">
        			<?php echo htmlspecialchars(t('auth.language_switch_label', 'Sprache:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			<?php foreach ($availableLanguages as $code): ?>
        				<?php if ($code === current_language()): ?>
        					<strong><?php echo htmlspecialchars(strtoupper($code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
        				<?php else: ?>
        					<a href="?<?php
        						echo htmlspecialchars(
        							$langQueryPrefix . 'lang=' . urlencode($code),
        							ENT_QUOTES | ENT_SUBSTITUTE,
        							'UTF-8'
        						);
        					?>">
        						<?php echo htmlspecialchars(strtoupper($code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        					</a>
        				<?php endif; ?>
        			<?php endforeach; ?>
        		</div>
        		<?php endif; ?>
        	</div>
        
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
        </header>
        
        <main class="app-main">
        
        	<section class="import-box">
        		<h2><?php echo htmlspecialchars(t('archive.upload_heading', '.broccoli-archive oder .broccoli hochladen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
        		<input type="file" id="archive-file" accept=".broccoli,.broccoli-archive">
        		<button id="upload-btn">
        			<?php echo htmlspecialchars(t('archive.upload_button', 'Upload & Analysieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        		</button>
        	</section>
        
        	<section id="archive-results" class="hidden">
        		<h2><?php echo htmlspecialchars(t('archive.results_heading', 'Gefundene Rezepte'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
        
        		<div id="archive-meta"></div>
        
        		<div class="archive-tools">
        			<button id="select-all-btn-top" type="button">
        				<?php echo htmlspecialchars(t('archive.select_all', 'Alle auswählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
        			<button id="select-none-btn-top" type="button">
        				<?php echo htmlspecialchars(t('archive.select_none', 'Alle abwählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
        	 		<button id="import-selected-top" type="button" disabled>
        				<?php echo htmlspecialchars(t('archive.import_selected', 'Ausgewählte importieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
        		</div>
        
        	 	<div class="import-actions">
        	 	</div>
        
        		<div id="recipes-list" class="archive-list"></div>
        
        		<div class="archive-tools">
        			<button id="select-all-btn" type="button">
        				<?php echo htmlspecialchars(t('archive.select_all', 'Alle auswählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
        			<button id="select-none-btn" type="button">
        				<?php echo htmlspecialchars(t('archive.select_none', 'Alle abwählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
        			<button id="import-selected" type="button" disabled>
        				<?php echo htmlspecialchars(t('archive.import_selected', 'Ausgewählte importieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        			</button>
        		</div>
        	</section>
        
        </main>
        <script>
        window.archiveMessages = {
        	select_file_required: <?php echo json_encode(t('archive.select_file_required', 'Bitte eine .broccoli- oder .broccoli-archive-Datei auswählen.'), JSON_UNESCAPED_UNICODE); ?>,
        	upload_error:         <?php echo json_encode(t('archive.upload_error', 'Fehler beim Upload!'), JSON_UNESCAPED_UNICODE); ?>,
        	analyze_error:        <?php echo json_encode(t('archive.analyze_error', 'Archiv / Datei konnte nicht analysiert werden.'), JSON_UNESCAPED_UNICODE); ?>,
        	empty_hint:           <?php echo json_encode(t('archive.empty_hint', 'Keine Rezepte gefunden.'), JSON_UNESCAPED_UNICODE); ?>,
        	title_fallback:       <?php echo json_encode(t('archive.title_fallback', '(ohne Titel)'), JSON_UNESCAPED_UNICODE); ?>,
        	thumb_has_image:      <?php echo json_encode(t('archive.thumb_has_image', 'Bild vorhanden'), JSON_UNESCAPED_UNICODE); ?>,
        	thumb_no_image:       <?php echo json_encode(t('archive.thumb_no_image', 'Kein Bild'), JSON_UNESCAPED_UNICODE); ?>,
        	categories_label:     <?php echo json_encode(t('archive.categories_label', 'Kategorien:'), JSON_UNESCAPED_UNICODE); ?>,
        	preview_link:         <?php echo json_encode(t('archive.preview_link', 'anzeigen'), JSON_UNESCAPED_UNICODE); ?>,
        	duplicate_only_message: <?php echo json_encode(t('archive.duplicate_only_message', 'Rezept existiert bereits. Kein Rezept übernommen.'), JSON_UNESCAPED_UNICODE); ?>,
        	import_done:          <?php echo json_encode(t('archive.import_done', 'Import abgeschlossen. {count} Rezepte übernommen.'), JSON_UNESCAPED_UNICODE); ?>,
        	import_error:         <?php echo json_encode(t('archive.import_error', 'Fehler beim Importieren.'), JSON_UNESCAPED_UNICODE); ?>
        };
        
        function amsg(key, fallback) {
        	if (window.archiveMessages && Object.prototype.hasOwnProperty.call(window.archiveMessages, key)) {
        		return window.archiveMessages[key];
        	}
        	return fallback;
        }
        </script>
        <script src="archive.js"></script>
    </body>
</html>