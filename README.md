# Portfolio & Planning Workspace

A Laravel + Vue app with two halves behind one login.

**The public side** is a bilingual (EN/NL) visit card: expertise, projects, results and a contact form. Initials only, no dates, no employer names.

**The private side** is a planning workspace: calendar, task timers, categories, weekly and monthly reports, and reflection notes.

All the content lives in the database, so you change it in the app rather than in the code. It ships with placeholder content under the initials `AB`. Swap them under **Edit page → Shared** and nothing is left pointing at whoever set it up.

## What's in it

**Public page**

- EN/NL, with the starting language and whether visitors may switch both set from the workspace
- Each language has its own address — `/` and `/nl` — so either can be linked, shared and found by a search engine
- A contact button that mails the address you set, and hides itself until you set one
- A link preview image, title and summary rendered server-side, so a link pasted into LinkedIn or WhatsApp shows a real card
- Expertise, metrics, process steps and projects, each ordered and each written in both languages
- A connect form for developers, reachable from the public page

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
- `php artisan app:install` asks for the admin password and refuses a weak one, so it never sits in a file
- The workspace is built as its own JavaScript bundle, served only inside the prefix, so the private API's endpoint names are not in the public page's assets
- The connect form has a rate limit, a honeypot and validation
- The last 20 saves of the public page are kept, so a bad edit is one click from undone

## Stack

PHP 8.3+, Laravel 13, Vue 3, Tailwind 4, PrimeVue, Vite, MySQL 8.

MySQL in development, tests and production alike, so strict mode, foreign-key indexing and JSON handling never differ between them.

## Setup

```bash
git clone <this-repo> && cd my_page
composer install && npm install
cp .env.example .env && php artisan key:generate
```

Create a database to work in. The test suite uses its own, `my_page_testing`:

```sql
CREATE DATABASE your_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE my_page_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Pick a workspace path at random, and set these in `.env` before you seed:

```bash
php -r "echo 'workspace-'.bin2hex(random_bytes(4)).PHP_EOL;"
```

```env
DB_DATABASE=your_database
ADMIN_PATH=the-value-you-just-generated
```

`ADMIN_PATH` is where the whole workspace lives, login page included. Nothing in the code hardcodes it and the public page never mentions it, so its only job is to be unguessable. Do not build it from anything a visitor can see, such as the initials on the page.

There is no `ADMIN_EMAIL` or `ADMIN_PASSWORD`. The account is created by a command that asks, so your real password never has to sit in a file:

```bash
php artisan migrate --seed
php artisan app:install
composer run dev
```

`app:install` prompts for the email, the password and the initials. It is safe to run twice — it updates the password rather than making a second account, and leaves content you have already edited alone.

Sign in at `http://127.0.0.1:8000/<ADMIN_PATH>/login`.

Change `ADMIN_PATH` later and you will need `php artisan route:clear && php artisan config:clear`.

## What gets seeded

Seeders create **samples**. Your admin account and your profile are created by `php artisan app:install`, not by a seeder — a seeder runs unattended and would have to read a password out of a file.

| Seeder | Creates | In production |
| --- | --- | --- |
| `DefaultPortfolioContent` | Placeholder public page content | Yes |
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

The public visit card is at `/`, and the other language at `/nl`. Everything else lives under the path you set in `ADMIN_PATH`, starting with its login page, and the left nav takes you between the four sections from there.

## Checks

```bash
vendor/bin/pint      # code style
composer analyse     # PHPStan via Larastan, level 5
composer test        # PHPUnit
npm test             # Vitest
npm run build
```

All five run clean. Backend tests use their own database on MySQL, the same engine as production. Only that name is pinned in `phpunit.xml`, so your own host and credentials apply and your working data is never touched.

## How it fits together

`PortfolioProfile` is the root of the public page, with metrics, expertise, projects and process steps as ordered children. Free text is stored as `{en, nl}` JSON and edited as one field with both languages side by side. The language a visitor gets comes from the URL, so each one is a real page rather than a setting held in their browser.

The planner is separate: `Task`, `Category`, `TimeLog` and `Reflection`, all scoped to the signed-in user.

[`CLAUDE.md`](CLAUDE.md) has the architecture notes, including how time is handled and the traps worth knowing before you change anything. [`CONTRIBUTING.md`](CONTRIBUTING.md) covers how work happens here.
