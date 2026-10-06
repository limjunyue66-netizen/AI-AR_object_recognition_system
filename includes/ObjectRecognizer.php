<?php

declare(strict_types=1);

require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/GeminiVision.php';
require_once __DIR__ . '/AgnesVision.php';
require_once __DIR__ . '/ProductResult.php';
require_once __DIR__ . '/ProductLookup.php';
require_once __DIR__ . '/ScanHistory.php';

/**
 * Orchestrates AI providers (auto / gemini / agnes) and optional enrichment.
 */
final class ObjectRecognizer
{
    /**
     * @return array{result: ProductResult, stage: string}
     */
    public function recognize(string $imageBinary, string $mimeType, string $languageName = ''): array
    {
        $mode = strtolower((string) AppConfig::get('ai_provider', 'auto'));
        if (!in_array($mode, ['auto', 'gemini', 'agnes'], true)) {
            $mode = 'auto';
        }

        $geminiReady = AppConfig::hasKey('gemini_api_key');
        $agnesReady = AppConfig::hasKey('agnes_api_key');

        if ($mode === 'gemini' && !$geminiReady) {
            throw new RuntimeException('AI provider is not configured.');
        }
        if ($mode === 'agnes' && !$agnesReady) {
            throw new RuntimeException('AI provider is not configured.');
        }
        if ($mode === 'auto' && !$geminiReady && !$agnesReady) {
            throw new RuntimeException('AI provider is not configured.');
        }

        $errors = [];
        $result = null;

        if ($mode === 'gemini' || ($mode === 'auto' && $geminiReady)) {
            try {
                $gemini = new GeminiVision();
                $gemini->setResponseLanguage($languageName);
                $result = $gemini->recognize($imageBinary, $mimeType);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                if ($mode === 'gemini') {
                    throw $e;
                }
            }
        }

        $needFallback = $result === null || !$result->isComplete();
        if ($needFallback && ($mode === 'agnes' || ($mode === 'auto' && $agnesReady))) {
            try {
                $agnes = new AgnesVision();
                $agnes->setResponseLanguage($languageName);
                $result = $agnes->recognize($imageBinary, $mimeType);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                if ($mode === 'agnes') {
                    throw $e;
                }
            }
        }

        if ($result === null || !$result->isComplete()) {
            $msg = $errors !== [] ? $errors[0] : 'Unable to identify the object.';
            throw new RuntimeException($msg);
        }

        $stage = 'ai';
        if ((bool) AppConfig::get('web_lookup_enabled', true)) {
            $enriched = ProductLookup::enrich($result);
            if ($enriched !== null) {
                $result = $enriched;
                $stage = 'lookup';
            }
        }

        // Optional history persistence
        try {
            ScanHistory::save($result);
        } catch (Throwable $e) {
            // History is optional — never fail recognition because of DB issues
            self::logSafe('History save skipped: ' . $e->getMessage());
        }

        return ['result' => $result, 'stage' => $stage];
    }

    /**
     * @return array<string, array{ok:bool, message:string}>
     */
    public function testProviders(): array
    {
        $out = [
            'gemini' => ['ok' => false, 'message' => 'Not configured'],
            'agnes' => ['ok' => false, 'message' => 'Not configured'],
        ];

        if (AppConfig::hasKey('gemini_api_key')) {
            try {
                $out['gemini'] = (new GeminiVision())->testConnection();
            } catch (Throwable $e) {
                $out['gemini'] = ['ok' => false, 'message' => 'Connection failed'];
            }
        }

        if (AppConfig::hasKey('agnes_api_key')) {
            try {
                $out['agnes'] = (new AgnesVision())->testConnection();
            } catch (Throwable $e) {
                $out['agnes'] = ['ok' => false, 'message' => 'Connection failed'];
            }
        }

        return $out;
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
