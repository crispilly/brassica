<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/../lib/share_access.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Nur POST erlaubt.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $userId = require_login();

    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    $recipeId = (int)($data['id'] ?? 0);

    if ($recipeId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Rezept-ID.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = get_db();
    brassica_ensure_recipe_share_table($db);

    $stmt = $db->prepare(
        'SELECT id FROM recipes WHERE id=:id AND owner_id=:uid LIMIT 1'
    );
    $stmt->execute([
        ':id' => $recipeId,
        ':uid' => $userId,
    ]);

    if ($stmt->fetchColumn() === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Rezept nicht gefunden.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare(
        'SELECT token FROM recipe_shares WHERE recipe_id=:rid AND owner_id=:uid LIMIT 1'
    );
    $stmt->execute([
        ':rid' => $recipeId,
        ':uid' => $userId,
    ]);
    $token = $stmt->fetchColumn();

    $now = (new DateTimeImmutable())->format(DATE_ATOM);

    if ($token === false) {
        $token = bin2hex(random_bytes(24));

        $db->prepare(
            'INSERT INTO recipe_shares(owner_id,recipe_id,token,created_at,updated_at)
             VALUES(:uid,:rid,:token,:created,:updated)'
        )->execute([
            ':uid' => $userId,
            ':rid' => $recipeId,
            ':token' => $token,
            ':created' => $now,
            ':updated' => $now,
        ]);
    } else {
        $db->prepare(
            'UPDATE recipe_shares SET updated_at=:updated WHERE recipe_id=:rid AND owner_id=:uid'
        )->execute([
            ':updated' => $now,
            ':rid' => $recipeId,
            ':uid' => $userId,
        ]);
    }

    echo json_encode([
        'success' => true,
        'url' => '/share/recipe/' . rawurlencode((string)$token),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode([
        'error' => 'Interner Fehler.',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
