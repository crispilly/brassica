<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_login(): int {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'not logged in'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return (int)$_SESSION['user_id'];
}
