# OA Portfolio Visit Card

Laravel + Vue + Tailwind project combining two things behind one login:

1. **A bilingual public visit card** (EN/NL) — expertise, results, projects and contact direction, presented with initials only (`OA`) and no concrete dates or workplaces.
2. **A private planning workspace** — a personal calendar with task tracking, timers, categories, reports and per-period reflection notes.

Everything editable lives in the database, so running the project for yourself is a matter of changing content in the admin UI, not editing code.

## Features

### Public page
- Bilingual (EN/NL) portfolio page, with the default language and whether visitors may switch both configurable from the admin.
- Ordered expertise, metrics, process steps and projects, each with per-language fields.
- "For developers" connect form at `/hi-developer`.

### Admin — Content studio
Reached at an unlinked, non-obvious URL and gated by login. Three sections in the left nav:

- **My agenda** — the planning workspace:
  - Day / Week / Month calendar views.
  - Create, edit and delete tasks; status (planned, in progress, paused, done, skipped); planned duration kept in step with the start/end times.
  - Built-in timer per task. Only one timer runs at a time — starting a second one stops the first and parks it as *paused*, so time never double-counts.
  - Filter by category and status (multi-select).
  - **Task categories** with one level of nesting, colour and icon — full create/edit/delete.
  - **Report** per week or month: planned vs tracked time per category, task counts by status, and a reflection note saved per period.
- **Insights** — *Connections* (submissions from the public connect form, view-only) and *Industry news* (placeholder, under construction).
- **Edit page** — the public content, one tab per section (Profile, Experience, Expertise, Process, Projects, Language, Reset content).

## Requirements

- PHP 8.3+
- Composer
- Node.js + npm
- A database — SQLite works out of the box (`database/database.sqlite` is already in the repo); MySQL is also supported.

## Quick start (demo)

This gets you a running app with realistic sample data, so you can click around before deciding whether to keep it.

```bash
git clone <this-repo> && cd my_page
composer install
npm install
cp .env.example .env
php artisan key:generate
```

`.env.example` points at MySQL. For the fastest path, switch to SQLite:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

Choose your workspace URL and admin login **before** seeding:

```env
ADMIN_PATH=control-room-ab
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=choose-a-real-password
```

`ADMIN_PATH` is the prefix the entire private workspace lives behind — `/` stays the public visit card. It is **not** hardcoded anywhere in the code: pick your own, e.g. your initials as `control-room-ab`. Nothing on the public page links to it, so beyond the login its protection is that it isn't guessable — an install that keeps the shipped default (`control-room`) has the same URL as every other install. `ADMIN_EMAIL` / `ADMIN_PASSWORD` become the one admin account the seeder creates.

Make sure `APP_ENV=local` (it is in `.env.example`) — the demo tasks only seed in local. Then:

```bash
php artisan migrate --seed
composer run dev
```

Open `http://127.0.0.1:8000/<ADMIN_PATH>` — with the example above, `http://127.0.0.1:8000/control-room-ab` — and sign in.

If you change `ADMIN_PATH` later, clear the cached routes so the new prefix takes effect:

```bash
php artisan route:clear && php artisan config:clear
```

### What the demo seeds

`php artisan migrate --seed` runs four seeders:

| Seeder | What it creates | Runs in production? |
| --- | --- | --- |
| `DefaultPortfolioContent` | The public page content (profile, metrics, expertise, projects, process) | Yes |
| `AdminUserSeeder` | One admin user from `ADMIN_EMAIL` / `ADMIN_PASSWORD`, with the `admin` role | Yes |
| `CategorySeeder` | 9 task categories (Job Search, Learning, Sport, …) plus subcategories under Learning and Language | Yes |
| `DemoWeekSeeder` | A sample week of tasks — mixed statuses, some with logged time | **No — local only** |

Two deliberate safety properties:

- **`DemoWeekSeeder` is gated behind `app()->environment('local')`** in `DatabaseSeeder`, so a `git pull` + `migrate --seed` on a live instance can never bury your real tasks under sample rows.
- **Every seeder is safe to re-run.** `CategorySeeder` uses `updateOrCreate`, and `DemoWeekSeeder` deletes its own previous rows (matched on `source = seeder`) before recreating them — so re-seeding refreshes the demo week rather than stacking duplicates. Tasks you created yourself are never touched.

The demo week is pinned to *the current week*, so it always lands on the calendar you open. To refresh it later:

```bash
php artisan db:seed --class=DemoWeekSeeder
```

### Making it yours

When you're ready to drop the sample data:

```bash
php artisan tinker --execute="App\Models\Task::where('source','seeder')->delete();"
```

Then edit the public content under **Edit page** (initials, role, headline, projects — nothing is hardcoded to the `OA` persona), and manage your own categories under **My agenda → Task categories**. **Edit page → Reset content** restores the seeded public content at any time if you want to start over.

## Run locally

One command starts the server, queue worker, log tailer and Vite together:

```bash
composer run dev
```

Or run the pieces separately:

```bash
php artisan serve   # backend
npm run dev         # frontend (Vite)
```

- Public page: `http://127.0.0.1:8000`
- Developer connect form: `http://127.0.0.1:8000/hi-developer`
- Admin: `http://127.0.0.1:8000/<ADMIN_PATH>` — not linked anywhere on the public page. Sign in at `/login` with your `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
  - `<ADMIN_PATH>/mijn-agenda` — planning workspace
  - `<ADMIN_PATH>/insights` — connections and industry news
  - `<ADMIN_PATH>/edit-content` — public page content

> The workspace path is deliberately obscure rather than `/admin`, and is yours to choose (`ADMIN_PATH`) so no two installs share it — but obscurity is not the protection. Every one of those routes is behind `auth` + `role:admin`. If you deploy this, still use a real password.

## Build

```bash
npm run build
```

## Tests

```bash
composer test           # backend (PHPUnit) — clears config cache, then runs the suite
npm test                # frontend (Vitest, jsdom)
npm run test:watch      # same, in watch mode
vendor/bin/pint         # PHP code style
```

Backend tests run against in-memory SQLite regardless of your `.env` database, so they never touch your local data.

## Content model

`PortfolioProfile` is the root record for the public page, with `metrics`, `expertiseItems`, `projects` and `processSteps` as ordered child collections. Free-text fields are stored as `{en, nl}` JSON and edited in the admin as a single label with EN/NL inputs side by side.

The planner is separate: `Task` (with `Category`, `TimeLog`) plus `Reflection`, all scoped to the signed-in user.

See `CLAUDE.md` for full architecture notes, including the timezone convention and the gotchas worth knowing before changing this code.
