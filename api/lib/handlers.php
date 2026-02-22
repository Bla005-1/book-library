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

    if ($stats_only) {
        respond(200, [
            'success' => true,
            'data' => fetch_book_stats(),
        ]);
    }

    if ($id !== null) {
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

    $page = 1;
    if (isset($_GET['page'])) {
        $page = filter_var($_GET['page'], FILTER_VALIDATE_INT);
        if ($page === false || $page < 1) {
            respond(400, [
                'success' => false,
                'error' => ['message' => 'Invalid page parameter. Please provide a positive integer.'],
            ]);
        }
    }

    $pageSize = 10;
    if (isset($_GET['page_size'])) {
        $pageSize = filter_var($_GET['page_size'], FILTER_VALIDATE_INT);
        $validPageSizes = [5, 10, 20, 50];
        if ($pageSize === false || !in_array($pageSize, $validPageSizes, true)) {
            respond(400, [
                'success' => false,
                'error' => ['message' => 'Invalid page_size parameter. Allowed values: 5, 10, 20, 50.'],
            ]);
        }
    }

    $search = '';
    if (isset($_GET['search'])) {
        $search = trim((string) $_GET['search']);
        if (strlen($search) > 255) {
            respond(400, [
                'success' => false,
                'error' => ['message' => 'Search text is too long. Maximum length is 255 characters.'],
            ]);
        }
    }

    $sort = 'desc';
    if (isset($_GET['sort'])) {
        $sort = strtolower(trim((string) $_GET['sort']));
        if (!in_array($sort, ['asc', 'desc'], true)) {
            respond(400, [
                'success' => false,
                'error' => ['message' => 'Invalid sort parameter. Use asc or desc.'],
            ]);
        }
    }

    $pageData = fetch_books_page($page, $pageSize, $search, $sort);
    $books = $pageData['books'];
    $total = $pageData['total'];
    $responsePageSize = $pageData['page_size'];

    respond(200, [
        'success' => true,
        'data' => $books,
        'count' => count($books),
        'total' => $total,
        'page' => $page,
        'page_size' => $responsePageSize,
        'search' => $pageData['search'],
        'sort' => $pageData['sort'],
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
