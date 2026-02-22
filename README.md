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
  pages INT,
  year INT,
  is_read INT
);
```

Backend DB connection settings are read from environment variables (with defaults):

- `DB_HOST` (default `127.0.0.1`)
- `DB_USER` (default empty)
- `DB_PASS` (default empty)
- `DB_NAME` (default `testing`)
- `DB_PORT` (default `3306`)

Loom video demo:
https://www.loom.com/share/3299773740044da182ac03e08c910fe2
