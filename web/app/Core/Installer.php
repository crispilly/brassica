<?php
declare(strict_types=1);

namespace Brassica\Core;

use PDO;
use RuntimeException;
use Throwable;

final class Installer
{
    public static function ensureReady(): void
    {
        self::ensureStorageDirectories();

        $databasePath = storage_path('database/brassica.sqlite');
        if (!is_file($databasePath)) {
            if (LegacyV1Migrator::hasLegacyInstallation()) {
                LegacyV1Migrator::migrate($databasePath);
            } else {
                self::createDatabase($databasePath);
            }
        }
    }

    public static function needsSetup(): bool
    {
        self::ensureReady();

        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT COUNT(*) FROM users');
        return (int)$stmt->fetchColumn() === 0;
    }

    private static function ensureStorageDirectories(): void
    {
        foreach ([
            storage_path(),
            storage_path('database'),
            storage_path('images'),
            storage_path('imports'),
            storage_path('imports/archives'),
            storage_path('exports'),
            storage_path('temp'),
            storage_path('logs'),
        ] as $directory) {
            if (is_dir($directory)) {
                continue;
            }

            if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Verzeichnis konnte nicht angelegt werden: ' . $directory);
            }
        }
    }

    private static function createDatabase(string $databasePath): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('Die PHP-Erweiterung pdo_sqlite ist nicht verfügbar.');
        }

        $schemaPath = base_path('database/schema.sql');
        $schema = @file_get_contents($schemaPath);
        if ($schema === false || trim($schema) === '') {
            throw new RuntimeException('Datenbankschema nicht gefunden: ' . $schemaPath);
        }

        try {
            $pdo = new PDO('sqlite:' . $databasePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec($schema);
        } catch (Throwable $e) {
            @unlink($databasePath);
            throw new RuntimeException('Datenbank konnte nicht initialisiert werden: ' . $e->getMessage(), 0, $e);
        }
    }
}
