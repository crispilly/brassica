<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/session_bootstrap_page.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../i18n.php';


$db = get_db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$error   = null;
$success = false;
$self    = __FILE__;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? '');
	$password  = trim($_POST['password']  ?? '');
	$password2 = trim($_POST['password2'] ?? '');

	if ($username === '') {
		$error = t('set_admin.error_username', 'Bitte einen Benutzernamen angeben.');
	} elseif ($password === '' || $password2 === '') {
		$error = t('set_admin.error_password_fields', 'Bitte beide Passwortfelder ausfüllen.');
	} elseif ($password !== $password2) {
		$error = t('set_admin.error_password_mismatch', 'Passwörter stimmen nicht überein.');
	} else {

		$hash = password_hash($password, PASSWORD_DEFAULT);

		$stmt = $db->prepare('UPDATE users SET username = :u, password_hash = :h WHERE id = 1');
		$stmt->execute([':u' => $username, ':h' => $hash]);

		$success = true;

		// Datei nach erfolgreichem Setzen des Passwortes entfernen
		//@unlink($self);
	}
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(current_language(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
<head>
	<meta charset="utf-8">
	<title><?php echo htmlspecialchars(t('set_admin.page_title', 'Brassica - Admin-Passwort setzen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
	<link rel="stylesheet" href="styles.css">
</head>
<body class="auth-body">
	<div class="auth-wrapper">
		<section class="auth-card">
			<h1><?php echo htmlspecialchars(t('app.title', 'Brassica Rezeptdatenbank'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
			<h3><?php echo htmlspecialchars(t('auth.tagline', 'Die Broccoli Web App'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h3>

			<?php
			$languages = available_languages();
			$currentLang = current_language();
			$currentParams = $_GET;
			?>

			<?php if (!empty($languages) && count($languages) > 1): ?>
				<div class="language-switch">
					<span class="language-switch-label">
						<?php echo htmlspecialchars(t('auth.language_switch_label', 'Sprache:'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</span>
					<?php foreach ($languages as $langCode): ?>
						<?php
							$isActive = ($langCode === $currentLang);
							$params = $currentParams;
							$params['lang'] = $langCode;
							$queryString = http_build_query($params);
						?>
						<?php if ($isActive): ?>
							<strong class="language-switch-link language-switch-link--active">
								<?php echo htmlspecialchars(strtoupper($langCode), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
							</strong>
						<?php else: ?>
							<a href="?<?php echo htmlspecialchars($queryString, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
							class="language-switch-link">
								<?php echo htmlspecialchars(strtoupper($langCode), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<p class="auth-subtitle">
				<?php echo htmlspecialchars(t('set_admin.subtitle', 'Setze Nutzernamen und Passwort für den ersten Benutzer'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				<strong>(User&nbsp;1 <?php echo htmlspecialchars(t('set_admin.subtitle_admin_note', 'ist immer der Admin.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)</strong>
			</p>

			<?php if ($success): ?>
				<p class="auth-message auth-success">
					<?php echo htmlspecialchars(t('set_admin.success', 'Passwort erfolgreich gesetzt. Diese Installationsdatei wurde entfernt.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
				</p>
			<?php endif; ?>

			<?php if ($error): ?>
				<p class="auth-message auth-error">
					<?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
				</p>
			<?php endif; ?>

			<?php if (!$success): ?>
				<form method="post" class="auth-form">
					<div class="auth-field">
						<label for="username"><?php echo htmlspecialchars(t('set_admin.label_username', 'Benutzername'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></label>
						<input type="text" id="username" name="username" required autocomplete="username">
					</div>

					<div class="auth-field">
						<label for="password"><?php echo htmlspecialchars(t('set_admin.label_password', 'Neues Passwort'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></label>
						<input type="password" id="password" name="password" required autocomplete="new-password">
					</div>

					<div class="auth-field">
						<label for="password2"><?php echo htmlspecialchars(t('set_admin.label_password2', 'Passwort wiederholen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></label>
						<input type="password" id="password2" name="password2" required autocomplete="new-password">
					</div>

					<button type="submit" class="auth-submit"><?php echo htmlspecialchars(t('set_admin.submit', 'Passwort setzen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></button>
				</form>
			<?php else: ?>
				<p class="auth-footer">
					<a href="login.php"><?php echo htmlspecialchars(t('set_admin.to_login', 'Zum Login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></a>
				</p>
			<?php endif; ?>
		</section>
	</div>
</body>
</html>
<?php
if ($success) {
    flush();
    @unlink(__FILE__);
}
