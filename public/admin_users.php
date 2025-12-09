<?php
// public/admin_users.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';

$currentUserId = require_login_page();

// einfache Admin-Regel: User mit ID 1 ist Admin
if ($currentUserId !== 1) {
	http_response_code(403);
	echo 'Zugriff verweigert.';
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
?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title>Benutzerverwaltung</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="app-header">
	<h1>Benutzerverwaltung</h1>
    	<nav class="main-nav">
    		<a href="index.php">Übersicht</a>
    		<a href="archive.php">Rezept-Import</a>
    		<a href="editor_new.php">Neues Rezept schreiben</a>
    		<?php if ($currentUserId === 1): ?>
    			<a href="admin_collections.php">Sammlungen verwalten</a>
    		<?php endif; ?>
    		<a href="logout.php" class="logout-btn">Logout</a>
    	</nav>
</header>

<main class="app-main">
	<section class="view-block">
		<h2>Benutzerübersicht</h2>
		<table class="recipe-table">
			<thead>
				<tr>
					<th>ID</th>
					<th>Benutzername</th>
					<th>Registriert am</th>
					<th>Anzahl Rezepte</th>
					<th>Aktionen</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($users as $user): ?>
				<tr>
					<td><?= (int)$user['id'] ?></td>
					<td><?= htmlspecialchars($user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
					<td><?= htmlspecialchars($user['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
					<td><?= (int)$user['recipe_count'] ?></td>
					<td>
						<a href="admin_user_recipes.php?user_id=<?= (int)$user['id'] ?>">Rezepte ansehen</a>
						<?php if ((int)$user['id'] !== $currentUserId): ?>
							|
							<form action="admin_user_delete.php" method="post" style="display:inline" onsubmit="return confirm('Diesen Benutzer und alle seine Rezepte wirklich löschen?');">
								<input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
								<button type="submit">Benutzer löschen</button>
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
