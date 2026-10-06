<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/ObjectRecognizer.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $action = (string) ($_GET['action'] ?? 'get');

        if ($action === 'test') {
            app_require_settings_auth();
            $recognizer = new ObjectRecognizer();
            $results = $recognizer->testProviders();
            app_json_response(['ok' => true, 'data' => $results]);
        }

        if ($action === 'qr_url') {
            app_json_response([
                'ok' => true,
                'data' => [
                    'url' => app_public_url('/index.php'),
                ],
            ]);
        }

        app_json_response([
            'ok' => true,
            'data' => AppConfig::publicSettings(),
        ]);
    }

    if ($method === 'POST') {
        app_require_settings_auth();
        $body = app_read_json_body();
        $action = (string) ($body['action'] ?? 'save');

        if ($action === 'test') {
            $recognizer = new ObjectRecognizer();
            $results = $recognizer->testProviders();
            app_json_response(['ok' => true, 'data' => $results]);
        }

        $ok = AppConfig::save($body);
        if (!$ok) {
            app_json_response(['ok' => false, 'error' => 'Unable to save settings. Check file permissions.'], 500);
        }

        app_json_response([
            'ok' => true,
            'data' => AppConfig::publicSettings(),
            'message' => 'Settings saved.',
        ]);
    }

    app_json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    app_log('api/settings.php: ' . $e->getMessage());
    app_json_response(['ok' => false, 'error' => 'Server error while handling settings.'], 500);
}
