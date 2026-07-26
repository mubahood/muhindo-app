# muhindo-app

Muhindo Mubaraka's personal platform: a **public portfolio site** in front, and — behind
one login — a **learning management system** for students taking his courses and a
**client/project management system** for tracking work delivered to clients.

Built on Laravel 12 (PHP 8.2+), Blade + Livewire + Alpine.js, Tailwind CSS. See
`PROJECT_PLAN.md` for the full build plan this codebase was built from.

## What's here

- **Public site** (`/`) — portfolio (about, projects, skills, experience, research,
  products, contact), backed by a database-driven CMS editable from the admin.
- **Courses** (`/courses`) — a public catalogue; free courses self-enrol, paid courses
  checkout via Flutterwave.
- **`/dashboard`** — one entry point after login; content branches by role:
  - **Owner / admin** — full back office: portfolio CMS, courses, clients, projects,
    invoices/payments, users, settings.
  - **Student** (`/learn`) — enrolled courses, lesson player, progress, certificates.
  - **Client** (`/portal`) — their own projects, progress updates, documents, invoices.

## Requirements

- PHP 8.2+, Composer 2
- MySQL 5.7+/8 (MAMP socket supported) — the app uses a `muhindo_app` database
- Node 18+ (for building front-end assets)

## Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
#   Set DB_DATABASE=muhindo_app, DB_USERNAME/DB_PASSWORD, and (MAMP) DB_SOCKET.

mysql -uroot -proot -e "CREATE DATABASE muhindo_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate --seed   # roles, owner account, districts, portfolio content
php artisan storage:link     # avatars / uploads on the public disk
npm run build                # or `npm run dev` while developing
php artisan serve
```

## Tests

```bash
php artisan test     # test suite (SQLite in-memory)
vendor/bin/pint       # code style
vendor/bin/phpstan analyse   # static analysis
```

## Notes

- **Payments:** Flutterwave, behind a swappable `PaymentGateway` interface
  (`app/Services/Gateway/`). Set `FLW_*` keys in `.env` to enable checkout.
- **Documents:** project documents are stored on the private `local` disk and only ever
  streamed through an authorization check — never web-served directly.
- **Branding:** logo/favicon assets under `public/` are still placeholders inherited from
  this codebase's source project — swap them for real brand assets before shipping.
