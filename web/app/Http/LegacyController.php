<?php
declare(strict_types=1);

namespace Brassica\Http;

use RuntimeException;

final class LegacyController
{
    public static function page(string $name): void
    {
        self::run(base_path('app/Legacy/public/' . $name . '.php'));
    }

    public static function api(string $name): void
    {
        self::run(base_path('app/Legacy/api/' . $name . '.php'));
    }

    private static function run(string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException('Interner Handler fehlt: ' . $file);
        }
        require $file;
    }
}
