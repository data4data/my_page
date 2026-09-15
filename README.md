# Portfolio & Planning Workspace

A Laravel + Vue app with two halves behind one login.

**The public side** is a bilingual (EN/NL) visit card: expertise, projects, results and a contact form. Initials only, no dates, no employer names.

**The private side** is a planning workspace: calendar, task timers, categories, weekly and monthly reports, and reflection notes.

All the content lives in the database, so you change it in the app rather than in the code. It ships with placeholder content under the initials `AB`. Swap them in Settings and nothing is left pointing at whoever set it up.

## What's in it

**Public page**

- EN/NL, with the starting language and whether visitors may switch both set from the workspace
- Expertise, metrics, process steps and projects, each ordered and each written in both languages
- Connect form at `/hi-developer`

**Workspace**

- **My agenda** — day, week and month views. Tasks with a status and a planned duration. One timer at a time, so minutes never count twice. Filters by category and status. Weekly and monthly reports of planned against tracked time, with a reflection note per period.
- **Insights** — messages from the connect form, and every sign-in attempt grouped by address.
- **Edit page** — the public content, a tab per section.
- **Settings** — language, two-step sign-in, and a version history you can roll back to.

## Security

The workspace sits behind a URL prefix you choose, login page included. There is no `/login` for a scanner to find. That is a nuisance to an attacker, not a defence — every route behind it is gated by `auth` and `role:admin`.

- Two-step sign-in (TOTP), off until you turn it on, with recovery codes and `php artisan two-factor:disable` as the way back in
- Sign-in attempts limited per address *and* per account, and logged for 30 days
- Content Security Policy, `Secure` and `SameSite=Strict` cookies, and security headers on every response
- The seeder refuses a weak admin password outside local development
- The connect form has a rate limit, a honeypot and validation
- Every save of the public page is kept, so a bad edit is one click from undone

## Stack

PHP 8.3+, Laravel 13, Vue 3, Tailwind 4, PrimeVue, Vite, MySQL 8.

MySQL in development, tests and production alike, so strict mode, foreign-key indexing and JSON handling never differ between them.

## Setup

```bash
git clone <this-repo> && cd my_page
composer install && npm install
cp .env.example .env && php artisan key:generate
```

Create two databases, one to work in and one for the tests:

```sql
CREATE DATABASE portfolio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE portfolio_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Set these in `.env` before you seed:

```env
DB_DATABASE=portfolio
ADMIN_PATH=control-room-ab
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=choose-a-real-password
```

`ADMIN_PATH` is where the whole workspace lives. Pick your own; nothing in the code hardcodes it, and the public page never mentions it. The password must be at least 12 characters outside local development, or the seeder refuses to create the account.

Then:

```bash
php artisan migrate --seed
composer run dev
```

Sign in at `http://127.0.0.1:8000/control-room-ab/login`.

Change `ADMIN_PATH` later and you will need `php artisan route:clear && php artisan config:clear`.

## What gets seeded

| Seeder | Creates | In production |
| --- | --- | --- |
| `DefaultPortfolioContent` | Placeholder public page content | Yes |
| `AdminUserSeeder` | The one admin account | Yes |
| `CategorySeeder` | Nine task categories, six subcategories | Yes |
| `DemoWeekSeeder` | A sample week of tasks | No, local only |

The demo week only seeds when `APP_ENV=local`, so pulling and re-seeding a live instance can never bury real tasks under sample rows. Every seeder is safe to run twice: the demo week replaces its own rows instead of stacking duplicates, and leaves tasks you made alone.

The sample week always lands on the current week. Refresh it with `php artisan db:seed --class=DemoWeekSeeder`.

## Making it yours

Drop the sample tasks:

```bash
php artisan tinker --execute="App\Models\Task::where('source','seeder')->delete();"
```

Then edit the public content under **Edit page**, your categories under **My agenda → Task categories**, and the language behaviour under **Settings**. Every save is kept under **Settings → Content versions**, with the seeded defaults as the first entry, so you can always get back to where you started.

## Running it

`composer run dev` starts the server, queue worker, log tailer and Vite together.

| | |
| --- | --- |
| Public page | `/` |
| Connect form | `/hi-developer` |
| Sign in | `<ADMIN_PATH>/login` |
| Agenda | `<ADMIN_PATH>/mijn-agenda` |
| Insights | `<ADMIN_PATH>/insights` |
| Edit page | `<ADMIN_PATH>/edit-content` |
| Settings | `<ADMIN_PATH>/settings` |

## Checks

```bash
vendor/bin/pint      # code style
composer analyse     # PHPStan via Larastan, level 5
composer test        # PHPUnit
npm test             # Vitest
npm run build
```

All five run clean. Backend tests use `portfolio_test` on MySQL, the same engine as production. Only the database name is pinned in `phpunit.xml`, so your own host and credentials apply and your working data is never touched.

## How it fits together

`PortfolioProfile` is the root of the public page, with metrics, expertise, projects and process steps as ordered children. Free text is stored as `{en, nl}` JSON and edited as one field with both languages side by side.

The planner is separate: `Task`, `Category`, `TimeLog` and `Reflection`, all scoped to the signed-in user.

[`CLAUDE.md`](CLAUDE.md) has the architecture notes, including how time is handled and the traps worth knowing before you change anything. [`CONTRIBUTING.md`](CONTRIBUTING.md) covers how work happens here.
