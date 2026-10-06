<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/ImageValidator.php';
require_once dirname(__DIR__) . '/includes/ObjectRecognizer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

try {
    $body = app_read_json_body();
    $image = (string) ($body['image'] ?? '');
    $languageName = trim((string) ($body['languageName'] ?? ''));
    $languageName = preg_replace('/[^A-Za-z0-9 ()\/\-]/', '', $languageName) ?? '';
    $languageName = mb_substr($languageName, 0, 80);
    $target = strtolower(str_replace('_', '-', trim((string) ($body['target'] ?? 'en'))));
    if ($target === 'zh') {
        $target = 'zh-cn';
    }
    if ($target === '' || $target === 'en' || !preg_match('/^[a-z]{2,8}(-[a-z0-9]{2,8}){0,2}$/', $target)) {
        $languageName = '';
        $target = 'en';
    }

    if ($image === '') {
        app_json_response(['ok' => false, 'error' => 'Image data is required.'], 400);
    }

    $validated = ImageValidator::validateBase64($image);
    if (!$validated['ok']) {
        app_json_response(['ok' => false, 'error' => $validated['error'] ?? 'Invalid image.'], 400);
    }

    $recognizer = new ObjectRecognizer();
    $outcome = $recognizer->recognize($validated['binary'], $validated['mime'], $languageName);
    /** @var ProductResult $result */
    $result = $outcome['result'];

    app_json_response([
        'ok' => true,
        'data' => $result->toArray(),
        'meta' => [
            'stage' => $outcome['stage'],
            'lang' => $target,
        ],
    ]);
} catch (Throwable $e) {
    app_log('recognize.php: ' . $e->getMessage());
    $message = $e->getMessage();
    $safeMessages = [
        'AI provider is not configured.',
        'Unable to identify the object.',
        'Empty image.',
        'Invalid Base64 image data.',
        'Unable to decode image data.',
        'Image is too large.',
        'Invalid image content.',
        'Unsupported image type.',
        'Image is too small.',
    ];

    $out = in_array($message, $safeMessages, true) ? $message : (
        str_starts_with($message, 'Gemini ') || str_starts_with($message, 'Agnes ')
            ? $message
            : 'Unable to identify the object.'
    );

    $status = ($out === 'AI provider is not configured.') ? 503 : 422;
    app_json_response(['ok' => false, 'error' => $out], $status);
}
