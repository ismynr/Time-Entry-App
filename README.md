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
- AI-assisted entry parser with confirmation before inserting a draft row. Defaults to `AI_PROVIDER=fake` for local demos; set `AI_PROVIDER=openai` or `AI_PROVIDER=anthropic` and provide the matching API key to use an external model.

## AI-Assisted Entry

The New Entries tab includes an AI-assisted parser. It never saves directly. The user types a sentence, reviews the parsed draft, then clicks `Insert row`.

Local demo mode:

```env
AI_PROVIDER=fake
```

External providers:

```env
AI_PROVIDER=openai
OPENAI_API_KEY=your-key
OPENAI_MODEL=gpt-5.4-mini
```

```env
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=your-key
ANTHROPIC_MODEL=claude-haiku-3.5
```

The fake provider supports predictable sentences such as:

```txt
Ari Wijaya worked on Platform Build 1 on 01/01/2026 doing Development for 4 hours.
```

## Verification

```bash
php artisan test
vendor/bin/pint --test
npm run build
```
