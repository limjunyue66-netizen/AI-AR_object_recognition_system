<?php

declare(strict_types=1);

require_once __DIR__ . '/AIProvider.php';
require_once __DIR__ . '/ProductResult.php';
require_once __DIR__ . '/AppConfig.php';

/**
 * Agnes AI Vision provider (OpenAI-compatible chat completions).
 */
final class AgnesVision extends AIProvider
{
    public function getName(): string
    {
        return 'agnes';
    }

    public function recognize(string $imageBinary, string $mimeType): ?ProductResult
    {
        $apiKey = trim((string) AppConfig::get('agnes_api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('Agnes API key is not configured.');
        }

        $models = AppConfig::parseModelList(
            (string) AppConfig::get('agnes_model', 'agnes-2.5-flash'),
            (string) AppConfig::get('agnes_fallback_models', '')
        );
        if ($models === []) {
            $models = ['agnes-2.5-flash'];
        }

        $lastError = 'Agnes recognition failed.';
        foreach ($models as $model) {
            try {
                $result = $this->callModel($apiKey, $model, $imageBinary, $mimeType);
                if ($result !== null && $result->isComplete()) {
                    return $result;
                }
                $lastError = 'Agnes returned an incomplete result for model ' . $model . '.';
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                self::logSafe('Agnes model error: ' . $model . ' — ' . $e->getMessage());
            }
        }

        throw new RuntimeException($lastError);
    }

    public function testConnection(): array
    {
        $apiKey = trim((string) AppConfig::get('agnes_api_key', ''));
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'API key not configured'];
        }

        $base = rtrim((string) AppConfig::get('agnes_base_url', 'https://apihub.agnes-ai.com/v1'), '/');
        $url = $base . '/models';

        $response = $this->httpJson(
            'GET',
            $url,
            [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ],
            null,
            min(20, $this->timeout())
        );

        if (!$response['ok']) {
            // Fallback: tiny chat ping without image
            return $this->testWithChat($apiKey, $base);
        }

        return ['ok' => true, 'message' => 'Connected'];
    }

    private function testWithChat(string $apiKey, string $base): array
    {
        $model = (string) AppConfig::get('agnes_model', 'agnes-2.5-flash');
        $url = $base . '/chat/completions';
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Reply with OK'],
            ],
            'max_tokens' => 8,
        ];

        $response = $this->httpJson(
            'POST',
            $url,
            [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            $payload,
            min(25, $this->timeout())
        );

        if (!$response['ok']) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($response['body'], $response['status'], $response['error'] ?? null),
            ];
        }

        return ['ok' => true, 'message' => 'Connected'];
    }

    private function callModel(string $apiKey, string $model, string $imageBinary, string $mimeType): ?ProductResult
    {
        $base = rtrim((string) AppConfig::get('agnes_base_url', 'https://apihub.agnes-ai.com/v1'), '/');
        $url = $base . '/chat/completions';

        $dataUri = 'data:' . $mimeType . ';base64,' . base64_encode($imageBinary);

        $payload = [
            'model' => $model,
            'temperature' => 0.1,
            'max_tokens' => 500,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->visionPrompt()],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => $dataUri],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->httpJson(
            'POST',
            $url,
            [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            $payload,
            $this->timeout()
        );

        if (!$response['ok']) {
            throw new RuntimeException($this->friendlyError($response['body'], $response['status'], $response['error'] ?? null));
        }

        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            throw new RuntimeException('Invalid Agnes response.');
        }

        $text = (string) ($json['choices'][0]['message']['content'] ?? '');
        if (is_array($json['choices'][0]['message']['content'] ?? null)) {
            $parts = $json['choices'][0]['message']['content'];
            $text = '';
            foreach ($parts as $part) {
                if (is_array($part) && isset($part['text'])) {
                    $text .= (string) $part['text'];
                } elseif (is_string($part)) {
                    $text .= $part;
                }
            }
        }

        $data = $this->extractJsonObject($text);
        if ($data === null) {
            throw new RuntimeException('Agnes returned invalid JSON.');
        }

        return ProductResult::fromArray($data, 'agnes');
    }

    private function friendlyError(string $body, int $status, ?string $curlError): string
    {
        if ($curlError) {
            if (stripos($curlError, 'timed out') !== false) {
                return 'Agnes request timed out.';
            }
            return 'Agnes network error.';
        }

        $decoded = json_decode($body, true);
        $message = '';
        if (is_array($decoded)) {
            $message = (string) ($decoded['error']['message'] ?? $decoded['message'] ?? '');
            if ($message === '' && isset($decoded['error']) && is_string($decoded['error'])) {
                $message = $decoded['error'];
            }
        }

        if ($status === 401 || $status === 403) {
            return 'Agnes authentication failed. Check the API key.';
        }
        if ($status === 429) {
            return 'Agnes rate limit reached. Try again later.';
        }
        if ($message !== '') {
            $message = preg_replace('/Bearer\s+[A-Za-z0-9_\-\.]+/i', 'Bearer ***', $message) ?? $message;
            return 'Agnes error: ' . mb_substr($message, 0, 180);
        }

        return 'Agnes request failed (HTTP ' . $status . ').';
    }

    private static function logSafe(string $message): void
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
