<?php

declare(strict_types=1);

require_once __DIR__ . '/AIProvider.php';
require_once __DIR__ . '/ProductResult.php';
require_once __DIR__ . '/AppConfig.php';

/**
 * Google Gemini Vision provider.
 */
final class GeminiVision extends AIProvider
{
    public function getName(): string
    {
        return 'gemini';
    }

    public function recognize(string $imageBinary, string $mimeType): ?ProductResult
    {
        $apiKey = trim((string) AppConfig::get('gemini_api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $models = AppConfig::parseModelList(
            (string) AppConfig::get('gemini_model', 'gemini-2.0-flash'),
            (string) AppConfig::get('gemini_fallback_models', '')
        );

        if ($models === []) {
            $models = ['gemini-2.0-flash'];
        }

        $lastError = 'Gemini recognition failed.';
        foreach ($models as $model) {
            try {
                $result = $this->callModel($apiKey, $model, $imageBinary, $mimeType);
                if ($result !== null && $result->isComplete()) {
                    return $result;
                }
                $lastError = 'Gemini returned an incomplete result for model ' . $model . '.';
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                self::logSafe('Gemini model error: ' . $model . ' — ' . $e->getMessage());
            }
        }

        throw new RuntimeException($lastError);
    }

    public function testConnection(): array
    {
        $apiKey = trim((string) AppConfig::get('gemini_api_key', ''));
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'API key not configured'];
        }

        $model = (string) AppConfig::get('gemini_model', 'gemini-2.0-flash');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . '?key=' . rawurlencode($apiKey);

        $response = $this->httpJson('GET', $url, ['Accept' => 'application/json'], null, min(20, $this->timeout()));
        if (!$response['ok']) {
            $msg = $this->friendlyError($response['body'], $response['status'], $response['error'] ?? null);
            return ['ok' => false, 'message' => $msg];
        }

        return ['ok' => true, 'message' => 'Connected'];
    }

    private function callModel(string $apiKey, string $model, string $imageBinary, string $mimeType): ?ProductResult
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . ':generateContent?key=' . rawurlencode($apiKey);

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['text' => $this->visionPrompt()],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => base64_encode($imageBinary),
                        ],
                    ],
                ],
            ]],
            'generationConfig' => self::fastGenerationConfig($model, 500),
        ];

        $response = $this->httpJson(
            'POST',
            $url,
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $payload,
            $this->timeout()
        );

        if (!$response['ok'] && stripos(($response['body'] ?? '') . ($response['error'] ?? ''), 'thinking') !== false) {
            $payload['generationConfig'] = self::fastGenerationConfig($model, 500, false);
            $response = $this->httpJson(
                'POST',
                $url,
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                $payload,
                $this->timeout()
            );
        }

        if (!$response['ok']) {
            throw new RuntimeException($this->friendlyError($response['body'], $response['status'], $response['error'] ?? null));
        }

        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            throw new RuntimeException('Invalid Gemini response.');
        }

        $text = '';
        if (isset($json['candidates'][0]['content']['parts']) && is_array($json['candidates'][0]['content']['parts'])) {
            foreach ($json['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $text .= (string) $part['text'];
                }
            }
        }

        $data = $this->extractJsonObject($text);
        if ($data === null) {
            throw new RuntimeException('Gemini returned invalid JSON.');
        }

        return ProductResult::fromArray($data, 'gemini');
    }

    /**
     * Gemini 3.5 thinks at medium by default, which slows a short JSON answer.
     * 2.5 Flash can turn thinking off. Do not send both fields together.
     *
     * @return array<string, mixed>
     */
    public static function fastGenerationConfig(string $model, int $maxTokens, bool $limitThinking = true): array
    {
        $config = [
            'temperature' => 0.1,
            'maxOutputTokens' => $maxTokens,
            'responseMimeType' => 'application/json',
        ];
        if (!$limitThinking) {
            return $config;
        }
        if (preg_match('/gemini-2\.5/', $model)) {
            $config['thinkingConfig'] = ['thinkingBudget' => 0];
        } elseif (preg_match('/gemini-3/', $model)) {
            $config['thinkingConfig'] = ['thinkingLevel' => 'minimal'];
        }
        return $config;
    }

    private function friendlyError(string $body, int $status, ?string $curlError): string
    {
        if ($curlError) {
            if (stripos($curlError, 'timed out') !== false) {
                return 'Gemini request timed out.';
            }
            return 'Gemini network error.';
        }

        $decoded = json_decode($body, true);
        $message = '';
        if (is_array($decoded)) {
            $message = (string) ($decoded['error']['message'] ?? $decoded['message'] ?? '');
        }

        if ($status === 401 || $status === 403) {
            return 'Gemini authentication failed. Check the API key.';
        }
        if ($status === 429) {
            return 'Gemini rate limit reached. Try again later.';
        }
        if ($message !== '') {
            // Strip any accidental key fragments
            $message = preg_replace('/key=[A-Za-z0-9_\-]+/i', 'key=***', $message) ?? $message;
            return 'Gemini error: ' . mb_substr($message, 0, 180);
        }

        return 'Gemini request failed (HTTP ' . $status . ').';
    }

    private static function logSafe(string $message): void
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . preg_replace('/AIza[0-9A-Za-z_\-]{10,}/', 'AIza***', $message) . PHP_EOL;
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
