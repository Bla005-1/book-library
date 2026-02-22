Book Library - Local Collection Manager
CPSC 3750 - Solo Project 2

Netlify url: https://resplendent-taiyaki-c6f46f.netlify.app/

Backend is written in PHP.

MySQL Persistence: All data is stored in a MySQL table named `book_lib`. The PHP backend performs CRUD operations directly against MySQL, and the frontend does not store data locally.

Expected table schema:

```sql
CREATE TABLE book_lib (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255),
  author VARCHAR(255),
  genre VARCHAR(255),
  image_url VARCHAR(1024) NOT NULL,
  pages INT,
  year INT,
  is_read INT
);
```

If you already have the table, run this migration once:

```sql
ALTER TABLE book_lib
  ADD COLUMN image_url VARCHAR(1024) NOT NULL DEFAULT 'https://placehold.co/96x128?text=No+Image';

UPDATE book_lib
SET image_url = 'https://placehold.co/96x128?text=No+Image'
WHERE image_url IS NULL OR TRIM(image_url) = '';
```

Backend DB connection settings are read from environment variables (with defaults):

- `DB_HOST` (default `127.0.0.1`)
- `DB_USER` (default empty)
- `DB_PASS` (default empty)
- `DB_NAME` (default `testing`)
- `DB_PORT` (default `3306`)

List API supports server-side filtering/sorting/paging:

- `GET /api/books.php?page=1&page_size=10&sort=desc&search=harry`
- `page_size` allowed values: `5`, `10`, `20`, `50`
- `sort` values: `desc` (newest first, default) or `asc` (oldest first)
- `search` performs partial title matching

Loom video demo:
https://www.loom.com/share/3299773740044da182ac03e08c910fe2
