# GQL Blog

A small blog app with a GraphQL endpoint. PHP backend (webonyx/graphql-php), vanilla JS frontend, Bootstrap.

## Requirements

- PHP 8.1+
- Composer
- Docker Desktop

## Setup

1. Copy the config template:
   ```
   cp backend/config.example.php backend/config.php
   ```
   Edit `backend/config.php` if your local DB credentials differ from the defaults.

2. Install backend dependencies:
   ```
   cd backend
   composer install
   cd ..
   ```

3. Start MySQL. The first run automatically imports `backend/sql/schema.sql` and `seed.sql`:
   ```
   docker compose up -d
   ```

## Run

Open three terminals:

- **MySQL** (already running after `docker compose up -d`)
- **Backend** on port 8089:
  ```
  cd backend
  php -S localhost:8089 -t public
  ```
- **Frontend** on port 8090:
  ```
  cd frontend
  php -S localhost:8090
  ```

Then open `http://localhost:8090/login.html`.

## Test users

| Email             | Password   |
| alice@blog.com    | alice123   |
| bob@blog.com      | bob123     |
| carol@blog.com    | carol123   |

## Reset the database

```
docker compose down -v
docker compose up -d
```
