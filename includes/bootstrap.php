<?php

declare(strict_types=1);

/**
 * Shared bootstrap helpers for API and pages.
 */

require_once __DIR__ . '/AppConfig.php';

function app_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function app_base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // /folder/index.php -> /folder
    // /folder/api/recognize.php -> /folder
    $dir = str_replace('\\', '/', dirname($script));
    if (str_ends_with($dir, '/api')) {
        $dir = dirname($dir);
    }
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }
    return rtrim($dir, '/');
}

function app_public_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = app_base_path();
    $path = '/' . ltrim($path, '/');
    if ($path === '/') {
        $path = '';
    }
    return $scheme . '://' . $host . $base . $path;
}

function app_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_require_settings_auth(): void
{
    $token = trim((string) AppConfig::get('settings_token', ''));
    if ($token === '') {
        return;
    }

    $provided = '';
    $header = $_SERVER['HTTP_X_SETTINGS_TOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        $provided = $header;
    } elseif (isset($_GET['token'])) {
        $provided = (string) $_GET['token'];
    }

    if (!hash_equals($token, $provided)) {
        app_json_response(['ok' => false, 'error' => 'Unauthorized.'], 401);
    }
}

function app_log(string $message): void
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $safe = preg_replace('/(AIza[0-9A-Za-z_\-]{8,}|Bearer\s+[A-Za-z0-9_\-\.]+)/i', '***', $message) ?? $message;
    @file_put_contents(
        $dir . DIRECTORY_SEPARATOR . 'app.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $safe . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
