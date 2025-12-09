<?php
// public/admin_collection_recipes.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';

$currentUserId = require_login_page();

if ($currentUserId !== 1) {
    http_response_code(403);
    echo 'Zugriff verweigert.';
    exit;
}

$collectionId = isset($_GET['collection_id']) ? (int)$_GET['collection_id'] : 0;
if ($collectionId <= 0) {
    http_response_code(400);
    echo 'Ungültige Collection-ID.';
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
    echo 'Sammlung nicht gefunden.';
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
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Rezepte der Sammlung #<?= (int)$collection['id'] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="app-header">
    <h1>Rezepte der Sammlung</h1>
    <nav class="main-nav">
        <a href="admin_collections.php">Sammlungen</a>
        <a href="admin_users.php">Benutzerverwaltung</a>
        <a href="index.php">Zur Rezeptübersicht</a>
    </nav>
</header>
<main class="app-main">
    <section>
        <h2>Sammlung #<?= (int)$collection['id'] ?></h2>
        <p>
            <strong>Token:</strong>
            <code><?= htmlspecialchars($collection['token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code><br>
            <strong>Besitzer:</strong>
            <?php if (!empty($collection['username'])): ?>
                <?= htmlspecialchars($collection['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                (ID <?= (int)$collection['owner_id'] ?>)
            <?php else: ?>
                Unbekannt (ID <?= (int)$collection['owner_id'] ?>)
            <?php endif; ?>
            <br>
            <strong>Angelegt am:</strong>
            <?= htmlspecialchars((string)$collection['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </p>
    </section>

     <section>
         <h2>Rezepte in dieser Sammlung</h2>
         <table class="admin-table">
             <thead>
             <tr>
                 <th>ID</th>
                 <th>Titel</th>
                 <th>Besitzer</th>
                 <th>Aktionen</th>
             </tr>
             </thead>
             <tbody>
             <?php if (empty($recipes)): ?>
                 <tr>
                     <td colspan="4">Keine Rezepte in dieser Sammlung.</td>
                 </tr>
             <?php else: ?>
                 <?php foreach ($recipes as $r): ?>
                     <tr>
                         <td><?= (int)$r['id'] ?></td>
                         <td><?= htmlspecialchars($r['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                         <td>
                             <?php if (!empty($r['username'])): ?>
                                 <?= htmlspecialchars($r['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                 (ID <?= (int)$r['owner_id'] ?>)
                             <?php else: ?>
                                 Unbekannt (ID <?= (int)$r['owner_id'] ?>)
                             <?php endif; ?>
                         </td>
                         <td>
                             <a href="view.php?id=<?= (int)$r['id'] ?>" target="_blank">Anzeigen</a>
                         </td>
                     </tr>
                 <?php endforeach; ?>
             <?php endif; ?>
             </tbody>
         </table>
     </section>
 
     <section class="view-actions">
         <a href="admin_collections.php" class="button-link">Zurück zur Sammlungsübersicht</a>
     </section>
 </main>
 </body>
 </html>
