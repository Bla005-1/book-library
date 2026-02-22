<?php
declare(strict_types=1);

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
