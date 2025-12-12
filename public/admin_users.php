<?php
// public/admin_users.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../i18n.php';

$currentUserId = require_login_page();

// einfache Admin-Regel: User mit ID 1 ist Admin
if ($currentUserId !== 1) {
	http_response_code(403);
	echo htmlspecialchars(t('admin_users.forbidden', 'Zugriff verweigert.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	exit;
}

$db = get_db();

// Nutzer + Rezeptanzahl laden
$stmt = $db->query(
	'SELECT u.id, u.username, u.created_at, COUNT(r.id) AS recipe_count
	 FROM users u
	 LEFT JOIN recipes r ON r.owner_id = u.id
	 GROUP BY u.id, u.username, u.created_at
	 ORDER BY u.id'
);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$languages    = available_languages();
$currentLang  = current_language();
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($currentLang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
<head>
	<meta charset="utf-8">
	<title><?php echo htmlspecialchars(t('admin_users.page_title', 'Benutzerverwaltung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
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
			<?php echo htmlspecialchars(t('admin_users.heading', 'Benutzerverwaltung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
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
				<?php foreach ($languages as $code): ?>
					<?php if ($code === $currentLang): ?>
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
		<?php if ($currentUserId === 1): ?>
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
	<section class="view-block">
		<h2>
			<?php echo htmlspecialchars(t('admin_users.section_heading', 'Benutzerübersicht'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</h2>
		<table class="recipe-table">
			<thead>
				<tr>
					<th><?php echo htmlspecialchars(t('admin_users.th_id', 'ID'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars(t('admin_users.th_username', 'Benutzername'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars(t('admin_users.th_created_at', 'Registriert am'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars(t('admin_users.th_recipe_count', 'Anzahl Rezepte'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
					<th><?php echo htmlspecialchars(t('admin_users.th_actions', 'Aktionen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($users as $user): ?>
				<tr>
					<td><?php echo (int)$user['id']; ?></td>
					<td><?php echo htmlspecialchars($user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
					<td><?php echo htmlspecialchars($user['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
					<td><?php echo (int)$user['recipe_count']; ?></td>
					<td>
						<a href="admin_user_recipes.php?user_id=<?php echo (int)$user['id']; ?>">
							<?php echo htmlspecialchars(t('admin_users.action_view_recipes', 'Rezepte ansehen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
						</a>
						<?php if ((int)$user['id'] !== $currentUserId): ?>
							|
							<form
								action="admin_user_delete.php"
								method="post"
								style="display:inline"
								onsubmit="return confirm('<?php echo htmlspecialchars(t('admin_users.confirm_delete_user', 'Diesen Benutzer und alle seine Rezepte wirklich löschen?'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>');"
							>
								<input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
								<button type="submit">
									<?php echo htmlspecialchars(t('admin_users.action_delete_user', 'Benutzer löschen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
								</button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</section>
</main>
</body>
</html>
