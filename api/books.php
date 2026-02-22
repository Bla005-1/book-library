<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/validation.php';
require_once __DIR__ . '/lib/handlers.php';

send_cors_headers();
handle_options_preflight();
send_json_headers();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE, OPTIONS');
    respond(405, [
        'success' => false,
        'error' => ['message' => 'Method not allowed.'],
    ]);
}

$id = null;
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        respond(400, [
            'success' => false,
            'error' => ['message' => 'Invalid id parameter.'],
        ]);
    }
}

if ($method === 'GET') {
    handle_get($id);
}

if ($method === 'POST') {
    handle_post();
}

if ($method === 'PUT') {
    handle_put($id);
}

if ($method === 'DELETE') {
    handle_delete($id);
}
