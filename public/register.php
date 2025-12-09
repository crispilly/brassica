<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';
session_start();

$db = get_db();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Bitte Benutzername und Passwort eingeben.';
    } else {
        // prüfen ob existiert
        $check = $db->prepare('SELECT id FROM users WHERE username = :u');
        $check->execute([':u' => $username]);

        if ($check->fetchColumn()) {
            $error = 'Benutzer existiert bereits.';
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
<html lang="de">
<head>
<meta charset="utf-8">
<title>Brassica - Registrierung</title>
<link rel="stylesheet" href="styles.css">
<style>
	body.auth-body {
		min-height: 100vh;
		margin: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		background: #f5f7fa;
		font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	}

	.auth-wrapper {
		width: 100%;
		max-width: 420px;
		padding: 1.5rem;
	}

	.auth-card {
		background: #ffffff;
		border-radius: 0.75rem;
		padding: 2rem;
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        text-align:center;
						 
	}

	.auth-card h1 {
		margin: 0 0 0.5rem 0;
		font-size: 1.6rem;
	}

    .auth-card h3 {
        margin-top:0;
    }

	.auth-subtitle {
		margin: 0 0 1.2rem 0;
		font-size: 0.95rem;
		color: #555;
	}

	.auth-message {
		margin: 0 0 1rem 0;
		padding: 0.6rem 0.8rem;
		border-radius: 0.4rem;
		font-size: 0.9rem;
	}

	.auth-success {
		background: #e8f7e8;
		color: #205c2a;
		border: 1px solid #5fa75f;
	}

	.auth-error {
		background: #ffe5e5;
		color: #8a1f1f;
		border: 1px solid #e08b8b;
	}

	.auth-form {
		margin-top: 0.5rem;
	}

	.auth-field {
		margin-bottom: 0.9rem;
				
						 
	}

	.auth-field label {
		display: block;
		font-weight: 600;
		margin-bottom: 0.25rem;
					
				 
 	}

 	.auth-field input[type="text"],
 	.auth-field input[type="password"] {
 		width: 100%;
 		box-sizing: border-box;
 		padding: 0.55rem 0.7rem;
 		border-radius: 0.35rem;
 		border: 1px solid #c5c5c5;
 		font-size: 1rem;
 	}

 	.auth-field input:focus {
 		outline: none;
 		border-color: #3498db;
 		box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.25);
 	}

 	.auth-submit {
					  
 		width: 100%;
 		padding: 0.6rem 1rem;
 		border-radius: 0.4rem;
 		border: none;
 		background: #1c5e8a;
 		color: #fff;
 		font-size: 1rem;
 		font-weight: 600;
 		cursor: pointer;
 	}

 	.auth-submit:hover {
 		background: #2f89c5;
 	}

 	.auth-footer {
 		margin-top: 0.9rem;
 		font-size: 0.9rem;
 		text-align: center;
 	}

 	.auth-footer a {
 		color: #1c5e8a;
 		text-decoration: none;
 	}

 	.auth-footer a:hover {
 		text-decoration: underline;
 	}
 </style>
</head>
<body class="auth-body">
    <div class="auth-wrapper">
        <section class="auth-card">
            <h1>Brassica</h1><h3>Die Broccoli Web App</h3>
            <p class="auth-subtitle">Erstelle einen <strong>neuen Benutzer</strong> für Brassica.</p>

            <?php if ($error): ?>
                <p class="auth-message auth-error"><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <div class="auth-field">
                    <label for="username">Benutzername</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>

                <div class="auth-field">
                    <label for="password">Passwort</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="auth-submit">Registrieren</button>
            </form>

            <p class="auth-footer">
                <a href="login.php">Zum Login</a>
            </p>
        </section>
    </div>
</body>
</html>
