<?php
declare(strict_types=1);

/**
 * Einfache I18n-Schicht für Brassica.
 *
 * - Sprachdateien liegen in project-root/lang/<code>.php
 * - Jede Sprachdatei gibt ein verschachteltes Array zurück.
 * - Zugriff über t('gruppe.key') z.B. t('nav.home')
 */

// Session sicherstellen (auch wenn zusätzlich session_bootstrap_page.php genutzt wird)
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

/**
 * Verfügbare Sprachen automatisch aus dem lang/-Verzeichnis ermitteln.
 *
 * Erwartet Dateien wie:
 *   project-root/lang/de.php
 *   project-root/lang/en.php
 */
function i18n_get_available_languages(): array {
	$langDir = __DIR__ . '/lang';
	$languages = [];

	if (is_dir($langDir)) {
		$files = glob($langDir . '/*.php') ?: [];
		foreach ($files as $file) {
			$code = basename($file, '.php');
			if ($code !== '') {
				$languages[] = $code;
			}
		}
	}

	// Fallback, falls nichts gefunden wird
	if ($languages === []) {
		$languages[] = 'de';
	}

	return $languages;
}

/**
 * Sprache bestimmen:
 * 1. ?lang=xx (wenn erlaubt)
 * 2. Session-Wert
 * 3. Accept-Language Header
 * 4. Fallback
 */
function i18n_detect_language(array $available, string $fallback = 'de'): string {
	// 1. URL-Parameter
	if (isset($_GET['lang'])) {
		$lang = strtolower(trim((string)$_GET['lang']));
		if (in_array($lang, $available, true)) {
			$_SESSION['lang'] = $lang;
			return $lang;
		}
	}

	// 2. Session
	if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $available, true)) {
		return (string)$_SESSION['lang'];
	}

	// 3. Accept-Language (Browser)
	if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		$header = strtolower((string)$_SERVER['HTTP_ACCEPT_LANGUAGE']);

		// einfache Parsing-Variante: Sprachen-Komponenten extrahieren
		$parts = explode(',', $header);
		foreach ($parts as $part) {
			$sub = explode(';', trim($part))[0]; // z.B. "de-DE" oder "en"
			// Volltreffer
			if (in_array($sub, $available, true)) {
				$_SESSION['lang'] = $sub;
				return $sub;
			}
			// Nur Hauptsprache z.B. "de" aus "de-DE"
			$primary = substr($sub, 0, 2);
			if (in_array($primary, $available, true)) {
				$_SESSION['lang'] = $primary;
				return $primary;
			}
		}
	}

	// 4. Fallback
	if (in_array($fallback, $available, true)) {
		$_SESSION['lang'] = $fallback;
		return $fallback;
	}

	// falls der Fallback nicht im verfügbaren Set ist, nimm einfach den ersten
	$_SESSION['lang'] = $available[0];
	return $available[0];
}

// Globale Übersetzungsdaten bereitstellen
$__i18n_available = i18n_get_available_languages();
$__i18n_lang = i18n_detect_language($__i18n_available, 'de');

$__i18n_translations = [];
$langFile = __DIR__ . '/lang/' . $__i18n_lang . '.php';
if (is_file($langFile)) {
	$tmp = require $langFile;
	if (is_array($tmp)) {
		$__i18n_translations = $tmp;
	}
}

/**
 * Übersetzung holen:
 *   t('gruppe.key') durchsucht das verschachtelte Array.
 *
 * Wenn nichts gefunden wird:
 *   - falls $fallback != '' → diesen zurückgeben
 *   - sonst den übergebenen Key zurückgeben
 */
function t(string $key, string $fallback = ''): string {
	global $__i18n_translations;

	$parts = explode('.', $key);
	$value = $__i18n_translations;

	foreach ($parts as $part) {
		if (!is_array($value) || !array_key_exists($part, $value)) {
			return $fallback !== '' ? $fallback : $key;
		}
		$value = $value[$part];
	}

	if (is_string($value)) {
		return $value;
	}

	return $fallback !== '' ? $fallback : $key;
}

/**
 * Aktive Sprache z.B. für <html lang="..."> oder Language-Switch.
 */
function current_language(): string {
	global $__i18n_lang;
	return $__i18n_lang;
}

/**
 * Verfügbare Sprachen zurückgeben (z.B. für ein Dropdown).
 */
function available_languages(): array {
	global $__i18n_available;
	return $__i18n_available;
}
