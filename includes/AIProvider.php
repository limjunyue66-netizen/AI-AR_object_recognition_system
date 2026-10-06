<?php

declare(strict_types=1);

/**
 * Shared vision prompt and JSON extraction helpers.
 */
abstract class AIProvider
{
    protected string $responseLanguage = '';

    abstract public function getName(): string;

    public function setResponseLanguage(string $languageName): void
    {
        $clean = preg_replace('/[^A-Za-z0-9 ()\/\-]/', '', $languageName) ?? '';
        $this->responseLanguage = mb_substr(trim($clean), 0, 80);
    }

    /**
     * @return ProductResult|null
     */
    abstract public function recognize(string $imageBinary, string $mimeType): ?ProductResult;

    /**
     * Lightweight connectivity check.
     *
     * @return array{ok:bool, message:string}
     */
    abstract public function testConnection(): array;

    protected function visionPrompt(): string
    {
        $prompt = <<<'PROMPT'
You are a careful visual product recognition assistant.

Analyze the provided image and identify the MAIN physical object.

Rules:
1. Prioritize visible evidence only (shape, logos, labels, text, packaging).
2. Read brand names, model numbers, and product labels when clearly visible.
3. NEVER invent an exact model number or product version when evidence is insufficient.
4. If the exact model is unclear, return a generic name (e.g. "Apple iPhone", "HP Laptop", "Sony headphones").
5. Do NOT invent specifications that are not supported by the image.
6. Provide a bounding box for the main object using normalized coordinates from 0 to 1000:
   ymin, xmin, ymax, xmax.
7. Confidence must be a number between 0 and 1.

Return ONLY valid JSON with this exact structure (no markdown, no extra text):
{
  "objectLabel": "short object type",
  "productName": "best product name without inventing models",
  "manufacturer": "brand or Unknown",
  "specification": "short useful specs if visible, else brief generic description",
  "description": "one or two short factual sentences",
  "boundingBox": {
    "ymin": 0,
    "xmin": 0,
    "ymax": 1000,
    "xmax": 1000
  },
  "confidence": 0.0
}
PROMPT;
        $lang = $this->responseLanguage;
        if ($lang !== '' && strcasecmp($lang, 'English') !== 0) {
            $prompt .= "\nWrite objectLabel, productName, manufacturer, specification, and description in {$lang}. "
                . "Keep well-known brand names in their usual form. Do not translate the JSON keys.\n";
        }
        return $prompt;
    }

    protected function extractJsonObject(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Strip markdown fences if present
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $text, $m)) {
            $text = $m[1];
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $slice = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($slice, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Shared HTTP JSON helper.
     *
     * @param array<string, string> $headers
     * @return array{ok:bool, status:int, body:string, error?:string}
     */
    protected function httpJson(string $method, string $url, array $headers, ?array $payload, int $timeout): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Unable to start HTTP request.'];
        }

        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => $err !== '' ? $err : 'Network error.'];
        }

        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => (string) $body];
    }

    protected function timeout(): int
    {
        return max(10, min(120, (int) AppConfig::get('request_timeout', 45)));
    }
}
