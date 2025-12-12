<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';
session_start();
require_once __DIR__ . '/../i18n.php';
$db = get_db();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($password, $row['password_hash'])) {
        $error = t('auth.invalid_credentials');
    } else {
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['username'] = $username;

        header('Location: index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(current_language(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
	<head>
		<meta charset="utf-8">
		<title><?php echo htmlspecialchars(t('auth.page_title', 'Brassica - Login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
		<link rel="stylesheet" href="styles.css">
		<link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-256.png" sizes="256x256" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
	</head>
    <body class="auth-body">
        <div class="auth-wrapper">
            <section class="auth-card">
                 <h1>Brassica</h1><h3><?php echo htmlspecialchars(t('auth.tagline', 'Die Broccoli Web App'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h3>
                <p class="auth-subtitle"><?php echo htmlspecialchars(t('auth.login_intro', 'Melde Dich an, um Deine Rezepte zu verwalten.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>

                <?php if (isset($_GET['registered'])): ?>
                    <p class="auth-message auth-success"><?php echo htmlspecialchars(t('auth.registration_success', 'Registrierung erfolgreich. Bitte einloggen.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if ($error): ?>
                    <p class="auth-message auth-error"><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                <?php endif; ?>

                <form method="post" class="auth-form">
                    <div class="auth-field">
                        <label for="username"><?php echo htmlspecialchars(t('auth.username_label', 'Benutzername'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></label>
                        <input type="text" id="username" name="username" required autofocus>
                    </div>

                    <div class="auth-field">
                        <label for="password"><?php echo htmlspecialchars(t('auth.password_label', 'Passwort'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></label>
                        <input type="password" id="password" name="password" required>
                    </div>

                     <button type="submit" class="auth-submit"><?php echo htmlspecialchars(t('auth.login_button', 'Login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></button>
				</form>
				<a href="register.php">
					<?php echo htmlspecialchars(t('auth.create_account_link', 'Neuen Benutzer erstellen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</a>
				<?php
					$availableLanguages = available_languages();
					if (count($availableLanguages) > 1):
				?>
				<p class="auth-footer">
					<?php echo htmlspecialchars(t('auth.language_switch_label', 'Sprache:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					<?php foreach ($availableLanguages as $code): ?>
						<?php if ($code === current_language()): ?>
							<strong><?php echo htmlspecialchars(strtoupper($code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
						<?php else: ?>
							<a href="?lang=<?php echo urlencode($code); ?>">
								<?php echo htmlspecialchars(strtoupper($code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</p>
				<?php
					endif;
				?>

            </section>
        </div>
    </body>
</html>
