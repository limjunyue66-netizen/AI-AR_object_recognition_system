<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/AppConfig.php';
require_once dirname(__DIR__) . '/includes/GeminiVision.php';

/**
 * Translate recognition fields into a selected language.
 * Prefers Gemini (if configured), falls back to MyMemory.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

try {
    $body = app_read_json_body();
    $target = strtolower(str_replace('_', '-', trim((string) ($body['target'] ?? 'en'))));
    if ($target === 'zh') {
        $target = 'zh-cn';
    }
    if (!preg_match('/^[a-z]{2,8}(-[a-z0-9]{2,8}){0,2}$/', $target)) {
        app_json_response(['ok' => false, 'error' => 'Unsupported language.'], 400);
    }

    $languageName = trim((string) ($body['languageName'] ?? ''));
    $languageName = preg_replace('/[^A-Za-z0-9 ()\/\-]/', '', $languageName) ?? '';
    if ($languageName === '') {
        $languageName = $target;
    }
    $languageName = mb_substr($languageName, 0, 80);

    $source = strtolower(str_replace('_', '-', trim((string) ($body['source'] ?? 'en'))));
    if ($source === 'zh') {
        $source = 'zh-cn';
    }
    if (!preg_match('/^[a-z]{2,8}(-[a-z0-9]{2,8}){0,2}$/', $source)) {
        $source = 'en';
    }

    $fields = $body['fields'] ?? null;
    if (!is_array($fields)) {
        app_json_response(['ok' => false, 'error' => 'fields object is required.'], 400);
    }

    $input = [];
    foreach ($fields as $key => $val) {
        if (!is_string($key) || !preg_match('/^[A-Za-z]{1,40}$/', $key) || !is_scalar($val)) {
            continue;
        }
        $input[$key] = mb_substr(trim((string) $val), 0, 500);
    }
    if ($input === []) {
        app_json_response(['ok' => false, 'error' => 'fields object is required.'], 400);
    }

    if ($source === $target) {
        app_json_response(['ok' => true, 'data' => $input, 'lang' => $target]);
    }

    $translated = translate_fields($input, $languageName, $source, $target);
    app_json_response(['ok' => true, 'data' => $translated, 'lang' => $target]);
} catch (Throwable $e) {
    app_log('translate.php: ' . $e->getMessage());
    app_json_response(['ok' => false, 'error' => 'Translation failed. Please try again.'], 500);
}

/**
 * @param array<string, string> $input
 * @return array<string, string>
 */
function translate_fields(array $input, string $languageName, string $source, string $target): array
{
    $gemini = translate_via_gemini($input, $languageName);
    if ($gemini !== null) {
        return keep_failed_fields($input, $gemini);
    }

    $out = [];
    foreach ($input as $key => $text) {
        if ($text === '' || $text === '—') {
            $out[$key] = $text;
            continue;
        }
        $out[$key] = translate_via_mymemory($text, $source, $target);
    }
    return keep_failed_fields($input, $out);
}

/**
 * @param array<string, string> $input
 * @param array<string, string> $translated
 * @return array<string, string>
 */
function keep_failed_fields(array $input, array $translated): array
{
    $out = [];
    foreach ($input as $key => $original) {
        $val = trim((string) ($translated[$key] ?? ''));
        $out[$key] = ($val !== '' && !translation_is_error($val)) ? $val : $original;
    }
    return $out;
}

function translation_is_error(string $text): bool
{
    return (bool) preg_match(
        '/please select two distinct languages|mymemory warning|invalid language|query length limit|no query specified/i',
        $text
    );
}

function memory_lang(string $code): string
{
    $code = strtolower(str_replace('_', '-', $code));
    if ($code === 'zh' || $code === 'zh-cn') {
        return 'zh-CN';
    }
    if ($code === 'zh-tw') {
        return 'zh-TW';
    }
    if (str_contains($code, '-')) {
        [$lang, $region] = explode('-', $code, 2);
        return $lang . '-' . strtoupper($region);
    }
    return $code;
}

/**
 * @param array<string, string> $input
 * @return array<string, string>|null
 */
function translate_via_gemini(array $input, string $languageName): ?array
{
    $apiKey = trim((string) AppConfig::get('gemini_api_key', ''));
    if ($apiKey === '') {
        return null;
    }

    $model = trim((string) AppConfig::get('gemini_model', 'gemini-2.5-flash'));
    if ($model === '' || preg_match('/\s/', $model)) {
        $model = 'gemini-2.5-flash';
    }

    $payloadJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $prompt = "Translate the JSON string values into {$languageName}. Keep JSON keys unchanged.\n"
        . "Keep well-known brand names unchanged when that is natural.\n"
        . "Return ONLY valid JSON with the same keys.\n\n"
        . $payloadJson;

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($model)
        . ':generateContent?key=' . rawurlencode($apiKey);

    $body = [
        'contents' => [[
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ]],
        'generationConfig' => GeminiVision::fastGenerationConfig($model, 700),
    ];

    $decoded = gemini_generate_json($url, $body);
    if ($decoded === null) {
        $body['generationConfig'] = GeminiVision::fastGenerationConfig($model, 700, false);
        $decoded = gemini_generate_json($url, $body);
    }
    if (!is_array($decoded)) {
        return null;
    }

    $out = [];
    foreach ($input as $key => $original) {
        $val = isset($decoded[$key]) ? trim((string) $decoded[$key]) : '';
        $out[$key] = $val !== '' ? mb_substr($val, 0, 500) : $original;
    }
    return $out;
}

/**
 * @param array<string, mixed> $body
 * @return array<string, mixed>|null
 */
function gemini_generate_json(string $url, array $body): ?array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 18,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
        return null;
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return null;
    }

    $text = '';
    foreach (($json['candidates'][0]['content']['parts'] ?? []) as $part) {
        if (isset($part['text'])) {
            $text .= (string) $part['text'];
        }
    }
    $text = trim($text);
    if ($text === '') {
        return null;
    }

    if (preg_match('/\{.*\}/s', $text, $m)) {
        $text = $m[0];
    }

    $decoded = json_decode($text, true);
    return is_array($decoded) ? $decoded : null;
}

function translate_via_mymemory(string $text, string $source, string $target): string
{
    $from = memory_lang($source);
    $to = memory_lang($target);
    if (strcasecmp($from, $to) === 0) {
        return $text;
    }

    $url = 'https://api.mymemory.translated.net/get?' . http_build_query([
        'q' => $text,
        'langpair' => $from . '|' . $to,
    ]);

    $ch = curl_init($url);
    if ($ch === false) {
        return $text;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'AI-AR-ObjectScanner/1.0',
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status < 200 || $status >= 300) {
        return $text;
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        return $text;
    }

    $out = trim((string) ($json['responseData']['translatedText'] ?? ''));
    if ($out === '' || translation_is_error($out)) {
        return $text;
    }

    $out = html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return mb_substr($out, 0, 500);
}
