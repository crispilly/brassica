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

	if ($username === '' || $password === '') {
		$error = t('auth.register_error_missing_fields', 'Bitte Benutzername und Passwort eingeben.');
	} else {
		// prüfen ob existiert
		$check = $db->prepare('SELECT id FROM users WHERE username = :u');
		$check->execute([':u' => $username]);

		if ($check->fetchColumn()) {
			$error = t('auth.register_error_user_exists', 'Benutzer existiert bereits.');
		} else {
			$hash = password_hash($password, PASSWORD_DEFAULT);

			$stmt = $db->prepare('
				INSERT INTO users (username, password_hash, created_at)
				VALUES (:u, :p, datetime("now"))
			');
			$stmt->execute([
				':u' => $username,
				':p' => $hash,
			]);

			header('Location: login.php?registered=1');
			exit;
		}
	}
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(current_language(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
	<head>
		<meta charset="utf-8">
		<title><?php echo htmlspecialchars(t('auth.register_page_title', 'Brassica - Registrierung'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
		<link rel="stylesheet" href="styles.css">
		<link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-256.png" sizes="256x256" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
	</head>
	<body class="auth-body">
		<div class="auth-wrapper">
			<section class="auth-card">
				<h1>Brassica</h1><h3><?php echo htmlspecialchars(t('auth.tagline', 'Die Broccoli Web App'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h3>
				<p class="auth-subtitle">
					<?php
					// enthält HTML (<strong>), daher kein htmlspecialchars
					echo t('auth.register_subtitle', 'Erstelle einen <strong>neuen Benutzer</strong> für Brassica.');
					?>
				</p>
				<?php if ($error): ?>
					<p class="auth-message auth-error"><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
				<?php endif; ?>

				<form method="post" class="auth-form">
					<div class="auth-field">
						<label for="username">
							<?php echo htmlspecialchars(t('auth.username_label', 'Benutzername'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
						</label>
						<input type="text" id="username" name="username" required autofocus>
					</div>

					<div class="auth-field">
						<label for="password">
							<?php echo htmlspecialchars(t('auth.password_label', 'Passwort'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
						</label>
						<input type="password" id="password" name="password" required>
					</div>

					<button type="submit" class="auth-submit">
						<?php echo htmlspecialchars(t('auth.register_button', 'Registrieren'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</button>
				</form>
				<p class="auth-footer">
					<a href="login.php">
						<?php echo htmlspecialchars(t('auth.to_login_link', 'Zum Login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</a>
				</p>
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