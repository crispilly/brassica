<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_login_page(): int {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    return (int)$_SESSION['user_id'];
}
