<?php
// public/admin_user_recipes.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';

$currentUserId = require_login_page();

if ($currentUserId !== 1) {
	http_response_code(403);
	echo 'Zugriff verweigert.';
	exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0) {
	http_response_code(400);
	echo 'Ungültige Benutzer-ID.';
	exit;
}

$db = get_db();

// Nutzer holen
$stmtUser = $db->prepare('SELECT id, username, created_at FROM users WHERE id = :id');
$stmtUser->execute([':id' => $userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
	http_response_code(404);
	echo 'Benutzer nicht gefunden.';
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
?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title>Rezepte von <?= htmlspecialchars($user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="app-header">
	<h1>Rezepte von <?= htmlspecialchars($user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
	<nav class="main-nav">
		<a href="index.php">Übersicht</a>
		<a href="archive.php">Rezept-Import</a>
		<a href="editor_new.php">Neues Rezept schreiben</a>
		<a href="admin_users.php">Benutzerverwaltung</a>
		<a href="logout.php" class="logout-btn">Logout</a>
	</nav>
</header>

<main class="app-main">
	<section class="view-block">
		<h2>Rezepte (<?= count($recipes) ?>)</h2>
		<table class="recipe-table">
			<thead>
				<tr>
					<th>ID</th>
					<th>Titel</th>
					<th>Erstellt</th>
					<th>Zuletzt geändert</th>
					<th>Aktionen</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($recipes as $recipe): ?>
				<tr>
					<td><?= (int)$recipe['id'] ?></td>
					<td><?= htmlspecialchars($recipe['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
					<td><?= htmlspecialchars($recipe['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
					<td><?= htmlspecialchars($recipe['updated_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
					<td>
						<a href="view.php?id=<?= (int)$recipe['id'] ?>" target="_blank">Ansehen</a>
						|
						<form action="admin_recipe_clone.php" method="post" style="display:inline">
							<input type="hidden" name="recipe_id" value="<?= (int)$recipe['id'] ?>">
							<button type="submit">In meine Rezepte kopieren</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if (empty($recipes)): ?>
				<tr>
					<td colspan="5">Keine Rezepte vorhanden.</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
	</section>
	<section class="view-actions">
		<a href="admin_users.php" class="button-link">Zurück zur Benutzerübersicht</a>
	</section>
</main>
</body>
</html>
