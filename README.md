# OA Portfolio Visit Card

Laravel + Vue + Tailwind project for a bilingual digital visit card. The public page uses initials only (`OA`) and presents expertise, results, projects, and contact direction without concrete dates or workplaces.

## Features

- Public portfolio page in English and Dutch.
- Password-protected admin editor for the bilingual content, split into one tab per section (Profile, Experience, Expertise, Process, Projects, Inquiries), built on a small reusable component layer (buttons, inputs, selects) styled once and used everywhere.
- Ordered expertise, metrics, process steps, and projects, each with per-language fields edited side by side.
- Public "For developers" connect form — submissions show up read-only in an Inquiries admin tab.
- Seeded default OA content, restorable from the admin editor at any time.
- Ready to extend later for company or multi-person profiles.

## Requirements

- PHP 8.3+
- Composer
- Node.js + npm
- A database — SQLite works out of the box (a `database/database.sqlite` file is already in the repo); MySQL is also supported if you'd rather use that.

## Setup

Install PHP and frontend dependencies:

```bash
composer install
npm install
```

Create the environment file and generate the app key:

```bash
cp .env.example .env
php artisan key:generate
```

By default `.env.example` is set up for MySQL. For SQLite (simplest for local dev), set instead:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

Set the admin login credentials before seeding (used to create the one admin account):

```env
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=choose-a-real-password
```

Run migrations and seed the default OA content plus the admin user:

```bash
php artisan migrate --seed
```

## Run locally

One command starts the server, queue worker, log tailer, and Vite together:

```bash
composer run dev
```

Or run the pieces separately:

```bash
php artisan serve   # backend
npm run dev          # frontend (Vite)
```

- Public page: `http://127.0.0.1:8000`
- Admin editor: `http://127.0.0.1:8000/control-room-ao` — not linked anywhere on the public page; sign in with the `ADMIN_EMAIL` / `ADMIN_PASSWORD` above at `/login`.
- Developer connect form: `http://127.0.0.1:8000/hi-developer`

## Build

Create production frontend assets:

```bash
npm run build
```

## Tests

```bash
composer test
```

## Content model

`PortfolioProfile` is the root record, with `metrics`, `expertiseItems`, `projects`, and `processSteps` as ordered child collections. Free-text fields are stored as `{en, nl}` JSON and edited in the admin UI as a single label with EN/NL inputs side by side — nothing here requires touching code. See `CLAUDE.md` for the full architecture notes.
