<?php
declare(strict_types=1);

namespace Brassica\Sync;

final class RecipeSync
{
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);
        return sprintf('%s-%s-%s-%s-%s', substr($hex,0,8), substr($hex,8,4), substr($hex,12,4), substr($hex,16,4), substr($hex,20));
    }

    /** @param array<string,mixed> $recipe */
    public static function normalizeRecipe(array $recipe): array
    {
        $categories = [];
        foreach (($recipe['categories'] ?? []) as $category) {
            if (!is_array($category) || !isset($category['name'])) continue;
            $name = trim((string)$category['name']);
            if ($name !== '') $categories[$name] = ['name' => $name];
        }
        ksort($categories, SORT_STRING);

        $normalized = [
            'categories' => array_values($categories),
            'description' => (string)($recipe['description'] ?? ''),
            'directions' => (string)($recipe['directions'] ?? ''),
            'favorite' => !empty($recipe['favorite']),
            'ingredients' => (string)($recipe['ingredients'] ?? ''),
            'notes' => (string)($recipe['notes'] ?? ''),
            'nutritionalValues' => (string)($recipe['nutritionalValues'] ?? ''),
            'preparationTime' => (string)($recipe['preparationTime'] ?? ''),
            'servings' => (string)($recipe['servings'] ?? ''),
            'source' => (string)($recipe['source'] ?? ''),
            'title' => (string)($recipe['title'] ?? ''),
        ];
        ksort($normalized, SORT_STRING);
        return $normalized;
    }

    /** @param array<string,mixed> $recipe */
    public static function contentHash(array $recipe): string
    {
        return hash('sha256', (string)json_encode(self::normalizeRecipe($recipe), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
