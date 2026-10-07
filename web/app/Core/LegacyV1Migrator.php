<?php
declare(strict_types=1);

namespace Brassica\Core;

use PDO;
use RuntimeException;
use Throwable;

final class LegacyV1Migrator
{
    private const REQUIRED_TABLES = [
        'users',
        'recipes',
        'categories',
        'recipe_categories',
        'collections',
        'collection_recipes',
        'archives',
    ];

    public static function hasLegacyInstallation(): bool
    {
        return is_file(base_path('data/db.sqlite'));
    }

    /**
     * Migriert eine Brassica-1.x-Installation, wenn deren data/-Ordner
     * im Root der neuen Webinstallation liegt.
     *
     * Die Quelldaten werden nur kopiert und niemals gelöscht.
     */
    public static function migrate(string $targetDatabasePath): array
    {
        $legacyDatabasePath = base_path('data/db.sqlite');

        if (!is_file($legacyDatabasePath) || is_file($targetDatabasePath)) {
            return ['migrated' => false];
        }

        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('Die PHP-Erweiterung pdo_sqlite ist nicht verfügbar.');
        }

        self::validateDatabase($legacyDatabasePath);

        $targetDirectory = dirname($targetDatabasePath);
        if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Datenbankverzeichnis konnte nicht angelegt werden: ' . $targetDirectory);
        }

        $temporaryDatabasePath = $targetDatabasePath . '.migrating';
        @unlink($temporaryDatabasePath);

        if (!@copy($legacyDatabasePath, $temporaryDatabasePath)) {
            throw new RuntimeException('Die Brassica-1.x-Datenbank konnte nicht kopiert werden.');
        }

        try {
            self::validateDatabase($temporaryDatabasePath);

            $imagesCopied = self::copyDirectoryFiles(
                base_path('data/images'),
                storage_path('images')
            );

            $archivesCopied = self::copyDirectoryFiles(
                base_path('data/uploads/archives'),
                storage_path('imports/archives')
            );

            if (!@rename($temporaryDatabasePath, $targetDatabasePath)) {
                throw new RuntimeException('Die migrierte Datenbank konnte nicht aktiviert werden.');
            }

            $report = [
                'migrated' => true,
                'migrated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'source_database' => 'data/db.sqlite',
                'target_database' => 'storage/database/brassica.sqlite',
                'database_sha256' => hash_file('sha256', $targetDatabasePath) ?: null,
                'images_copied' => $imagesCopied,
                'archives_copied' => $archivesCopied,
                'source_data_kept' => true,
            ];

            self::writeReport($report);

            return $report;
        } catch (Throwable $e) {
            @unlink($temporaryDatabasePath);
            if (!is_file($targetDatabasePath)) {
                @unlink($targetDatabasePath);
            }
            throw $e;
        }
    }

    private static function validateDatabase(string $path): void
    {
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $check = $pdo->query('PRAGMA quick_check')->fetchColumn();
            if ($check !== 'ok') {
                throw new RuntimeException('SQLite quick_check meldet: ' . (string)$check);
            }

            $rows = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
            $tables = array_map('strval', $rows ?: []);

            foreach (self::REQUIRED_TABLES as $requiredTable) {
                if (!in_array($requiredTable, $tables, true)) {
                    throw new RuntimeException('Die Datei ist keine unterstützte Brassica-1.x-Datenbank. Tabelle fehlt: ' . $requiredTable);
                }
            }
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException(
                'Die Brassica-1.x-Datenbank konnte nicht geprüft werden: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private static function copyDirectoryFiles(string $sourceDirectory, string $targetDirectory): int
    {
        if (!is_dir($sourceDirectory)) {
            return 0;
        }

        if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Zielverzeichnis konnte nicht angelegt werden: ' . $targetDirectory);
        }

        $copied = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($sourceDirectory) + 1);
            $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath) && !@mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
                    throw new RuntimeException('Unterverzeichnis konnte nicht angelegt werden: ' . $targetPath);
                }
                continue;
            }

            if (is_file($targetPath)) {
                if (hash_file('sha256', $item->getPathname()) !== hash_file('sha256', $targetPath)) {
                    throw new RuntimeException('Dateikonflikt bei der Migration: ' . $relativePath);
                }
                continue;
            }

            if (!@copy($item->getPathname(), $targetPath)) {
                throw new RuntimeException('Datei konnte nicht migriert werden: ' . $relativePath);
            }
            $copied++;
        }

        return $copied;
    }

    private static function writeReport(array $report): void
    {
        $path = storage_path('logs/v1-migration.json');
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json !== false) {
            @file_put_contents($path, $json . PHP_EOL);
        }
    }
}
