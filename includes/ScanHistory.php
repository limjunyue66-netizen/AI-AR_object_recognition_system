<?php

declare(strict_types=1);

require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/ProductResult.php';

/**
 * Scan history storage.
 * Default: SQLite file (works without MySQL).
 * Optional: MySQL when db_enabled = true.
 */
final class ScanHistory
{
    private static ?PDO $pdo = null;

    public static function isEnabled(): bool
    {
        // History is always available via SQLite (or MySQL if configured).
        return true;
    }

    public static function useMysql(): bool
    {
        return (bool) AppConfig::get('db_enabled', false);
    }

    public static function sqlitePath(): string
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . DIRECTORY_SEPARATOR . 'scan_history.sqlite';
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (self::useMysql()) {
            try {
                self::$pdo = self::connectMysql();
                self::ensureMysqlTable(self::$pdo);
                return self::$pdo;
            } catch (Throwable $e) {
                // Fall back to SQLite if MySQL fails
                self::logSafe('MySQL history unavailable, using SQLite: ' . $e->getMessage());
            }
        }

        self::$pdo = self::connectSqlite();
        self::ensureSqliteTable(self::$pdo);
        return self::$pdo;
    }

    private static function connectMysql(): PDO
    {
        $host = (string) AppConfig::get('db_host', '127.0.0.1');
        $port = (int) AppConfig::get('db_port', 3306);
        $name = (string) AppConfig::get('db_name', 'ai_ar_scanner');
        $user = (string) AppConfig::get('db_user', 'root');
        $pass = (string) AppConfig::get('db_pass', '');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private static function connectSqlite(): PDO
    {
        $path = self::sqlitePath();
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        return $pdo;
    }

    private static function ensureSqliteTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS scan_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                object_label TEXT NOT NULL DEFAULT "",
                product_name TEXT NOT NULL DEFAULT "",
                manufacturer TEXT NOT NULL DEFAULT "",
                specification TEXT,
                description TEXT,
                confidence REAL NOT NULL DEFAULT 0,
                provider TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    private static function ensureMysqlTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS scan_history (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                object_label VARCHAR(191) NOT NULL DEFAULT "",
                product_name VARCHAR(255) NOT NULL DEFAULT "",
                manufacturer VARCHAR(191) NOT NULL DEFAULT "",
                specification TEXT NULL,
                description TEXT NULL,
                confidence DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
                provider VARCHAR(64) NOT NULL DEFAULT "",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_created_at (created_at),
                KEY idx_product_name (product_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function save(ProductResult $result): void
    {
        $pdo = self::pdo();
        $sql = 'INSERT INTO scan_history
            (object_label, product_name, manufacturer, specification, description, confidence, provider, created_at)
            VALUES
            (:object_label, :product_name, :manufacturer, :specification, :description, :confidence, :provider, :created_at)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':object_label' => $result->objectLabel,
            ':product_name' => $result->productName,
            ':manufacturer' => $result->manufacturer,
            ':specification' => $result->specification,
            ':description' => $result->description,
            ':confidence' => $result->confidence,
            ':provider' => $result->provider,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{items: list<array<string,mixed>>, total: int, page: int, perPage: int, storage: string}
     */
    public static function list(string $search = '', int $page = 1, int $perPage = 20): array
    {
        $pdo = self::pdo();
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        $search = trim($search);
        if ($search !== '') {
            $where = 'WHERE product_name LIKE :q OR manufacturer LIKE :q OR object_label LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM scan_history {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // SQLite and MySQL both accept LIMIT/OFFSET integers when bound carefully;
        // embed sanitized ints to stay compatible.
        $sql = "SELECT id, object_label, product_name, manufacturer, specification, description, confidence, provider, created_at
                FROM scan_history {$where}
                ORDER BY id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $items[] = self::mapRow($row);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'storage' => self::useMysql() && self::$pdo !== null ? 'mysql' : 'sqlite',
        ];
    }

    public static function getById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        $pdo = self::pdo();
        $stmt = $pdo->prepare('SELECT * FROM scan_history WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return self::mapRow($row);
    }

    public static function clear(): int
    {
        $pdo = self::pdo();
        return (int) $pdo->exec('DELETE FROM scan_history');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'objectLabel' => (string) $row['object_label'],
            'productName' => (string) $row['product_name'],
            'manufacturer' => (string) $row['manufacturer'],
            'specification' => (string) ($row['specification'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'confidence' => (float) $row['confidence'],
            'provider' => (string) $row['provider'],
            'createdAt' => (string) $row['created_at'],
        ];
    }

    private static function logSafe(string $message): void
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'app.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
