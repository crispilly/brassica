<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use Brassica\Core\Installer;
use Brassica\Core\Router;

try {
    Installer::ensureReady();

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $setupPaths = ['/setup', '/set-admin-password', '/set_admin_password.php'];
    if (Installer::needsSetup() && !in_array($requestPath, $setupPaths, true)) {
        header('Location: /setup');
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    $message = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Brassica – Startfehler</title><link rel="stylesheet" href="/assets/css/app.css"></head><body class="auth-body"><div class="auth-wrapper"><section class="auth-card"><h1>Brassica</h1><h3>Start nicht möglich</h3><p class="auth-message auth-error">' . $message . '</p><p>Prüfe, ob PHP mit PDO SQLite verfügbar ist und der Webserver in das Verzeichnis <code>storage/</code> schreiben darf.</p></section></div></body></html>';
    error_log((string)$e);
    exit;
}

$router = new Router();
$registerRoutes = require base_path('config/routes.php');
$registerRoutes($router);

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    http_response_code(500);
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (str_contains($accept, 'application/json') || str_starts_with((string)($_SERVER['REQUEST_URI'] ?? ''), '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Interner Serverfehler'], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Interner Serverfehler';
    }
    error_log((string)$e);
}
