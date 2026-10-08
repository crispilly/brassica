<?php
declare(strict_types=1);

namespace Brassica\Http;

use Brassica\Core\Database;
use PDO;

final class PublicShareController
{
    public static function collectionRecipe(array $params): void
    {
        $token = trim((string)($params['token'] ?? ''));
        $recipeId = (int)($params['id'] ?? 0);

        if ($token === '' || $recipeId <= 0) {
            http_response_code(404);
            echo 'Freigabe nicht gefunden.';
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT 1
             FROM collections c
             JOIN collection_recipes cr ON cr.collection_id=c.id
             WHERE c.token=:token AND cr.recipe_id=:rid
             LIMIT 1'
        );
        $stmt->execute([
            ':token' => $token,
            ':rid' => $recipeId,
        ]);

        if ($stmt->fetchColumn() === false) {
            http_response_code(404);
            echo 'Diese Rezeptfreigabe wurde nicht gefunden.';
            return;
        }

        $_GET['id'] = (string)$recipeId;
        $_GET['collection_token'] = $token;

        LegacyController::page('view');
    }

    public static function recipe(array $params): void
    {
        $token = trim((string)($params['token'] ?? ''));

        if ($token === '') {
            http_response_code(404);
            echo 'Freigabe nicht gefunden.';
            return;
        }

        $db = Database::connection();

        self::ensureRecipeShareTable($db);

        $stmt = $db->prepare(
            'SELECT recipe_id FROM recipe_shares WHERE token=:token LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $recipeId = $stmt->fetchColumn();

        if ($recipeId === false) {
            http_response_code(404);
            echo 'Diese Rezeptfreigabe wurde nicht gefunden.';
            return;
        }

        $_GET['id'] = (string)(int)$recipeId;
        $_GET['share_token'] = $token;

        LegacyController::page('view');
    }

    public static function recipeDownload(array $params): void
    {
        $token = trim((string)($params['token'] ?? ''));

        if ($token === '') {
            http_response_code(404);
            echo 'Freigabe nicht gefunden.';
            return;
        }

        $db = Database::connection();
        self::ensureRecipeShareTable($db);

        $stmt = $db->prepare('SELECT recipe_id FROM recipe_shares WHERE token=:token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $recipeId = $stmt->fetchColumn();

        if ($recipeId === false) {
            http_response_code(404);
            echo 'Diese Rezeptfreigabe wurde nicht gefunden.';
            return;
        }

        $_GET['id'] = (string)(int)$recipeId;
        $_GET['download'] = '1';
        $_GET['share_token'] = $token;
        LegacyController::page('editor');
    }

    public static function collectionRecipes(array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $token = trim((string)($params['token'] ?? ''));
        if ($token === '') {
            http_response_code(404);
            echo json_encode(['error' => 'Freigabe nicht gefunden.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT r.id, r.title
             FROM collections c
             JOIN collection_recipes cr ON cr.collection_id=c.id
             JOIN recipes r ON r.id=cr.recipe_id
             WHERE c.token=:token
             ORDER BY r.title COLLATE NOCASE, r.id'
        );
        $stmt->execute([':token' => $token]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$items) {
            $exists = $db->prepare('SELECT 1 FROM collections WHERE token=:token LIMIT 1');
            $exists->execute([':token' => $token]);
            if ($exists->fetchColumn() === false) {
                http_response_code(404);
                echo json_encode(['error' => 'Diese Sammlung wurde nicht gefunden.'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        echo json_encode([
            'items' => array_map(static fn(array $row): array => [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
            ], $items),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function collectionRecipeDownload(array $params): void
    {
        $token = trim((string)($params['token'] ?? ''));
        $recipeId = (int)($params['id'] ?? 0);

        if ($token === '' || $recipeId <= 0) {
            http_response_code(404);
            echo 'Freigabe nicht gefunden.';
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT 1
             FROM collections c
             JOIN collection_recipes cr ON cr.collection_id=c.id
             WHERE c.token=:token AND cr.recipe_id=:rid
             LIMIT 1'
        );
        $stmt->execute([
            ':token' => $token,
            ':rid' => $recipeId,
        ]);

        if ($stmt->fetchColumn() === false) {
            http_response_code(404);
            echo 'Diese Rezeptfreigabe wurde nicht gefunden.';
            return;
        }

        $_GET['id'] = (string)$recipeId;
        $_GET['download'] = '1';
        $_GET['collection_token'] = $token;
        LegacyController::page('editor');
    }

    private static function ensureRecipeShareTable(PDO $db): void
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
    }
}
