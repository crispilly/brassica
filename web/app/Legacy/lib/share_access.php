<?php
declare(strict_types=1);

function brassica_ensure_recipe_share_table(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS recipe_shares (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            owner_id INTEGER NOT NULL,
            recipe_id INTEGER NOT NULL UNIQUE,
            token TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_recipe_shares_owner_id ON recipe_shares(owner_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_recipe_shares_token ON recipe_shares(token)');
}

function brassica_public_recipe_access(
    PDO $db,
    int $recipeId,
    ?string $shareToken = null,
    ?string $collectionToken = null
): bool {
    if ($recipeId <= 0) {
        return false;
    }

    $shareToken = trim((string)$shareToken);
    if ($shareToken !== '') {
        brassica_ensure_recipe_share_table($db);
        $stmt = $db->prepare(
            'SELECT 1 FROM recipe_shares WHERE recipe_id=:rid AND token=:token LIMIT 1'
        );
        $stmt->execute([
            ':rid' => $recipeId,
            ':token' => $shareToken,
        ]);

        if ($stmt->fetchColumn() !== false) {
            return true;
        }
    }

    $collectionToken = trim((string)$collectionToken);
    if ($collectionToken !== '') {
        $stmt = $db->prepare(
            'SELECT 1
             FROM collections c
             JOIN collection_recipes cr ON cr.collection_id=c.id
             WHERE c.token=:token AND cr.recipe_id=:rid
             LIMIT 1'
        );
        $stmt->execute([
            ':token' => $collectionToken,
            ':rid' => $recipeId,
        ]);

        if ($stmt->fetchColumn() !== false) {
            return true;
        }
    }

    return false;
}

function brassica_user_can_access_recipe(
    PDO $db,
    int $recipeId,
    ?int $userId,
    ?string $shareToken = null,
    ?string $collectionToken = null
): bool {
    if ($recipeId <= 0) {
        return false;
    }

    if ($userId !== null && $userId > 0) {
        $stmt = $db->prepare('SELECT owner_id FROM recipes WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $recipeId]);
        $ownerId = $stmt->fetchColumn();

        if ($ownerId !== false && ((int)$ownerId === $userId || $userId === 1)) {
            return true;
        }
    }

    return brassica_public_recipe_access(
        $db,
        $recipeId,
        $shareToken,
        $collectionToken
    );
}

function brassica_share_query_suffix(
    ?string $shareToken = null,
    ?string $collectionToken = null
): string {
    $params = [];

    $shareToken = trim((string)$shareToken);
    if ($shareToken !== '') {
        $params['share_token'] = $shareToken;
    }

    $collectionToken = trim((string)$collectionToken);
    if ($collectionToken !== '') {
        $params['collection_token'] = $collectionToken;
    }

    return $params ? '&' . http_build_query($params) : '';
}
