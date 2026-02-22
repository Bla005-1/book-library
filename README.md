Book Library - Local Collection Manager
CPSC 3750 - Solo Project 3

url = https://cpsc.loosesocket.com

### Tech
- Domain loosesocket.com purchased from cloudfare
- Hosted with Digital Ocean
- Simple webserver with nginx + php + html + mysql
- The database is a mysql server hosted on the same vps as the web server

### To deploy:
- Provision a VPS.
- Install and configure Nginx, PHP, and MySQL.
- Clone the repository from GitHub onto the server.
- Configure environment variables for database access.
- Create the MySQL database.
- Run the provided table schema to create the book_lib table.
- Seed the database.
- Configure Nginx to serve the application.
- Enable HTTPS using a valid SSL certificate.

### To updates:
- Pull the latest changes from GitHub.
- Restart PHP/Nginx if necessary.
- Apply any required database schema updates.

### Expected table schema:

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

### ENV
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

