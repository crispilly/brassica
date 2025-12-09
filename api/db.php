<?php
declare(strict_types=1);

function get_db(): PDO
{
	static $pdo = null;

	if ($pdo instanceof PDO) {
		return $pdo;
	}

	$dbPath = __DIR__ . '/../data/db.sqlite';

	if (!file_exists($dbPath)) {
		throw new RuntimeException('SQLite-Datei nicht gefunden: ' . $dbPath);
	}

	$pdo = new PDO('sqlite:' . $dbPath);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->exec('PRAGMA foreign_keys = ON');

	return $pdo;
}
