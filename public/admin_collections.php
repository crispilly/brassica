<?php
// public/admin_collections.php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../i18n.php';

$currentUserId = require_login_page();

if ($currentUserId !== 1) {
	http_response_code(403);
	echo htmlspecialchars(t('admin_collections.forbidden', 'Zugriff verweigert.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

$languages   = available_languages();
$currentLang = current_language();
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($currentLang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars(t('admin_collections.page_title', 'Sammlungen verwalten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
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
			<?php echo htmlspecialchars(t('admin_collections.heading', 'Brassica – Sammlungen verwalten'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
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
			<a href="admin_users.php">
				<?php echo htmlspecialchars(t('nav.admin_users', 'Benutzerverwaltung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
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
			<?php echo htmlspecialchars(t('admin_collections.section_heading', 'Alle geteilten Sammlungen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
		</h2>
        <table class="admin-table">
            <thead>
            <tr>
                <th><?php echo htmlspecialchars(t('admin_collections.th_id', 'ID'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(t('admin_collections.th_token', 'Token'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(t('admin_collections.th_owner', 'Besitzer'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(t('admin_collections.th_recipe_count', 'Anzahl Rezepte'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(t('admin_collections.th_created_at', 'Angelegt am'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(t('admin_collections.th_actions', 'Aktionen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($collections)): ?>
                <tr>
                    <td colspan="6">
						<?php echo htmlspecialchars(t('admin_collections.empty', 'Keine Sammlungen vorhanden.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</td>
                </tr>
            <?php else: ?>
                <?php foreach ($collections as $c): ?>
                    <tr>
                        <td><?php echo (int)$c['id']; ?></td>
                        <td>
                            <a href="index_open.php?token=<?php echo urlencode($c['token']); ?>">
                                <code><?php echo htmlspecialchars($c['token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code>
                            </a>
                        </td>
                        <td>
                            <?php if (!empty($c['username'])): ?>
                                <?php echo htmlspecialchars($c['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                (ID <?php echo (int)$c['owner_id']; ?>)
                            <?php else: ?>
                                <?php echo htmlspecialchars(t('admin_collections.owner_unknown', 'Unbekannt'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                (ID <?php echo (int)$c['owner_id']; ?>)
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int)$c['recipe_count']; ?></td>
                        <td><?php echo htmlspecialchars((string)$c['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                        <td>
                            <a href="admin_collection_recipes.php?collection_id=<?php echo (int)$c['id']; ?>">
								<?php echo htmlspecialchars(t('admin_collections.action_view_recipes', 'Rezepte ansehen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
							</a>
                            |
                            <form
								action="admin_collection_delete.php"
								method="post"
								style="display:inline"
								onsubmit="return confirm('<?php echo htmlspecialchars(t('admin_collections.confirm_delete_collection', 'Diese Sammlung und die Zuordnungen wirklich löschen?'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>');"
							>
                                <input type="hidden" name="collection_id" value="<?php echo (int)$c['id']; ?>">
                                <button type="submit">
									<?php echo htmlspecialchars(t('admin_collections.action_delete', 'Löschen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
								</button>
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
