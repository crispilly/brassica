<?php
declare(strict_types=1);

function require_login_page(): int
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    return (int)$_SESSION['user_id'];
}
