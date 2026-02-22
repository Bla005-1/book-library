<?php
declare(strict_types=1);

function db_connection(): mysqli
{
    static $conn = null;

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
    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'author' => (string) $row['author'],
        'genre' => (string) $row['genre'],
        'pages' => (int) $row['pages'],
        'year' => (int) $row['year'],
        'read' => (int) $row['is_read'] === 1,
    ];
}

function fetch_books_page(int $page, int $pageSize = 10): array
{
    $conn = db_connection();
    $offset = ($page - 1) * $pageSize;

    $countResult = $conn->query('SELECT COUNT(*) AS total FROM book_lib');
    if ($countResult === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to fetch book count.'],
        ]);
    }

    $countRow = $countResult->fetch_assoc();
    $countResult->free();
    $total = isset($countRow['total']) ? (int) $countRow['total'] : 0;

    $stmt = $conn->prepare(
        'SELECT id, title, author, genre, pages, year, is_read
         FROM book_lib
         ORDER BY id DESC
         LIMIT ? OFFSET ?'
    );

    if ($stmt === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to prepare list query.'],
        ]);
    }

    $stmt->bind_param('ii', $pageSize, $offset);

    if (!$stmt->execute()) {
        $stmt->close();
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to fetch books.'],
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
    ];
}

function fetch_book_by_id(int $id): ?array
{
    $conn = db_connection();
    $stmt = $conn->prepare(
        'SELECT id, title, author, genre, pages, year, is_read
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
        'INSERT INTO book_lib (title, author, genre, pages, year, is_read)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    if ($stmt === false) {
        respond(500, [
            'success' => false,
            'error' => ['message' => 'Failed to prepare insert query.'],
        ]);
    }

    $readValue = $book['read'] ? 1 : 0;

    $stmt->bind_param(
        'sssiii',
        $book['title'],
        $book['author'],
        $book['genre'],
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
         SET title = ?, author = ?, genre = ?, pages = ?, year = ?, is_read = ?
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
        'sssiiii',
        $book['title'],
        $book['author'],
        $book['genre'],
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
