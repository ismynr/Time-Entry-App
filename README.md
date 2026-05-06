# Time Entry App

Laravel 13 + Vue 3 single-page time-entry app.

## Stack

- PHP 8.4
- Laravel 13
- Node 22
- Vue 3
- Vite 8
- MySQL 8.0 or 8.1

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
/opt/homebrew/opt/mysql@8.0/bin/mysql -u root -h 127.0.0.1 -P 3306 -e "CREATE DATABASE IF NOT EXISTS time_entry_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Open:

```txt
http://127.0.0.1:8000
```

For active frontend development, run `npm run dev` in a second terminal.

The application is configured for MySQL 8.0/8.1. Keep `.env` on `DB_CONNECTION=mysql`; SQLite is only used by PHPUnit through `phpunit.xml` for isolated in-memory tests.

On this machine, Homebrew MySQL 8.0 is installed at `/opt/homebrew/opt/mysql@8.0`. It is running as a Homebrew service but is not linked as `mysql` on `PATH`.

## Features

- Top company filter with `All companies` mode.
- New Entries tab with editable bulk rows.
- Company-scoped employee, project, and task dropdowns.
- Add, remove, duplicate, and clear row actions.
- Server-side row validation for company relationships, employee-project assignment, max 24 hours/day, and one project per employee/date.
- History tab with company/date/employee/project filtering and server-side pagination.
- Seed data with 3 companies, 8 employees, multi-company assignment, project assignment examples, and conflict-ready time entries.

## Verification

```bash
php artisan test
vendor/bin/pint --test
npm run build
```
