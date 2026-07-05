# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A laundry-room reservation system for the "Stadtlohnweg" student dorm in Münster. Residents reserve
one-hour slots (07:00–22:00) on two washers + a dryer, in two cellars/blocks (A and C). Replaces a
manual Google Sheet. Bookings are public (room numbers are visible) and work with **and without** an
account. German is the primary UI language; English is fully supported.

Stack: **Laravel 13 · Livewire 4 · Flux UI · Fortify (auth) · Socialite (Google OAuth) · Tailwind v4 · SQLite**.

## Commands

```bash
composer setup          # first-time: install, .env, key, migrate, npm install + build
composer dev            # run everything (php serve + queue + pail logs + vite) via concurrently
npm run dev             # vite only (pair with `php artisan serve`)
npm run build           # build assets (required before viewing the app without vite dev server)

composer test           # full gate: config:clear + pint --test + phpstan + artisan test
php artisan test                                   # test suite only
php artisan test --filter=test_a_slot_cannot_be_double_booked   # single test by method
php artisan test tests/Feature/ReservationTest.php # single file

composer lint           # pint (fix)         · composer lint:check (dry-run)
composer types:check    # phpstan
```

Tests run under **`APP_LOCALE=en`** (forced in `phpunit.xml`), so feature-test assertions expect English
strings. `docker build .` produces the Cloud Run image (see Deployment).

## Architecture

### Livewire 4 single-file page components (the core UI pattern)
Routes bind to page components: `Route::livewire('/', 'pages::calendar')` resolves `pages::calendar` to
`resources/views/pages/calendar.blade.php`. Each such file is **PHP class + Blade template in one file**:
a `<?php new #[Layout('layouts::site')] #[Title('some_key')] class extends Component { ... }; ?>` header
followed by the markup. Most screens live in `resources/views/pages/**`. `resources/views/pages/calendar.blade.php`
is the heart of the app.

Layouts resolve via an anonymous-component namespace: `layouts::app` → `resources/views/layouts/app.blade.php`.
Livewire's default page layout is `layouts::app` (Fortify/settings screens); public pages opt into
`#[Layout('layouts::site')]`.

### Localization — never inline UI strings
All user-facing text goes through `__('identifier_key')` with **abstract identifier keys**, not English
sentences. Two complete dictionaries: `lang/en.json` and `lang/de.json`. When adding or changing UI text,
**add the key to both files**. Page titles use a key in `#[Title('key')]`; `resources/views/partials/head.blade.php`
runs it through `__()`.

`app/Http/Middleware/SetLocale.php` picks the locale (authenticated `users.locale` → session → config
default) and also sets the Carbon locale for localized dates. The language switcher persists the choice to
session, the `users.locale` column, and browser `localStorage`.

### Reservation domain
`app/Models/Reservation.php` centralizes the domain constants: `BLOCKS` (A/C), `APPLIANCES`
(left/right/dryer — values are **translation keys**), hours (7–21), `MAX_ADVANCE_MONTHS`, `RETENTION_DAYS`,
`ROOM_REGEX` (room-number format `aBBBcc`, e.g. `A115` / `A115.2`). A **unique index
`(block, appliance, reserved_date, hour)`** enforces no-double-booking at the DB level — this guarantee is
relied upon; do not drop it.

### Guest vs. account bookings (the trickiest part)
- **Account**: booking uses the profile's room number, sets `user_id`, no PIN.
- **Guest**: booking requires a room number + **4-digit PIN** (stored hashed; model casts `pin => 'hashed'`).
  The booking `id`+PIN are saved in browser `localStorage` via the Alpine store in `resources/js/app.js`
  (key `washing.mine`). That lets the *same browser* show the booking green and cancel it without re-entering
  the PIN; cancelling from another device needs the PIN or an account. `pages/my-reservations.blade.php`
  shows account bookings server-side, or guest bookings hydrated from localStorage.

### Calendar view
One long horizontally-scrollable table (no week pagination): 2 past days + the bookable range (today …
+1 month) + 2 future "hint" days. Past/future zones render a single centered notice spanning their columns.
It auto-scrolls to today on load (so mobile opens at the current day, not the past). New guest bookings are
rendered green immediately via a server-side `lastBookedId` (avoids a red→green flash while the Alpine store
hydrates). Past bookings cannot be cancelled (guarded in `cancel()` + hidden in the UI).

### Auth
Fortify handles login/registration/2FA/passkeys. `OAuthController` adds Google sign-in (Socialite); OAuth
users have a nullable password + `provider`/`provider_id`, and are sent to `profile.complete` if they lack a
room number. Custom middleware `SetLocale`, `EnsureEmailIsVerified`, `EnsureProfileComplete` are appended to
the web group in `bootstrap/app.php`. Password policy lives in `AppServiceProvider`: min 8 chars, characters
from ≥3 of {upper, lower, digit, symbol}.

### Data-protection scheduled jobs
`routes/console.php` schedules `reservations:purge` (daily, deletes reservations older than
`RETENTION_DAYS`) and `accounts:purge` (weekly, deletes long-inactive accounts). On Cloud Run these are
triggered by Cloud Scheduler hitting the token-guarded `GET /tasks/run-scheduler` endpoint
(`RunScheduledTasksController`), which runs `schedule:run`.

## Deployment (Google Cloud Run)
Multi-stage `Dockerfile`: `composer:2` (vendor, `--no-dev`) → `node:24-alpine` (Vite assets, needs `vendor/`
copied in because Tailwind imports Flux's CSS from `vendor/…/flux.css`) → `dunglas/frankenphp:…-php8.5-alpine`
runtime. `docker/entrypoint.sh` runs `config:cache` + `migrate --force` then serves via FrankenPHP on
`$PORT`. `bootstrap/app.php` trusts all proxies and `AppServiceProvider` forces the `https` scheme in
production (required for correct OAuth redirect URLs behind Cloud Run's TLS terminator).

## Gotchas
- **Do not `php artisan route:cache`** — `routes/web.php`/`settings.php` contain closures (`sprache`, passkey
  endpoints) that break route caching. `config:cache` is safe (no `env()` calls exist outside config files).
- After changing translated text, keep `lang/en.json` and `lang/de.json` in sync (same keys).
