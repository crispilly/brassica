<?php
declare(strict_types=1);

namespace Brassica\Http;

use Brassica\Core\Database;
use Brassica\Core\Installer;

final class SetupController
{
    public static function handle(): void
    {
        Installer::ensureReady();

        if (!Installer::needsSetup()) {
            header('Location: /login');
            return;
        }

        $error = null;
        $username = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $password2 = (string)($_POST['password2'] ?? '');

            if ($username === '') {
                $error = 'Bitte einen Benutzernamen angeben.';
            } elseif ($password === '' || $password2 === '') {
                $error = 'Bitte beide Passwortfelder ausfüllen.';
            } elseif ($password !== $password2) {
                $error = 'Passwörter stimmen nicht überein.';
            } else {
                $pdo = Database::connection();
                $stmt = $pdo->prepare(
                    'INSERT INTO users (username, password_hash, created_at) VALUES (:username, :password_hash, :created_at)'
                );
                $stmt->execute([
                    ':username' => $username,
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':created_at' => date('c'),
                ]);

                header('Location: /login?installed=1');
                return;
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        $safeUsername = htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeError = $error === null ? '' : htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Brassica 2.0 – Ersteinrichtung</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
</head>
<body class="auth-body">
<div class="auth-wrapper">
    <section class="auth-card">
        <h1>Brassica 2.0</h1>
        <h3>Ersteinrichtung</h3>
        <p class="auth-subtitle">Lege den ersten Benutzer an. Dieser Benutzer ist der Administrator.</p>

        <?php if ($error !== null): ?>
            <p class="auth-message auth-error"><?= $safeError ?></p>
        <?php endif; ?>

        <form method="post" action="/setup" class="auth-form">
            <div class="auth-field">
                <label for="username">Benutzername</label>
                <input type="text" id="username" name="username" value="<?= $safeUsername ?>" required autofocus autocomplete="username">
            </div>
            <div class="auth-field">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
            </div>
            <div class="auth-field">
                <label for="password2">Passwort wiederholen</label>
                <input type="password" id="password2" name="password2" required autocomplete="new-password">
            </div>
            <button type="submit" class="auth-submit">Brassica einrichten</button>
        </form>
    </section>
</div>
</body>
</html>
        <?php
    }
}
