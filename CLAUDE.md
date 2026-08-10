# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel + Vue + Tailwind app serving a bilingual (EN/NL) digital "visit card" / portfolio page, seeded by default for one person (initials `OA`, easily changed via the admin UI — nothing about the running app is hardcoded to that persona). The public page is fully open; the admin content editor is authenticated and lives at an unlinked, non-obvious path rather than `/admin` — reachable only by whoever already knows the URL, then gated by login. All content is DB-backed and translatable (JSON columns with `en`/`nl` keys), editable through the admin UI rather than by touching code.

## Commands

```bash
composer install && npm install         # install deps
cp .env.example .env && php artisan key:generate
php artisan migrate --seed              # seed default content + the admin user/role

composer run dev                        # serve + queue + logs + vite, all concurrently
php artisan serve                       # backend only
npm run dev                             # vite only
npm run build                           # production frontend assets

composer test                           # clears config cache, then runs php artisan test
php artisan test                        # run full test suite
php artisan test --filter=test_name     # run a single test
php artisan test tests/Feature/Foo.php  # run one test file

vendor/bin/pint                         # PHP code style (Laravel Pint)
```

Note: `.env.example` / README default to SQLite (a `database/database.sqlite` file exists in the repo — simplest for local dev); MySQL is also supported, `phpunit.xml` runs tests against in-memory SQLite regardless. Set `ADMIN_EMAIL`/`ADMIN_PASSWORD` in `.env` before seeding — that's what `AdminUserSeeder` uses to create the one admin login.

## Architecture

**One Eloquent aggregate, one Vue SPA, auth-gated admin.** `PortfolioProfile` is the root model (`slug` unique, `is_active` flag selects which profile is live) with four `hasMany` children: `metrics`, `expertiseItems`, `projects`, `processSteps`. Every child row belongs to exactly one profile and carries its own `sort_order` and `is_visible`. Free-text fields on the profile and children (title, headline, summary, description, label, quote, etc.) are cast `array` and store `{en: ..., nl: ...}` — there is no separate translations table. A separate `DeveloperInquiry` model (flat, no translation) holds submissions from the public connect form.

**Auth**: `spatie/laravel-permission` provides roles; a single admin user (created by `database/seeders/AdminUserSeeder.php` from `ADMIN_EMAIL`/`ADMIN_PASSWORD`) holds the `admin` role. Login is plain Laravel session auth (`app/Http/Controllers/AuthController.php`), no Breeze/Fortify. All `/control-room-ao*` routes carry `['auth', 'role:admin']` middleware (the `role` alias is registered in `bootstrap/app.php`, since Laravel 11+ has no `Kernel.php` to add it to). `bootstrap/app.php` also fixes a `shouldRenderJsonWhen()` gotcha — without the `|| $request->expectsJson()` clause it added, every non-`api/*` validation failure (e.g. wrong login password) would render as an HTML redirect instead of JSON, breaking every `fetch()`-based form in `resources/js`.

**Backend is a thin JSON API**, mostly in `app/Http/Controllers/PortfolioController.php`:
- `GET /`, `GET /login`, `GET /hi-developer`, `GET /control-room-ao` → all render the same `app` Blade view/Vue SPA; `resources/js/router.js` (vue-router) decides which page component mounts, based on path. The Blade view itself renders a real, DB-driven `<title>` (initials + role) rather than a hardcoded string.
- `GET /portfolio` → public payload, filtered to `is_visible = true` items only.
- `GET /control-room-ao/portfolio` → same payload but unfiltered (includes hidden items), used to populate the editor. Requires `auth` + `role:admin`.
- `PUT /control-room-ao/portfolio` → full replace-on-save: `update()` wraps everything in a `DB::transaction`, updates the profile's scalar fields via `Arr::only(...)`, then for each of the four child collections calls `replaceOrdered()`, which **deletes all existing rows for that relation and recreates them from the submitted array**, assigning `sort_order` from array position. There is no per-row PATCH/diffing — the admin UI always submits the complete current state of every collection on save.
- `POST /control-room-ao/portfolio/seed-defaults` → resets the active profile back to the content in `app/Support/DefaultPortfolioContent.php` (same replace-all pattern), so admins can undo edits.
- `POST /hi-developer` (`DeveloperInquiryController::store`, public, throttled) → validates and stores a connect-form submission. `GET /control-room-ao/inquiries` (`::index`, admin-only) lists them — intentionally **view-only**, no update/destroy endpoints exist.

When adding a new field to profile or a child model: add the DB column via migration, add it to the model's `$fillable`/`$casts`, add it to the relevant key-list inside `PortfolioController` (either the `Arr::only` call for profile fields, or the `$keys` array passed to `replaceOrdered` for a child relation), and add it to `DefaultPortfolioContent::content()` if it should ship with the seeded defaults.

**Frontend is a small vue-router SPA**, not one monolithic component. `resources/js/router.js` maps paths to lazily-loaded pages:
- `resources/js/pages/PublicPage.vue` — the read-only visit card. Also renders the "For developers" connect popup (`DeveloperConnectModal.vue`) when the route is `/hi-developer`, reusing the same fetched data underneath.
- `resources/js/pages/AdminPage.vue` — a thin shell (header, tab switcher, save/restore) that renders one component per tab from `resources/js/pages/admin/` (`ProfileTab`, `MetricsTab`, `ExpertiseTab`, `ProcessTab`, `ProjectsTab`, `InquiriesTab`).
- `resources/js/pages/LoginPage.vue` — fetches `/portfolio` itself just to show the real site initials in its logo, so it isn't tied to one persona either.

Shared logic lives in `resources/js/shared/`: `portfolio.js` (fetch + normalize + the `usePortfolioSource` composable), `i18n.js` (the `ui` EN/NL chrome-string dictionary, plus `lang`/`copy`/`t` — `lang` is a module-level singleton persisted to `localStorage`, shared by whichever page is mounted), `icons.js` (the `iconMap` string-key → lucide-vue component map — a new `icon` value used in DB/seed data must be added here or it silently renders nothing).

**Design system**: `resources/js/components/ui/` wraps PrimeVue (installed **unstyled**, pinned to the MIT-licensed v4 line — v5 switched to a commercial license requiring a key) with this app's own look, styled once in `resources/css/app.css`: `AppButton` (variant prop: primary/secondary/menu/menu-gold/lang/link/icon/icon-danger), `AppInput`, `AppTextarea`, `AppSelect` (optional `filterable`), `AppIconSelect` (a searchable `AppSelect` pre-populated from `iconMap`, with a live icon preview), `AppCheckbox`, `AppTranslatedField` (one label over grouped EN/NL `InputGroup` inputs — the standard way every bilingual field is edited, instead of two separately-labeled fields). `EditableCard.vue` is the generic reorder/remove wrapper used across the four editable admin tabs.

Styling is Tailwind v4 via the `@tailwindcss/vite` plugin (no `tailwind.config.js` — v4 is CSS-first, configured in `resources/css/app.css`) plus `tailwindcss-primeui` for the PrimeVue integration. Fonts are loaded through `laravel-vite-plugin/fonts` (Bunny Fonts, not Google Fonts).
