<?php
declare(strict_types=1);

namespace Brassica\Http;

use Brassica\Core\Database;
use PDO;

final class PublicShareController
{
    public static function recipe(array $params): void
    {
        $token = trim((string)($params['token'] ?? ''));

        if ($token === '') {
            http_response_code(404);
            echo 'Freigabe nicht gefunden.';
            return;
        }

        $db = Database::connection();

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
}
