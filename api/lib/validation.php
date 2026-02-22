<?php
declare(strict_types=1);

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
