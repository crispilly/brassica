<?php
// public/admin_collection_recipes.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../i18n.php';

$currentUserId = require_login_page();

if ($currentUserId !== 1) {
	http_response_code(403);
	echo t('admin_collection_recipes.error_forbidden', 'Zugriff verweigert.');
	exit;
}

$collectionId = isset($_GET['collection_id']) ? (int)$_GET['collection_id'] : 0;
if ($collectionId <= 0) {
	http_response_code(400);
	echo t('admin_collection_recipes.error_invalid_id', 'Ungültige Collection-ID.');
	exit;
}

$db = get_db();

// Collection-Infos
$sqlCol = '
    SELECT
        c.id,
        c.token,
        c.created_at,
        c.owner_id,
        u.username
    FROM collections c
    LEFT JOIN users u ON u.id = c.owner_id
    WHERE c.id = :cid
';
$stmtCol = $db->prepare($sqlCol);
$stmtCol->execute([':cid' => $collectionId]);
$collection = $stmtCol->fetch(PDO::FETCH_ASSOC);

if (!$collection) {
	http_response_code(404);
	echo t('admin_collection_recipes.error_not_found', 'Sammlung nicht gefunden.');
	exit;
}

// Rezepte der Sammlung
$sqlRecipes = '
    SELECT
        r.id,
        r.title,
        r.owner_id,
        u.username
    FROM collection_recipes cr
    JOIN recipes r ON r.id = cr.recipe_id
    LEFT JOIN users u ON u.id = r.owner_id
    WHERE cr.collection_id = :cid
    ORDER BY r.title COLLATE NOCASE ASC
';
$stmtRec = $db->prepare($sqlRecipes);
$stmtRec->execute([':cid' => $collectionId]);
$recipes = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

// Sprache
$currentLang = current_language();
$languages   = available_languages();
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($currentLang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
<head>
	<meta charset="utf-8">
	<title>
		<?php
		// "Rezepte der Sammlung #<id>"
		$prefix = t('admin_collection_recipes.page_title_prefix', 'Rezepte der Sammlung #');
		echo htmlspecialchars($prefix . (int)$collection['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		?>
	</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon-256.png" sizes="256x256" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
</head>
<body>
<header class="app-header">
	<h1>
		<?php echo htmlspecialchars(
			t('admin_collection_recipes.heading', 'Rezepte der Sammlung'),
			ENT_QUOTES | ENT_SUBSTITUTE,
			'UTF-8'
		); ?>
	</h1>
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
		<a href="admin_users.php">
			<?php echo htmlspecialchars(t('nav.admin_users', 'Benutzerverwaltung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</a>
		<a href="admin_collections.php">
			<?php echo htmlspecialchars(t('nav.admin_collections', 'Sammlungen verwalten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</a>
		<a href="logout.php" class="logout-btn">
			<?php echo htmlspecialchars(t('nav.logout', 'Logout'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</a>

		<?php if (!empty($languages) && count($languages) > 1): ?>
			<div class="language-switch">
				<span class="language-switch-label">
					<?php echo htmlspecialchars(
						t('auth.language_switch_label', 'Sprache:'),
						ENT_QUOTES | ENT_SUBSTITUTE,
						'UTF-8'
					); ?>
				</span>
				<?php
				$currentParams = $_GET;
				foreach ($languages as $langCode):
					$isActive            = ($langCode === $currentLang);
					$params              = $currentParams;
					$params['lang']      = $langCode;
					$queryString         = http_build_query($params);
					?>
					<?php if ($isActive): ?>
						<strong class="language-switch-current">
							<?php echo htmlspecialchars(strtoupper($langCode), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
						</strong>
					<?php else: ?>
						<a href="?<?php echo htmlspecialchars($queryString, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
						   class="language-switch-link">
							<?php echo htmlspecialchars(strtoupper($langCode), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</nav>
</header>
<main class="app-main">
	<section>
		<h2>
			<?php
			$colPrefix = t('admin_collection_recipes.collection_heading_prefix', 'Sammlung #');
			echo htmlspecialchars($colPrefix . (int)$collection['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			?>
		</h2>
		<p>
			<strong><?php echo htmlspecialchars(t('admin_collection_recipes.label_token', 'Token:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
			<code><?php echo htmlspecialchars($collection['token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code><br>
			<strong><?php echo htmlspecialchars(t('admin_collection_recipes.label_owner', 'Besitzer:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
			<?php if (!empty($collection['username'])): ?>
				<?php echo htmlspecialchars($collection['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				(<?php echo 'ID ' . (int)$collection['owner_id']; ?>)
			<?php else: ?>
				<?php echo htmlspecialchars(t('admin_collection_recipes.label_owner_unknown', 'Unbekannt'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				(<?php echo 'ID ' . (int)$collection['owner_id']; ?>)
			<?php endif; ?>
			<br>
			<strong><?php echo htmlspecialchars(t('admin_collection_recipes.label_created', 'Angelegt am:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
			<?php echo htmlspecialchars((string)$collection['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</p>
	</section>

	<section>
		<h2>
			<?php echo htmlspecialchars(
				t('admin_collection_recipes.recipes_heading', 'Rezepte in dieser Sammlung'),
				ENT_QUOTES | ENT_SUBSTITUTE,
				'UTF-8'
			); ?>
		</h2>
		<table class="admin-table">
			<thead>
			<tr>
				<th><?php echo htmlspecialchars(t('admin_collection_recipes.col_id', 'ID'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
				<th><?php echo htmlspecialchars(t('admin_collection_recipes.col_title', 'Titel'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
				<th><?php echo htmlspecialchars(t('admin_collection_recipes.col_owner', 'Besitzer'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
				<th><?php echo htmlspecialchars(t('admin_collection_recipes.col_actions', 'Aktionen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php if (empty($recipes)): ?>
				<tr>
					<td colspan="4">
						<?php echo htmlspecialchars(
							t('admin_collection_recipes.no_recipes', 'Keine Rezepte in dieser Sammlung.'),
							ENT_QUOTES | ENT_SUBSTITUTE,
							'UTF-8'
						); ?>
					</td>
				</tr>
			<?php else: ?>
				<?php foreach ($recipes as $r): ?>
					<tr>
						<td><?php echo (int)$r['id']; ?></td>
						<td><?php echo htmlspecialchars($r['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
						<td>
							<?php if (!empty($r['username'])): ?>
								<?php echo htmlspecialchars($r['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
								(<?php echo 'ID ' . (int)$r['owner_id']; ?>)
							<?php else: ?>
								<?php echo htmlspecialchars(
									t('admin_collection_recipes.label_owner_unknown', 'Unbekannt'),
									ENT_QUOTES | ENT_SUBSTITUTE,
									'UTF-8'
								); ?>
								(<?php echo 'ID ' . (int)$r['owner_id']; ?>)
							<?php endif; ?>
						</td>
						<td>
							<a href="view.php?id=<?php echo (int)$r['id']; ?>" target="_blank">
								<?php echo htmlspecialchars(
									t('admin_collection_recipes.action_view', 'Anzeigen'),
									ENT_QUOTES | ENT_SUBSTITUTE,
									'UTF-8'
								); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</section>

	<section class="view-actions">
		<a href="admin_collections.php" class="button-link">
			<?php echo htmlspecialchars(
				t('admin_collection_recipes.back_to_collections', 'Zurück zur Sammlungsübersicht'),
				ENT_QUOTES | ENT_SUBSTITUTE,
				'UTF-8'
			); ?>
		</a>
	</section>
</main>
</body>
</html>
