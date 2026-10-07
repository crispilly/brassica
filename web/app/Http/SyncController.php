<?php
declare(strict_types=1);

namespace Brassica\Http;

use Brassica\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class SyncController
{
    public static function issueToken(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        try {
            $db = Database::connection();
            self::ensureTokenTable($db);
            $userId = self::basicUser($db);

            $input = json_decode((string)file_get_contents('php://input'), true);
            $deviceId = trim((string)($input['deviceId'] ?? ''));
            $deviceName = trim((string)($input['deviceName'] ?? 'Brassica Android'));

            if ($deviceId === '') {
                throw new RuntimeException('Geräte-ID fehlt.');
            }

            $token = 'brs_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $tokenHash = hash('sha256', $token);
            $now = (new \DateTimeImmutable())->format(DATE_ATOM);

            $stmt = $db->prepare(
                'INSERT INTO sync_tokens(user_id,device_id,device_name,token_hash,created_at,last_used_at)
                 VALUES(:uid,:device_id,:device_name,:token_hash,:created,:used)
                 ON CONFLICT(user_id,device_id) DO UPDATE SET
                    device_name=excluded.device_name,
                    token_hash=excluded.token_hash,
                    created_at=excluded.created_at,
                    last_used_at=excluded.last_used_at'
            );
            $stmt->execute([
                ':uid' => $userId,
                ':device_id' => $deviceId,
                ':device_name' => $deviceName !== '' ? $deviceName : 'Brassica Android',
                ':token_hash' => $tokenHash,
                ':created' => $now,
                ':used' => $now,
            ]);

            echo json_encode([
                'token' => $token,
                'createdAt' => $now,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            if (http_response_code() < 400) {
                http_response_code(400);
            }
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function revokeToken(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        try {
            $db = Database::connection();
            self::ensureTokenTable($db);
            $token = self::bearerToken();

            if ($token === null) {
                http_response_code(401);
                throw new RuntimeException('Sync-Key fehlt.');
            }

            $stmt = $db->prepare('DELETE FROM sync_tokens WHERE token_hash=:hash');
            $stmt->execute([':hash' => hash('sha256', $token)]);

            echo json_encode(['revoked' => $stmt->rowCount() > 0], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if (http_response_code() < 400) {
                http_response_code(400);
            }
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function manifest(): void
    {
        self::json(function (PDO $db, int $userId): array {
            $stmt = $db->prepare('SELECT r.* FROM recipes r WHERE r.owner_id=:uid ORDER BY r.updated_at DESC');
            $stmt->execute([':uid' => $userId]);

            $items = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $row = self::ensureIdentity($db, $row);
                $cats = self::categories($db, (int)$row['id']);
                $contentHash = self::contentHash($row, $cats);
                $imageHash = self::imageHash($row['image_path'] ?? null);
                $syncHash = self::syncHash($contentHash, $imageHash);

                if (($row['content_hash'] ?? '') !== $contentHash) {
                    $db->prepare('UPDATE recipes SET content_hash=:h WHERE id=:id')
                        ->execute([':h' => $contentHash, ':id' => $row['id']]);
                }

                $items[] = [
                    'uuid' => $row['uuid'],
                    'title' => $row['title'],
                    'contentHash' => $contentHash,
                    'imageHash' => $imageHash,
                    'syncHash' => $syncHash,
                    'updatedAt' => $row['updated_at'],
                    'categories' => $cats,
                ];
            }

            return ['version' => 3, 'items' => $items];
        });
    }

    public static function recipe(array $params): void
    {
        self::json(function (PDO $db, int $userId) use ($params): array {
            $uuid = (string)($params['uuid'] ?? '');
            $stmt = $db->prepare('SELECT * FROM recipes WHERE owner_id=:uid AND uuid=:uuid LIMIT 1');
            $stmt->execute([':uid' => $userId, ':uuid' => $uuid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                http_response_code(404);
                throw new RuntimeException('Rezept nicht gefunden.');
            }

            // Immer dieselbe kanonische Nutzdatenstruktur verwenden, die auch
            // für den Sync-Hash verwendet wird.
            $cats = self::categories($db, (int)$row['id']);
            $data = self::rowToData($row);
            $data['categories'] = array_map(static fn(string $n): array => ['name' => $n], $cats);

            $imageBase64 = null;
            $imageName = null;
            if (!empty($row['image_path'])) {
                $file = legacy_image_file((string)$row['image_path']);
                if (is_file($file)) {
                    $imageBase64 = base64_encode((string)file_get_contents($file));
                    $imageName = basename($file);
                }
            }

            $contentHash = self::contentHash($row, $cats);
            $imageHash = self::imageHash($row['image_path'] ?? null);

            return [
                'uuid' => $row['uuid'],
                'data' => $data,
                'imageName' => $imageName,
                'imageBase64' => $imageBase64,
                'contentHash' => $contentHash,
                'imageHash' => $imageHash,
                'syncHash' => self::syncHash($contentHash, $imageHash),
                'updatedAt' => $row['updated_at'],
            ];
        });
    }

    public static function apply(): void
    {
        self::json(function (PDO $db, int $userId): array {
            $input = json_decode((string)file_get_contents('php://input'), true);
            $recipes = is_array($input['recipes'] ?? null) ? $input['recipes'] : [];

            $saved = [];
            $hashes = [];

            foreach ($recipes as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $result = self::upsert($db, $userId, $item);
                $saved[] = $result['uuid'];
                $hashes[$result['uuid']] = $result['syncHash'];
            }

            return [
                'saved' => $saved,
                'hashes' => $hashes,
                'count' => count($saved),
            ];
        });
    }

    private static function upsert(PDO $db, int $userId, array $item): array
    {
        $uuid = trim((string)($item['uuid'] ?? ''));
        if ($uuid === '') {
            $uuid = self::uuid();
        }

        $data = $item['data'] ?? null;
        if (!is_array($data) || trim((string)($data['title'] ?? '')) === '') {
            throw new RuntimeException('Ungültiges Rezept.');
        }

        $cats = [];
        foreach (($data['categories'] ?? []) as $c) {
            $name = trim((string)(is_array($c) ? ($c['name'] ?? '') : $c));
            if ($name !== '') {
                $cats[$name] = $name;
            }
        }
        $cats = array_values($cats);
        sort($cats, SORT_STRING);

        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $stmt = $db->prepare('SELECT id,image_path,created_at FROM recipes WHERE owner_id=:uid AND uuid=:uuid LIMIT 1');
        $stmt->execute([':uid' => $userId, ':uuid' => $uuid]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $canonical = self::canonicalData($data, $cats);
        $json = $canonical;

        $values = [
            ':uid' => $userId,
            ':uuid' => $uuid,
            ':title' => $canonical['title'],
            ':description' => $canonical['description'],
            ':directions' => $canonical['directions'],
            ':ingredients' => $canonical['ingredients'],
            ':notes' => $canonical['notes'],
            ':nutritional_vals' => $canonical['nutritionalValues'],
            ':preparation_time' => $canonical['preparationTime'],
            ':servings' => $canonical['servings'],
            ':source' => $canonical['source'],
            ':favorite' => $canonical['favorite'] ? 1 : 0,
            ':json' => json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':updated' => $now,
        ];

        if ($existing) {
            $id = (int)$existing['id'];
            $values[':id'] = $id;
            $db->prepare(
                'UPDATE recipes SET
                    uuid=:uuid,title=:title,description=:description,directions=:directions,
                    ingredients=:ingredients,notes=:notes,nutritional_vals=:nutritional_vals,
                    preparation_time=:preparation_time,servings=:servings,source=:source,
                    favorite=:favorite,json_data=:json,updated_at=:updated
                 WHERE id=:id AND owner_id=:uid'
            )->execute($values);
        } else {
            $values[':created'] = $now;
            $db->prepare(
                "INSERT INTO recipes(
                    owner_id,uuid,title,description,directions,ingredients,notes,nutritional_vals,
                    preparation_time,servings,source,favorite,json_data,source_type,created_at,updated_at
                 ) VALUES(
                    :uid,:uuid,:title,:description,:directions,:ingredients,:notes,:nutritional_vals,
                    :preparation_time,:servings,:source,:favorite,:json,'sync',:created,:updated
                 )"
            )->execute($values);
            $id = (int)$db->lastInsertId();
        }

        $db->prepare('DELETE FROM recipe_categories WHERE recipe_id=:id')->execute([':id' => $id]);

        foreach ($cats as $name) {
            $q = $db->prepare('SELECT id FROM categories WHERE name=:n');
            $q->execute([':n' => $name]);
            $cid = $q->fetchColumn();

            if ($cid === false) {
                $db->prepare('INSERT INTO categories(name) VALUES(:n)')->execute([':n' => $name]);
                $cid = $db->lastInsertId();
            }

            $db->prepare(
                'INSERT OR IGNORE INTO recipe_categories(recipe_id,category_id) VALUES(:r,:c)'
            )->execute([':r' => $id, ':c' => $cid]);
        }

        if (!empty($item['imageBase64'])) {
            $raw = base64_decode((string)$item['imageBase64'], true);
            if ($raw !== false) {
                $name = basename((string)($item['imageName'] ?? ($uuid . '.jpg')));
                $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: ($uuid . '.jpg');
                $path = storage_path('images/' . $uuid . '_' . $name);

                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0775, true);
                }

                file_put_contents($path, $raw);
                $stored = 'data/images/' . basename($path);
                $db->prepare('UPDATE recipes SET image_path=:p,image_name_orig=:n WHERE id=:id')
                    ->execute([':p' => $stored, ':n' => $name, ':id' => $id]);
            }
        } else {
            $db->prepare('UPDATE recipes SET image_path=NULL,image_name_orig=NULL WHERE id=:id')
                ->execute([':id' => $id]);
        }

        $row = $db->query('SELECT * FROM recipes WHERE id=' . (int)$id)->fetch(PDO::FETCH_ASSOC);
        $contentHash = self::contentHash($row, $cats);
        $imageHash = self::imageHash($row['image_path'] ?? null);

        $db->prepare('UPDATE recipes SET content_hash=:h WHERE id=:id')
            ->execute([':h' => $contentHash, ':id' => $id]);

        return [
            'uuid' => $uuid,
            'contentHash' => $contentHash,
            'imageHash' => $imageHash,
            'syncHash' => self::syncHash($contentHash, $imageHash),
        ];
    }

    private static function json(callable $fn): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        try {
            $db = Database::connection();
            self::ensureTokenTable($db);
            $userId = self::syncUser($db);
            echo json_encode(
                $fn($db, $userId),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (Throwable $e) {
            if (http_response_code() < 400) {
                http_response_code(400);
            }
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private static function syncUser(PDO $db): int
    {
        $token = self::bearerToken();

        if ($token !== null) {
            $tokenHash = hash('sha256', $token);
            $stmt = $db->prepare('SELECT user_id FROM sync_tokens WHERE token_hash=:hash LIMIT 1');
            $stmt->execute([':hash' => $tokenHash]);
            $userId = $stmt->fetchColumn();

            if ($userId === false) {
                http_response_code(401);
                throw new RuntimeException('Ungültiger oder widerrufener Sync-Key.');
            }

            $db->prepare('UPDATE sync_tokens SET last_used_at=:used WHERE token_hash=:hash')
                ->execute([
                    ':used' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    ':hash' => $tokenHash,
                ]);

            return (int)$userId;
        }

        // Übergangskompatibilität für Brassica Android 2.0.0/2.0.1.
        return self::basicUser($db);
    }

    private static function basicUser(PDO $db): int
    {
        $user = $_SERVER['PHP_AUTH_USER'] ?? null;
        $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
        $authorization = self::authorizationHeader();

        if ($user === null && $authorization !== null && str_starts_with($authorization, 'Basic ')) {
            $decoded = base64_decode(substr($authorization, 6), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$user, $pass] = explode(':', $decoded, 2);
            }
        }

        if ($user === null) {
            http_response_code(401);
            header('WWW-Authenticate: Basic realm="Brassica Sync"');
            throw new RuntimeException('Anmeldung erforderlich.');
        }

        $stmt = $db->prepare('SELECT id,password_hash FROM users WHERE username=:u LIMIT 1');
        $stmt->execute([':u' => $user]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify((string)$pass, (string)$row['password_hash'])) {
            http_response_code(401);
            throw new RuntimeException('Ungültige Zugangsdaten.');
        }

        return (int)$row['id'];
    }

    private static function bearerToken(): ?string
    {
        $authorization = self::authorizationHeader();

        if ($authorization === null || !str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($authorization, 7));
        return $token !== '' ? $token : null;
    }

    private static function authorizationHeader(): ?string
    {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            if (!empty($_SERVER[$key])) {
                return trim((string)$_SERVER[$key]);
            }
        }

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $name => $value) {
                if (strcasecmp((string)$name, 'Authorization') === 0) {
                    return trim((string)$value);
                }
            }
        }

        return null;
    }

    private static function ensureTokenTable(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS sync_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                device_id TEXT NOT NULL,
                device_name TEXT,
                token_hash TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL,
                last_used_at TEXT,
                UNIQUE(user_id, device_id)
            )'
        );
        $db->exec('CREATE INDEX IF NOT EXISTS idx_sync_tokens_user_id ON sync_tokens(user_id)');
    }

    private static function ensureIdentity(PDO $db, array $row): array
    {
        if (empty($row['uuid'])) {
            $row['uuid'] = self::uuid();
            $db->prepare('UPDATE recipes SET uuid=:u WHERE id=:id')
                ->execute([':u' => $row['uuid'], ':id' => $row['id']]);
        }
        return $row;
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private static function categories(PDO $db, int $id): array
    {
        $stmt = $db->prepare(
            'SELECT c.name
             FROM categories c
             JOIN recipe_categories rc ON rc.category_id=c.id
             WHERE rc.recipe_id=:id
             ORDER BY c.name COLLATE NOCASE'
        );
        $stmt->execute([':id' => $id]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function canonicalData(array $data, array $cats): array
    {
        $names = array_values(array_unique(array_map('strval', $cats)));
        sort($names, SORT_STRING);

        return [
            'title' => (string)($data['title'] ?? ''),
            'description' => (string)($data['description'] ?? ''),
            'directions' => (string)($data['directions'] ?? ''),
            'ingredients' => (string)($data['ingredients'] ?? ''),
            'notes' => (string)($data['notes'] ?? ''),
            'nutritionalValues' => (string)($data['nutritionalValues'] ?? ''),
            'preparationTime' => (string)($data['preparationTime'] ?? ''),
            'servings' => (string)($data['servings'] ?? ''),
            'source' => (string)($data['source'] ?? ''),
            'favorite' => !empty($data['favorite']),
            'categories' => array_map(static fn(string $n): array => ['name' => $n], $names),
        ];
    }

    private static function contentHash(array $row, array $cats): string
    {
        $data = self::canonicalData(self::rowToData($row), $cats);

        // Serializer-unabhängiges Format: UTF-8-Byte-Längen + Rohdaten.
        // Dadurch erzeugen PHP und Android auch bei Unicode, Zeilenumbrüchen
        // und Sonderzeichen garantiert dieselbe Hash-Eingabe.
        $buffer = "brassica-sync-v3\0";

        foreach ([
            'title',
            'description',
            'directions',
            'ingredients',
            'notes',
            'nutritionalValues',
            'preparationTime',
            'servings',
            'source',
        ] as $field) {
            $value = (string)$data[$field];
            $buffer .= $field . "\0" . strlen($value) . "\0" . $value . "\0";
        }

        $buffer .= 'favorite' . "\0" . ($data['favorite'] ? '1' : '0') . "\0";

        $categoryNames = [];
        foreach ($data['categories'] as $category) {
            $categoryNames[] = (string)($category['name'] ?? '');
        }

        $buffer .= 'categories' . "\0" . count($categoryNames) . "\0";
        foreach ($categoryNames as $categoryName) {
            $buffer .= strlen($categoryName) . "\0" . $categoryName . "\0";
        }

        return hash('sha256', $buffer);
    }

    private static function imageHash(?string $stored): ?string
    {
        if (!$stored) {
            return null;
        }

        $file = legacy_image_file($stored);
        return is_file($file) ? hash_file('sha256', $file) : null;
    }

    private static function syncHash(string $contentHash, ?string $imageHash): string
    {
        // Broccoli/Brassica Android komprimiert Bilder beim lokalen Speichern.
        // Ein Byte-Hash des Bildes wäre deshalb nach einem Download absichtlich
        // verschieden. Für die Konflikterkennung ist der kanonische Rezeptinhalt
        // maßgeblich; imageHash bleibt als Manifest-Metadatum verfügbar.
        return $contentHash;
    }

    private static function rowToData(array $r): array
    {
        return [
            'title' => $r['title'] ?? '',
            'description' => $r['description'] ?? '',
            'directions' => $r['directions'] ?? '',
            'ingredients' => $r['ingredients'] ?? '',
            'notes' => $r['notes'] ?? '',
            'nutritionalValues' => $r['nutritional_vals'] ?? '',
            'preparationTime' => $r['preparation_time'] ?? '',
            'servings' => $r['servings'] ?? '',
            'source' => $r['source'] ?? '',
            'favorite' => !empty($r['favorite']),
        ];
    }
}
