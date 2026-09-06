# Portfolio Visit Card & Planning Workspace

Laravel + Vue + Tailwind project combining two things behind one login:

1. **A bilingual public visit card** (EN/NL) — expertise, results, projects and contact direction, presented with initials only and no concrete dates or workplaces.
2. **A private planning workspace** — a personal calendar with task tracking, timers, categories, reports and per-period reflection notes.

Everything editable lives in the database, so making the project yours is a matter of changing content in the workspace, not editing code. It ships seeded with placeholder content under placeholder initials (`AB`) — change them under **Settings**, and nothing anywhere is tied to whoever set it up.

## Features

### Public page
- Bilingual (EN/NL) portfolio page, with the default language and whether visitors may switch both configurable from the admin.
- Ordered expertise, metrics, process steps and projects, each with per-language fields.
- "For developers" connect form at `/hi-developer`.

### Workspace — Content studio
Reached at an unlinked, non-obvious URL and gated by login. Four sections in the left nav:

- **My agenda** — the planning workspace:
  - Day / Week / Month calendar views.
  - Create, edit and delete tasks; status (planned, in progress, paused, done, skipped); planned duration kept in step with the start/end times.
  - Built-in timer per task. Only one timer runs at a time — starting a second one stops the first and parks it as *paused*, so time never double-counts.
  - Filter by category and status (multi-select).
  - **Task categories** with one level of nesting, colour and icon — full create/edit/delete.
  - **Report** per week or month: planned vs tracked time per category, task counts by status, and a reflection note saved per period.
- **Insights** — *Connections* (submissions from the public connect form, view-only), *Industry news* (placeholder, under construction), and *Security*: every sign-in attempt against the site, grouped by address over the last 12 hours.
- **Edit page** — the public content, one tab per section (Profile, Experience, Expertise, Process, Projects).
- **Settings** — *Language* (which language the page opens in, and whether visitors may switch), *Two-step sign-in*, and *Content versions* (every save is kept; restoring is itself undoable).

### Security

- The whole workspace sits behind a URL prefix you choose (`ADMIN_PATH`), including the login page — there is no `/login`, so the scanners that probe for one find nothing.
- **Two-step sign-in is opt-in.** Turn it on from Settings when you are ready; a password alone works until you do. Recovery codes are issued at enrolment, and `php artisan two-factor:disable <email>` is the way back in if you lose both your phone and the codes.
- Sign-in attempts are rate-limited per address *and* per account, and every attempt is recorded for 30 days.
- Session cookies are Secure outside local development and `SameSite=Strict`; a Content Security Policy allows script from this origin only.
- The seeder refuses a weak `ADMIN_PASSWORD` outside local development, so the placeholder in `.env.example` cannot reach a live install.

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

`ADMIN_PATH` is the prefix the entire private workspace lives behind, login page included — `/` stays the public visit card. It is **not** hardcoded anywhere in the code: pick your own, for instance your initials as `control-room-ab`. Nothing on the public page links to it and the page never mentions it, so beyond the login its protection is that it isn't guessable — an install that keeps the shipped default (`control-room`) has the same URL as every other install.

`ADMIN_EMAIL` / `ADMIN_PASSWORD` become the one admin account the seeder creates. Outside `APP_ENV=local` the seeder **refuses** a password shorter than 12 characters or one of the well-known defaults, rather than creating a weak account.

Make sure `APP_ENV=local` (it is in `.env.example`) — the demo tasks only seed in local. Then:

```bash
php artisan migrate --seed
composer run dev
```

Open `http://127.0.0.1:8000/<ADMIN_PATH>/login` — with the example above, `http://127.0.0.1:8000/control-room-ab/login` — and sign in.

If you change `ADMIN_PATH` later, clear the cached routes so the new prefix takes effect:

```bash
php artisan route:clear && php artisan config:clear
```

### What the demo seeds

`php artisan migrate --seed` runs four seeders:

| Seeder | What it creates | Runs in production? |
| --- | --- | --- |
| `DefaultPortfolioContent` | The public page content — placeholder profile, metrics, expertise, projects, process | Yes |
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

Then edit the public content under **Edit page** — initials, role, headline, projects. None of it is hardcoded anywhere; the seed just supplies placeholders. Manage your own categories under **My agenda → Task categories**, and set the site's language behaviour under **Settings → Language**. **Settings → Content versions** keeps every save, with the seeded defaults as the first row, so you can go back to any earlier state including the original.

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
- Workspace: `http://127.0.0.1:8000/<ADMIN_PATH>` — not linked anywhere on the public page.
  - `<ADMIN_PATH>/login` — sign in with your `ADMIN_EMAIL` / `ADMIN_PASSWORD`
  - `<ADMIN_PATH>/mijn-agenda` — planning workspace
  - `<ADMIN_PATH>/insights` — connections, industry news, sign-in trail
  - `<ADMIN_PATH>/edit-content` — public page content
  - `<ADMIN_PATH>/settings` — language, two-step sign-in, content versions

> The workspace path is deliberately obscure rather than `/admin`, and is yours to choose (`ADMIN_PATH`) so no two installs share it — but obscurity is not the protection. Every one of those routes is behind `auth` + `role:admin`. If you deploy this, use a real password, serve it over HTTPS, and turn on two-step sign-in.

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

The planner is separate: `Task` (with `Category`, `TimeLog`) plus `Reflection`, all scoped to the signed-in user. `SecurityEvent` records sign-in attempts and expires after 30 days.

See `CLAUDE.md` for full architecture notes, including the timezone convention and the gotchas worth knowing before changing this code.
