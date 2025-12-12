<?php
// public/index.php
require_once __DIR__ . '/session_bootstrap_page.php';
$userId = require_login_page();

require_once __DIR__ . '/../i18n.php';
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(current_language(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    <head>
    	<meta charset="utf-8">
    	<title><?php echo htmlspecialchars(t('app.title', 'Brassica Rezeptdatenbank'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
    	<meta name="viewport" content="width=device-width, initial-scale=1">
    	<link rel="stylesheet" href="styles.css">
    	<link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-256.png" sizes="256x256" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
    </head>
    <body>
        <header class="app-header">
        	<div class="app-header-top">
        		<h1><?php echo htmlspecialchars(t('app.title', 'Brassica Rezeptdatenbank'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
        
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
        					<a href="?lang=<?php echo urlencode($code); ?>">
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
    		<section class="toolbar">
    			<div class="toolbar-group">
    				<label for="search-input">
    					<?php echo htmlspecialchars(t('index.search_title_label', 'Suche im Titel:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    				</label>
    				<input
    					type="text"
    					id="search-input"
    					placeholder="<?php echo htmlspecialchars(t('index.search_title_placeholder', 'z.B. Brot, Suppe, Marmelade'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
    				>
    			</div>
    
    			<div class="toolbar-group">
    				<label for="category-select">
    					<?php echo htmlspecialchars(t('index.category_label', 'Kategorie:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    				</label>
    
    				<select id="category-select">
    					<option value="">
    						<?php echo htmlspecialchars(t('index.category_all', 'Alle Kategorien'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    					</option>
    
    				</select>
    			</div>
    
    			<div class="toolbar-group">
    				<button id="reload-button" type="button">
    					<?php echo htmlspecialchars(t('index.reload_button', 'Neu laden'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    				</button>
    
    			</div>
    		</section>
    		<div class="list-tools">
    			<button type="button" id="select-all">
    				<?php echo htmlspecialchars(t('index.select_all', 'Alle auswählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    			<button type="button" id="select-none">
    				<?php echo htmlspecialchars(t('index.select_none', 'Auswahl aufheben'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    			<button type="button" id="delete-selected" disabled>
    				<?php echo htmlspecialchars(t('index.delete_selected', 'Ausgewählte löschen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    			<button type="button" id="share-selected" disabled>
    				<?php echo htmlspecialchars(t('index.share_selected', 'Ausgewählte Rezepte teilen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    			<button type="button" id="export-selected" disabled>
    				<?php echo htmlspecialchars(t('index.export_selected', 'Ausgewählte exportieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    
    		</div>
    
    		<section id="recipes-container" class="recipes-grid">
    			<!-- Karten werden per JS eingefügt -->
    		</section>
    		<div class="list-tools">
    			<button type="button" id="select-all-bottom">
    				<?php echo htmlspecialchars(t('index.select_all', 'Alle auswählen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    			<button type="button" id="select-none-bottom">
    				<?php echo htmlspecialchars(t('index.select_none', 'Auswahl aufheben'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    			<button type="button" id="delete-selected-bottom" disabled>
    				<?php echo htmlspecialchars(t('index.delete_selected', 'Ausgewählte löschen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    			<button type="button" id="share-selected-bottom" disabled>
    				<?php echo htmlspecialchars(t('index.share_selected', 'Ausgewählte Rezepte teilen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    			<button type="button" id="export-selected-bottom" disabled>
    				<?php echo htmlspecialchars(t('index.export_selected', 'Ausgewählte exportieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    
    		</div>
    		<section class="pagination" id="pagination">
    			<button type="button" id="page-prev" disabled>
    				&laquo; <?php echo htmlspecialchars(t('index.page_prev', 'Zurück'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</button>
    			<span id="page-info">
    				<?php echo htmlspecialchars(t('index.page_info_default', 'Seite 1 / 1'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    			</span>
    			<button type="button" id="page-next" disabled>
    				<?php echo htmlspecialchars(t('index.page_next', 'Weiter'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> &raquo;
    			</button>
    		</section>
    	</main>
    	<script>
    	window.appMessages = {
    		page_info_pattern: <?php echo json_encode(t('index.page_info_pattern', 'Seite {page} / {pages}'), JSON_UNESCAPED_UNICODE); ?>,
    		no_recipes: <?php echo json_encode(t('index.no_recipes', 'Keine Rezepte gefunden.'), JSON_UNESCAPED_UNICODE); ?>,
    		load_error: <?php echo json_encode(t('index.load_error', 'Fehler beim Laden der Rezepte.'), JSON_UNESCAPED_UNICODE); ?>,
    
    		card_image_alt_fallback: <?php echo json_encode(t('index.card_image_alt_fallback', 'Rezeptbild'), JSON_UNESCAPED_UNICODE); ?>,
    		card_image_placeholder: <?php echo json_encode(t('index.card_image_placeholder', 'Kein Bild'), JSON_UNESCAPED_UNICODE); ?>,
    		card_title_fallback: <?php echo json_encode(t('index.card_title_fallback', 'Unbenanntes Rezept'), JSON_UNESCAPED_UNICODE); ?>,
    		card_category_none: <?php echo json_encode(t('index.card_category_none', 'Keine Kategorie'), JSON_UNESCAPED_UNICODE); ?>,
    		category_all: <?php echo json_encode(t('index.category_all', 'Alle Kategorien'), JSON_UNESCAPED_UNICODE); ?>,
    
    		delete_confirm_pattern: <?php echo json_encode(t('index.delete_confirm_pattern', 'Sollen wirklich {count} Rezept(e) gelöscht werden?'), JSON_UNESCAPED_UNICODE); ?>,
    		delete_error: <?php echo json_encode(t('index.delete_error', 'Fehler beim Löschen der Rezepte.'), JSON_UNESCAPED_UNICODE); ?>,
    
    		share_single_copied: <?php echo json_encode(t('index.share_single_copied', 'Link zum Rezept wurde in die Zwischenablage kopiert:\n\n{url}'), JSON_UNESCAPED_UNICODE); ?>,
    		share_collection_copied: <?php echo json_encode(t('index.share_collection_copied', 'Link zur Sammlung wurde in die Zwischenablage kopiert:\n\n{url}'), JSON_UNESCAPED_UNICODE); ?>,
    		share_collection_error: <?php echo json_encode(t('index.share_collection_error', 'Fehler beim Erzeugen des Teilungs-Links.'), JSON_UNESCAPED_UNICODE); ?>,
            load_error_log: <?php echo json_encode(t('index.load_error_log', 'Fehler beim Laden der Rezepte:'), JSON_UNESCAPED_UNICODE); ?>,
            delete_error_log: <?php echo json_encode(t('index.delete_error_log', 'Fehler beim Löschen:'), JSON_UNESCAPED_UNICODE); ?>,
            share_collection_error_log: <?php echo json_encode(t('index.share_collection_error_log', 'Fehler beim Erzeugen der Sammlung:'), JSON_UNESCAPED_UNICODE); ?>,
            clipboard_single_warn_log: <?php echo json_encode(t('index.clipboard_single_warn_log', 'Konnte Link nicht in die Zwischenablage kopiert werden:'), JSON_UNESCAPED_UNICODE); ?>,
            clipboard_collection_warn_log: <?php echo json_encode(t('index.clipboard_collection_warn_log', 'Konnte Link nicht in die Zwischenablage kopieren:'), JSON_UNESCAPED_UNICODE); ?>
    	};
    	</script>
    	<script src="app.js"></script>
    </body>
</html>
