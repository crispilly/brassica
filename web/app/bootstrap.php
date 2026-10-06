<?php
declare(strict_types=1);

const BRASSICA_BASE_PATH = __DIR__ . '/..';

function base_path(string $path = ''): string
{
    $base = rtrim(BRASSICA_BASE_PATH, '/\\');
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
}

function storage_path(string $path = ''): string
{
    $base = base_path('storage');
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
}

function public_path(string $path = ''): string
{
    $base = base_path('public');
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
}

function legacy_image_file(string $storedValue): string
{
    return storage_path('images/' . basename(str_replace('\\', '/', $storedValue)));
}

function resolve_archive_path(string $storedValue): string
{
    if ($storedValue !== '' && is_file($storedValue)) {
        return $storedValue;
    }
    return storage_path('imports/archives/' . basename(str_replace('\\', '/', $storedValue)));
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Brassica\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = base_path('app/' . str_replace('\\', '/', $relative) . '.php');
    if (is_file($file)) require $file;
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once base_path('app/i18n.php');
