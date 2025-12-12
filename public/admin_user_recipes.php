<?php
// public/admin_user_recipes.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../i18n.php';

$currentUserId = require_login_page();

// einfache Admin-Regel: User mit ID 1 ist Admin
if ($currentUserId !== 1) {
	http_response_code(403);
	echo t('admin_user_recipes.error_forbidden', 'Zugriff verweigert.');
	exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0) {
	http_response_code(400);
	echo t('admin_user_recipes.error_invalid_user_id', 'Ungültige Benutzer-ID.');
	exit;
}

$db = get_db();

// Nutzer holen
$stmtUser = $db->prepare('SELECT id, username, created_at FROM users WHERE id = :id');
$stmtUser->execute([':id' => $userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
	http_response_code(404);
	echo t('admin_user_recipes.error_user_not_found', 'Benutzer nicht gefunden.');
	exit;
}

// Rezepte des Nutzers holen
$stmtRecipes = $db->prepare(
	'SELECT id, title, created_at, updated_at
	 FROM recipes
	 WHERE owner_id = :owner_id
	 ORDER BY created_at DESC'
);
$stmtRecipes->execute([':owner_id' => $userId]);
$recipes = $stmtRecipes->fetchAll(PDO::FETCH_ASSOC);

// Sprachkontext
$languages   = available_languages();
$currentLang = current_language();
$currentParams = $_GET;
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($currentLang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
<head>
	<meta charset="utf-8">
	<title>
		<?php
			// "Rezepte von <User>"
			echo htmlspecialchars(
				t('admin_user_recipes.page_title_prefix', 'Rezepte von') . ' ' . $user['username'],
				ENT_QUOTES | ENT_SUBSTITUTE,
				'UTF-8'
			);
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
	<div class="app-header-top">
		<h1>
			<?php
				echo htmlspecialchars(
					t('admin_user_recipes.heading_prefix', 'Rezepte von') . ' ' . $user['username'],
					ENT_QUOTES | ENT_SUBSTITUTE,
					'UTF-8'
				);
			?>
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
						$isActive        = ($langCode === $currentLang);
						$params          = $currentParams;
						$params['lang']  = $langCode;
						$queryString     = http_build_query($params);
					?>
					<a href="?<?php echo htmlspecialchars($queryString, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
					   class="language-switch-link<?php echo $isActive ? ' language-switch-link--active' : ''; ?>">
						<?php echo htmlspecialchars(strtoupper($langCode), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</a>
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
		<a href="admin_users.php">
			<?php echo htmlspecialchars(t('nav.admin_users', 'Benutzerverwaltung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</a>
		<a href="logout.php" class="logout-btn">
			<?php echo htmlspecialchars(t('nav.logout', 'Logout'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</a>
	</nav>
</header>

<main class="app-main">
	<section class="view-block">
		<h2>
			<?php
				echo htmlspecialchars(
					t('admin_user_recipes.section_heading', 'Rezepte') . ' (' . count($recipes) . ')',
					ENT_QUOTES | ENT_SUBSTITUTE,
					'UTF-8'
				);
			?>
		</h2>
		<table class="recipe-table">
			<thead>
				<tr>
					<th><?php echo htmlspecialchars(t('admin_user_recipes.col_id', 'ID'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars(t('admin_user_recipes.col_title', 'Titel'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars(t('admin_user_recipes.col_created', 'Erstellt'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars(t('admin_user_recipes.col_updated', 'Zuletzt geändert'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars(t('admin_user_recipes.col_actions', 'Aktionen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($recipes as $recipe): ?>
				<tr>
					<td><?php echo (int)$recipe['id']; ?></td>
					<td><?php echo htmlspecialchars($recipe['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($recipe['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($recipe['updated_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
					<td>
						<a href="view.php?id=<?php echo (int)$recipe['id']; ?>" target="_blank">
							<?php echo htmlspecialchars(t('admin_user_recipes.action_view', 'Ansehen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
						</a>
						|
						<form action="admin_recipe_clone.php" method="post" style="display:inline">
							<input type="hidden" name="recipe_id" value="<?php echo (int)$recipe['id']; ?>">
							<button type="submit">
								<?php echo htmlspecialchars(t('admin_user_recipes.action_clone', 'In meine Rezepte kopieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
							</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($recipes)): ?>
				<tr>
					<td colspan="5">
						<?php echo htmlspecialchars(t('admin_user_recipes.no_recipes', 'Keine Rezepte vorhanden.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
	</section>
	<section class="view-actions">
		<a href="admin_users.php" class="button-link">
			<?php echo htmlspecialchars(t('admin_user_recipes.back_to_overview', 'Zurück zur Benutzerübersicht'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</a>
	</section>
</main>
</body>
</html>
