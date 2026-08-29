# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

This is a fresh, unmodified Laravel 12 application skeleton (`laravel/laravel` scaffold). It has no custom domain code yet — no custom models beyond `User`, no controllers beyond the base `Controller` class, no non-default routes, and no migrations beyond the framework defaults (users, cache, jobs tables). Despite the repo name ("learning-platform"), no learning-platform-specific features have been built. Treat this as a greenfield starting point rather than an existing system with established conventions to infer.

Note: this directory is not currently a git repository.

## Stack

- **Backend**: PHP 8.2+, Laravel 12
- **Database**: SQLite by default (`DB_CONNECTION=sqlite`), configurable via `.env`
- **Frontend build**: Vite 7 + Tailwind CSS 4 (via `@tailwindcss/vite`), plain JS (no framework like Vue/React installed)
- **Testing**: PHPUnit 11 (feature/unit split), Mockery, FakerPHP
- **Dev tooling**: Laravel Pint (code style), Laravel Pail (log viewer), Laravel Sail (Docker), Laravel Tinker (REPL)

## Common commands

Install dependencies:
```bash
composer install
npm install
```

Run the full local dev stack (server + queue listener + log tailing + Vite, concurrently):
```bash
composer run dev
```

Run pieces individually:
```bash
php artisan serve            # app server
npm run dev                  # Vite dev server (HMR)
npm run build                # production frontend build
php artisan queue:listen --tries=1 --timeout=0
php artisan pail --timeout=0 # live log viewer
```

Run tests:
```bash
composer test                 # clears config cache, then runs php artisan test
php artisan test                              # run full suite
php artisan test --filter=testName            # run a single test by name
php artisan test tests/Feature/ExampleTest.php # run a single test file
```
Tests run against an in-memory SQLite database (see `phpunit.xml`), with `CACHE_STORE=array`, `SESSION_DRIVER=array`, and `QUEUE_CONNECTION=sync`.

Code style (Laravel Pint, PSR-12-based):
```bash
./vendor/bin/pint         # fix style
./vendor/bin/pint --test  # check only, no changes
```

Database:
```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan tinker         # interactive REPL against the app
```

## Architecture

Standard Laravel directory conventions apply — there is no custom architecture layered on top yet:

- `app/Http/Controllers/` — HTTP controllers (currently only the base `Controller` class)
- `app/Models/` — Eloquent models (currently only `User`)
- `app/Providers/AppServiceProvider.php` — application bootstrapping/service registration
- `routes/web.php` — web routes (currently just `/` → `welcome` view)
- `routes/console.php` — Artisan console routes/closures
- `database/migrations/` — schema migrations
- `database/factories/`, `database/seeders/` — model factories and DB seeders
- `resources/views/` — Blade templates
- `resources/js/`, `resources/css/` — frontend entry points bundled by Vite (`vite.config.js`)
- `tests/Feature/` vs `tests/Unit/` — Feature tests boot the full framework (HTTP, DB); Unit tests do not

When adding real functionality, follow standard Laravel idioms (Eloquent models, form requests, resource controllers, Blade or API resources) unless the user directs otherwise, since no project-specific patterns exist yet to override the framework defaults.
