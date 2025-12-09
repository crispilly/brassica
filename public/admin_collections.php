<?php
// public/admin_collections.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';
$userId = require_login_page();

$currentUserId = require_login_page();

if ($currentUserId !== 1) {
    http_response_code(403);
    echo 'Zugriff verweigert.';
    exit;
}

$db = get_db();

// Alle Sammlungen mit Besitzer und Rezeptanzahl laden
$sql = '
    SELECT
        c.id,
        c.token,
        c.created_at,
        c.owner_id,
        u.username,
        COUNT(cr.recipe_id) AS recipe_count
    FROM collections c
    LEFT JOIN users u ON u.id = c.owner_id
    LEFT JOIN collection_recipes cr ON cr.collection_id = c.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
';
$stmt = $db->query($sql);
$collections = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Sammlungen verwalten</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="app-header">
    <h1>Brassica – Sammlungen verwalten</h1>
    	<nav class="main-nav">
    		<a href="index.php">Übersicht</a>
    		<a href="archive.php">Rezept-Import</a>
    		<a href="editor_new.php">Neues Rezept schreiben</a>
    		<?php if ($userId === 1): ?>
    			<a href="admin_users.php">Benutzerverwaltung</a>
    		<?php endif; ?>
    		<a href="logout.php" class="logout-btn">Logout</a>
    	</nav>
</header>
<main class="app-main">
    <section>
        <h2>Alle geteilten Sammlungen</h2>
        <table class="admin-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Token</th>
                <th>Besitzer</th>
                <th>Anzahl Rezepte</th>
                <th>Angelegt am</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($collections)): ?>
                <tr>
                    <td colspan="6">Keine Sammlungen vorhanden.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($collections as $c): ?>
                    <tr>
                        <td><?= (int)$c['id'] ?></td>
                        <td>
                            <a href="index_open.php?token=<?= urlencode($c['token']) ?>">
                                <code><?= htmlspecialchars($c['token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code>
                            </a>
                        </td>
                        <td>
                            <?php if (!empty($c['username'])): ?>
                                <?= htmlspecialchars($c['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                (ID <?= (int)$c['owner_id'] ?>)
                            <?php else: ?>
                                Unbekannt (ID <?= (int)$c['owner_id'] ?>)
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$c['recipe_count'] ?></td>
                        <td><?= htmlspecialchars((string)$c['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td>
                            <a href="admin_collection_recipes.php?collection_id=<?= (int)$c['id'] ?>">Rezepte ansehen</a>
                            |
                            <form action="admin_collection_delete.php" method="post" style="display:inline" onsubmit="return confirm('Diese Sammlung und die Zuordnungen wirklich löschen?');">
                                <input type="hidden" name="collection_id" value="<?= (int)$c['id'] ?>">
                                <button type="submit">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
 </main>
 </body>
 </html>
