<?php

declare(strict_types=1);

/**
 * Loads and saves application configuration safely.
 */
final class AppConfig
{
    private static ?array $data = null;

    public static function configPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    }

    public static function examplePath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.example.php';
    }

    public static function all(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $path = self::configPath();
        if (!is_file($path)) {
            $example = self::examplePath();
            self::$data = is_file($example) ? (array) require $example : [];
            return self::$data;
        }

        $loaded = require $path;
        self::$data = is_array($loaded) ? $loaded : [];
        return self::$data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function reload(): void
    {
        self::$data = null;
    }

    /**
     * Public-safe settings (never includes raw API keys).
     */
    public static function publicSettings(): array
    {
        $all = self::all();
        return [
            'ai_provider' => (string) ($all['ai_provider'] ?? 'auto'),
            'gemini_model' => (string) ($all['gemini_model'] ?? ''),
            'gemini_fallback_models' => (string) ($all['gemini_fallback_models'] ?? ''),
            'agnes_model' => (string) ($all['agnes_model'] ?? ''),
            'agnes_fallback_models' => (string) ($all['agnes_fallback_models'] ?? ''),
            'agnes_base_url' => (string) ($all['agnes_base_url'] ?? 'https://apihub.agnes-ai.com/v1'),
            'request_timeout' => (int) ($all['request_timeout'] ?? 45),
            'db_enabled' => (bool) ($all['db_enabled'] ?? false),
            'web_lookup_enabled' => (bool) ($all['web_lookup_enabled'] ?? true),
            'gemini_key_status' => self::keyStatus('gemini_api_key'),
            'agnes_key_status' => self::keyStatus('agnes_api_key'),
        ];
    }

    public static function keyStatus(string $key): string
    {
        $value = trim((string) self::get($key, ''));
        return $value !== '' ? 'Key saved' : 'Not configured';
    }

    public static function hasKey(string $key): bool
    {
        return trim((string) self::get($key, '')) !== '';
    }

    /**
     * Persist settings. Empty API key fields keep the existing key.
     *
     * @param array<string, mixed> $input
     */
    public static function save(array $input): bool
    {
        $current = self::all();

        $provider = strtolower(trim((string) ($input['ai_provider'] ?? $current['ai_provider'] ?? 'auto')));
        if (!in_array($provider, ['auto', 'gemini', 'agnes'], true)) {
            $provider = 'auto';
        }

        $timeout = (int) ($input['request_timeout'] ?? $current['request_timeout'] ?? 45);
        $timeout = max(10, min(120, $timeout));

        $geminiKey = trim((string) ($input['gemini_api_key'] ?? ''));
        if ($geminiKey === '') {
            $geminiKey = (string) ($current['gemini_api_key'] ?? '');
        }

        $agnesKey = trim((string) ($input['agnes_api_key'] ?? ''));
        if ($agnesKey === '') {
            $agnesKey = (string) ($current['agnes_api_key'] ?? '');
        }

        $next = [
            'ai_provider' => $provider,
            'gemini_api_key' => $geminiKey,
            'gemini_model' => trim((string) ($input['gemini_model'] ?? $current['gemini_model'] ?? 'gemini-2.0-flash')),
            'gemini_fallback_models' => (string) ($input['gemini_fallback_models'] ?? $current['gemini_fallback_models'] ?? ''),
            'agnes_api_key' => $agnesKey,
            'agnes_model' => trim((string) ($input['agnes_model'] ?? $current['agnes_model'] ?? 'agnes-2.5-flash')),
            'agnes_fallback_models' => (string) ($input['agnes_fallback_models'] ?? $current['agnes_fallback_models'] ?? ''),
            'agnes_base_url' => rtrim(trim((string) ($input['agnes_base_url'] ?? $current['agnes_base_url'] ?? 'https://apihub.agnes-ai.com/v1')), '/'),
            'request_timeout' => $timeout,
            'db_host' => (string) ($current['db_host'] ?? '127.0.0.1'),
            'db_port' => (int) ($current['db_port'] ?? 3306),
            'db_name' => (string) ($current['db_name'] ?? 'ai_ar_scanner'),
            'db_user' => (string) ($current['db_user'] ?? 'root'),
            'db_pass' => (string) ($current['db_pass'] ?? ''),
            'db_enabled' => (bool) ($input['db_enabled'] ?? $current['db_enabled'] ?? false),
            'settings_token' => (string) ($current['settings_token'] ?? ''),
            'web_lookup_enabled' => (bool) ($input['web_lookup_enabled'] ?? $current['web_lookup_enabled'] ?? true),
        ];

        $exported = self::exportPhp($next);
        $path = self::configPath();
        $ok = file_put_contents($path, $exported, LOCK_EX) !== false;
        if ($ok) {
            self::$data = $next;
        }
        return $ok;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function exportPhp(array $data): string
    {
        $lines = ["<?php", "", "declare(strict_types=1);", "", "return ["];
        foreach ($data as $key => $value) {
            $lines[] = '    ' . var_export((string) $key, true) . ' => ' . var_export($value, true) . ',';
        }
        $lines[] = '];';
        $lines[] = '';
        return implode(PHP_EOL, $lines);
    }

    /**
     * Parse newline / comma separated model list.
     *
     * @return list<string>
     */
    public static function parseModelList(string $primary, string $fallbackBlob): array
    {
        $models = [];
        $primary = trim($primary);
        if ($primary !== '') {
            $models[] = $primary;
        }

        $parts = preg_split('/[\r\n,]+/', $fallbackBlob) ?: [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && !in_array($part, $models, true)) {
                $models[] = $part;
            }
        }

        return $models;
    }
}
