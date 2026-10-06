<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/ScanHistory.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id > 0) {
            $item = ScanHistory::getById($id);
            if ($item === null) {
                app_json_response(['ok' => false, 'error' => 'Record not found.'], 404);
            }
            app_json_response(['ok' => true, 'data' => $item]);
        }

        $search = (string) ($_GET['q'] ?? '');
        $page = (int) ($_GET['page'] ?? 1);
        $data = ScanHistory::list($search, $page, 20);
        app_json_response(['ok' => true, 'data' => $data]);
    }

    if ($method === 'DELETE') {
        $deleted = ScanHistory::clear();
        app_json_response(['ok' => true, 'data' => ['deleted' => $deleted]]);
    }

    if ($method === 'POST') {
        $body = app_read_json_body();
        $action = (string) ($body['action'] ?? ($_GET['action'] ?? ''));
        if ($action === 'clear') {
            $deleted = ScanHistory::clear();
            app_json_response(['ok' => true, 'data' => ['deleted' => $deleted]]);
        }
        app_json_response(['ok' => false, 'error' => 'Unknown action.'], 400);
    }

    app_json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    app_log('api/history.php: ' . $e->getMessage());
    app_json_response(['ok' => false, 'error' => 'Unable to access scan history.'], 500);
}
