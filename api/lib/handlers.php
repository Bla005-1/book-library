<?php
declare(strict_types=1);

function handle_get(?int $id): void
{
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

    if ($stats_only) {
        respond(200, [
            'success' => true,
            'data' => fetch_book_stats(),
        ]);
    }

    if ($id === null) {
        $pageData = fetch_books_page($page, 10);
        $books = $pageData['books'];
        $total = $pageData['total'];
        $pageSize = $pageData['page_size'];

        respond(200, [
            'success' => true,
            'data' => $books,
            'count' => count($books),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    $book = fetch_book_by_id($id);
    if ($book === null) {
        respond(404, [
            'success' => false,
            'error' => ['message' => 'Book not found.'],
        ]);
    }

    respond(200, [
        'success' => true,
        'data' => $book,
    ]);
}

function handle_post(): void
{
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

    $created = create_book($clean);

    respond(201, [
        'success' => true,
        'data' => $created,
    ]);
}

function handle_put(?int $id): void
{
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

    $updated = update_book($id, $clean);
    if ($updated === null) {
        respond(404, [
            'success' => false,
            'error' => ['message' => 'Book not found.'],
        ]);
    }

    respond(200, [
        'success' => true,
        'data' => $updated,
    ]);
}

function handle_delete(?int $id): void
{
    if ($id === null) {
        respond(400, [
            'success' => false,
            'error' => ['message' => 'id parameter is required.'],
        ]);
    }

    if (!delete_book($id)) {
        respond(404, [
            'success' => false,
            'error' => ['message' => 'Book not found.'],
        ]);
    }

    respond(200, [
        'success' => true,
        'data' => ['id' => $id],
    ]);
}
