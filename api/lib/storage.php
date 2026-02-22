<?php
declare(strict_types=1);

const DEFAULT_PLACEHOLDER_IMAGE_URL = 'https://placehold.co/96x128?text=No+Image';

function db_connection(): mysqli
{
    static $conn = null;
    static $mysqliReportingInitialized = false;

    if ($mysqliReportingInitialized === false) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqliReportingInitialized = true;
    }

    if ($conn instanceof mysqli) {
        return $conn;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $user = getenv('DB_USER') ?: '';
    $pass = getenv('DB_PASS') ?: '';
    $name = getenv('DB_NAME') ?: 'testing';
    $port = (int) (getenv('DB_PORT') ?: '3306');

    $conn = mysqli_init();
    if ($conn === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Database initialization failed.'],
        ]);
    }

    if (!$conn->real_connect($host, $user, $pass, $name, $port)) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Database connection failed.'],
        ]);
    }

    if (!$conn->set_charset('utf8mb4')) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Database charset configuration failed.'],
        ]);
    }

    return $conn;
}

function map_book_row(array $row): array
{
    $imageUrl = trim((string) ($row['image_url'] ?? ''));
    if ($imageUrl === '') {
        $imageUrl = DEFAULT_PLACEHOLDER_IMAGE_URL;
    }

    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'author' => (string) $row['author'],
        'genre' => (string) $row['genre'],
        'image_url' => $imageUrl,
        'pages' => (int) $row['pages'],
        'year' => (int) $row['year'],
        'read' => (int) $row['is_read'] === 1,
    ];
}

function books_where_clause_and_params(string $search): array
{
    $searchTerm = trim($search);
    if ($searchTerm === '') {
        return ['sql' => '', 'types' => '', 'values' => []];
    }

    return [
        'sql' => ' WHERE title LIKE ?',
        'types' => 's',
        'values' => ['%' . $searchTerm . '%'],
    ];
}

function fetch_books_page(
    int $page,
    int $pageSize = 10,
    string $search = '',
    string $sortDirection = 'desc'
): array
{
    $conn = db_connection();
    $offset = ($page - 1) * $pageSize;
    $normalizedSort = strtolower($sortDirection) === 'asc' ? 'ASC' : 'DESC';
    $filters = books_where_clause_and_params($search);
    $searchLike = '';

    $countSql = 'SELECT COUNT(*) AS total FROM book_lib' . $filters['sql'];
    $countStmt = $conn->prepare($countSql);
    if ($countStmt === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'We could not prepare the count query. Please try again.'],
        ]);
    }

    if ($filters['types'] !== '') {
        $searchLike = $filters['values'][0];
        $countStmt->bind_param('s', $searchLike);
    }

    if (!$countStmt->execute()) {
        $countStmt->close();
        respond(500, [
            'success' => false,
            'error' => ['message' => 'We could not count books right now. Please try again.'],
        ]);
    }

    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $countStmt->close();
    $total = isset($countRow['total']) ? (int) $countRow['total'] : 0;

    $stmt = $conn->prepare(
        'SELECT id, title, author, genre, image_url, pages, year, is_read
         FROM book_lib' . $filters['sql'] . '
         ORDER BY year ' . $normalizedSort . ', id ' . $normalizedSort . '
         LIMIT ? OFFSET ?'
    );

    if ($stmt === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'We could not prepare the list query. Please try again.'],
        ]);
    }

    if ($filters['types'] !== '') {
        $stmt->bind_param('sii', $searchLike, $pageSize, $offset);
    } else {
        $stmt->bind_param('ii', $pageSize, $offset);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        respond(500, [
            'success' => false,
            'error' => ['message' => 'We could not load books right now. Please try again.'],
        ]);
    }

    $result = $stmt->get_result();
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = map_book_row($row);
    }

    $stmt->close();

    return [
        'books' => $books,
        'total' => $total,
        'page_size' => $pageSize,
        'search' => $search,
        'sort' => strtolower($normalizedSort),
    ];
}

function fetch_book_by_id(int $id): ?array
{
    $conn = db_connection();
    $stmt = $conn->prepare(
        'SELECT id, title, author, genre, image_url, pages, year, is_read
         FROM book_lib
         WHERE id = ?
         LIMIT 1'
    );

    if ($stmt === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to prepare fetch query.'],
        ]);
    }

    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        $stmt->close();
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to fetch book.'],
        ]);
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!is_array($row)) {
        return null;
    }

    return map_book_row($row);
}

function fetch_book_stats(): array
{
    $conn = db_connection();
    $result = $conn->query('SELECT COUNT(*) AS total, COALESCE(SUM(is_read), 0) AS read_count FROM book_lib');

    if ($result === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to fetch stats.'],
        ]);
    }

    $row = $result->fetch_assoc();
    $result->free();

    $total = isset($row['total']) ? (int) $row['total'] : 0;
    $readCount = isset($row['read_count']) ? (int) $row['read_count'] : 0;
    $unread = $total - $readCount;

    return [
        'total' => $total,
        'read' => $readCount,
        'unread' => $unread,
        'percent' => $total === 0 ? 0 : round(($readCount / $total) * 100, 1),
    ];
}

function create_book(array $book): array
{
    $conn = db_connection();
    $stmt = $conn->prepare(
        'INSERT INTO book_lib (title, author, genre, image_url, pages, year, is_read)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    if ($stmt === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to prepare insert query.'],
        ]);
    }

    $readValue = $book['read'] ? 1 : 0;

    $stmt->bind_param(
        'ssssiii',
        $book['title'],
        $book['author'],
        $book['genre'],
        $book['image_url'],
        $book['pages'],
        $book['year'],
        $readValue
    );

    if (!$stmt->execute()) {
        $stmt->close();
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to create book.'],
        ]);
    }

    $newId = (int) $conn->insert_id;
    $stmt->close();

    $created = fetch_book_by_id($newId);
    if ($created === null) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Created book could not be reloaded.'],
        ]);
    }

    return $created;
}

function update_book(int $id, array $book): ?array
{
    if (fetch_book_by_id($id) === null) {
        return null;
    }

    $conn = db_connection();
    $stmt = $conn->prepare(
        'UPDATE book_lib
         SET title = ?, author = ?, genre = ?, image_url = ?, pages = ?, year = ?, is_read = ?
         WHERE id = ?'
    );

    if ($stmt === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to prepare update query.'],
        ]);
    }

    $readValue = $book['read'] ? 1 : 0;

    $stmt->bind_param(
        'ssssiiii',
        $book['title'],
        $book['author'],
        $book['genre'],
        $book['image_url'],
        $book['pages'],
        $book['year'],
        $readValue,
        $id
    );

    if (!$stmt->execute()) {
        $stmt->close();
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to update book.'],
        ]);
    }

    $stmt->close();

    return fetch_book_by_id($id);
}

function delete_book(int $id): bool
{
    $conn = db_connection();
    $stmt = $conn->prepare('DELETE FROM book_lib WHERE id = ?');

    if ($stmt === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to prepare delete query.'],
        ]);
    }

    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        $stmt->close();
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to delete book.'],
        ]);
    }

    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    return $deleted;
}
