<?php
declare(strict_types=1);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

function storage_path(): string
{
    return __DIR__ . '/books.json';
}

function open_storage()
{
    $path = storage_path();
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Storage file could not be opened.'],
        ]);
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Storage file could not be locked.'],
        ]);
    }
    return $handle;
}

function read_books($handle): array
{
    rewind($handle);
    $raw = stream_get_contents($handle);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Storage file is corrupted.'],
        ]);
    }
    return $data;
}

function write_books($handle, array $books): void
{
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($books, JSON_PRETTY_PRINT));
    fflush($handle);
}

function close_storage($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        respond(400, [
            'success' => false,
            'error' => ['message' => 'Invalid JSON body.'],
        ]);
    }
    return $data;
}

function parse_bool($value): ?bool
{
    if (is_bool($value)) {
        return $value;
    }
    if ($value === 1 || $value === 0) {
        return (bool) $value;
    }
    if (is_string($value)) {
        $lower = strtolower(trim($value));
        if ($lower === 'true' || $lower === '1') {
            return true;
        }
        if ($lower === 'false' || $lower === '0') {
            return false;
        }
    }
    return null;
}

function validate_book(array $data): array
{
    $errors = [];
    $clean = [];

    $fields = ['title', 'author', 'genre'];
    foreach ($fields as $field) {
        if (!array_key_exists($field, $data)) {
            $errors[$field] = 'Required.';
            continue;
        }
        $value = trim((string) $data[$field]);
        if ($value === '') {
            $errors[$field] = 'Must be a non-empty string.';
            continue;
        }
        $clean[$field] = $value;
    }

    if (!array_key_exists('pages', $data)) {
        $errors['pages'] = 'Required.';
    } else {
        $pages = filter_var($data['pages'], FILTER_VALIDATE_INT);
        if ($pages === false || $pages < 1) {
            $errors['pages'] = 'Must be a positive integer.';
        } else {
            $clean['pages'] = $pages;
        }
    }

    if (!array_key_exists('year', $data)) {
        $errors['year'] = 'Required.';
    } else {
        $year = filter_var($data['year'], FILTER_VALIDATE_INT);
        if ($year === false || $year < 0 || $year > 9999) {
            $errors['year'] = 'Must be an integer between 0 and 9999.';
        } else {
            $clean['year'] = $year;
        }
    }

    if (!array_key_exists('read', $data)) {
        $errors['read'] = 'Required.';
    } else {
        $read = parse_bool($data['read']);
        if ($read === null) {
            $errors['read'] = 'Must be a boolean.';
        } else {
            $clean['read'] = $read;
        }
    }

    return [$errors, $clean];
}

function find_book_index(array $books, int $id): int
{
    foreach ($books as $index => $book) {
        if (isset($book['id']) && (int) $book['id'] === $id) {
            return $index;
        }
    }
    return -1;
}

function next_id(array $books): int
{
    $max = 0;
    foreach ($books as $book) {
        if (isset($book['id']) && (int) $book['id'] > $max) {
            $max = (int) $book['id'];
        }
    }
    return $max + 1;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    respond(200, [
        'success' => true,
        'data' => ['message' => 'OK'],
    ]);
}

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
    $stats_only = false;
    if (array_key_exists('stats', $_GET)) {
        $stats_raw = $_GET['stats'];
        if ($stats_raw === '' || $stats_raw === null) {
            $stats_only = true;
        } else {
            $stats_bool = parse_bool($stats_raw);
            if ($stats_bool === null) {
                respond(400, [
                    'success' => false,
                    'error' => ['message' => 'Invalid stats parameter.'],
                ]);
            }
            $stats_only = $stats_bool;
        }
    }

    $page = 1;
    if (isset($_GET['page'])) {
        $page = filter_var($_GET['page'], FILTER_VALIDATE_INT);
        if ($page === false || $page < 1) {
            respond(400, [
                'success' => false,
                'error' => ['message' => 'Invalid page parameter.'],
            ]);
        }
    }

    $handle = open_storage();
    $books = read_books($handle);
    close_storage($handle);

    if ($stats_only) {
        $total = count($books);
        $read_count = 0;
        foreach ($books as $book) {
            if (!empty($book['read'])) {
                $read_count += 1;
            }
        }
        $unread = $total - $read_count;
        $percent = $total === 0 ? 0 : round(($read_count / $total) * 100, 1);

        respond(200, [
            'success' => true,
            'data' => [
                'total' => $total,
                'read' => $read_count,
                'unread' => $unread,
                'percent' => $percent,
            ],
        ]);
    }

    if ($id === null) {
        $page_size = 10;
        $total = count($books);
        $offset = ($page - 1) * $page_size;
        $paged = array_slice($books, $offset, $page_size);
        respond(200, [
            'success' => true,
            'data' => $paged,
            'count' => count($paged),
            'total' => $total,
            'page' => $page,
            'page_size' => $page_size,
        ]);
    }

    $index = find_book_index($books, $id);
    if ($index === -1) {
        respond(404, [
            'success' => false,
            'error' => ['message' => 'Book not found.'],
        ]);
    }

    respond(200, [
        'success' => true,
        'data' => $books[$index],
    ]);
}

if ($method === 'POST') {
    $input = get_json_body();
    [$errors, $clean] = validate_book($input);
    if (!empty($errors)) {
        respond(422, [
            'success' => false,
            'error' => [
                'message' => 'Validation failed.',
                'details' => $errors,
            ],
        ]);
    }

    $handle = open_storage();
    $books = read_books($handle);
    $clean['id'] = next_id($books);
    $books[] = $clean;
    write_books($handle, $books);
    close_storage($handle);

    respond(201, [
        'success' => true,
        'data' => $clean,
    ]);
}

if ($method === 'PUT') {
    if ($id === null) {
        respond(400, [
            'success' => false,
            'error' => ['message' => 'id parameter is required.'],
        ]);
    }

    $input = get_json_body();
    [$errors, $clean] = validate_book($input);
    if (!empty($errors)) {
        respond(422, [
            'success' => false,
            'error' => [
                'message' => 'Validation failed.',
                'details' => $errors,
            ],
        ]);
    }

    $handle = open_storage();
    $books = read_books($handle);
    $index = find_book_index($books, $id);
    if ($index === -1) {
        close_storage($handle);
        respond(404, [
            'success' => false,
            'error' => ['message' => 'Book not found.'],
        ]);
    }

    $clean['id'] = $id;
    $books[$index] = $clean;
    write_books($handle, $books);
    close_storage($handle);

    respond(200, [
        'success' => true,
        'data' => $clean,
    ]);
}

if ($method === 'DELETE') {
    if ($id === null) {
        respond(400, [
            'success' => false,
            'error' => ['message' => 'id parameter is required.'],
        ]);
    }

    $handle = open_storage();
    $books = read_books($handle);
    $index = find_book_index($books, $id);
    if ($index === -1) {
        close_storage($handle);
        respond(404, [
            'success' => false,
            'error' => ['message' => 'Book not found.'],
        ]);
    }

    array_splice($books, $index, 1);
    write_books($handle, $books);
    close_storage($handle);

    respond(200, [
        'success' => true,
        'data' => ['id' => $id],
    ]);
}
