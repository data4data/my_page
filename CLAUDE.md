# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel + Vue + Tailwind app serving a single bilingual (EN/NL) digital "visit card" / portfolio page for one person, initials `OA`. It has no auth: the public page and its `/admin` content editor are both open routes, distinguished only by URL path. All content is DB-backed and translatable (JSON columns with `en`/`nl` keys), editable through the admin UI rather than by touching code.

## Commands

```bash
composer install && npm install         # install deps
cp .env.example .env && php artisan key:generate
php artisan migrate --seed              # seed default OA content

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

Note: `.env.example` / README default to MySQL (`DB_DATABASE=portfolio`), but `phpunit.xml` runs tests against in-memory SQLite, and a `database/database.sqlite` file exists in the repo — either backend works for local dev.

## Architecture

**One Eloquent aggregate, one Vue SPA.** `PortfolioProfile` is the root model (`slug` unique, `is_active` flag selects which profile is live) with four `hasMany` children: `metrics`, `expertiseItems`, `projects`, `processSteps`. Every child row belongs to exactly one profile and carries its own `sort_order` and `is_visible`. Free-text fields on the profile and children (title, headline, summary, description, label, quote, etc.) are cast `array` and store `{en: ..., nl: ...}` — there is no separate translations table.

**Backend is a thin JSON API**, all in `app/Http/Controllers/PortfolioController.php`:
- `GET /` and `GET /admin` → both render the same `app` Blade view/Vue SPA (`resources/js/App.vue` reads `window.location.pathname` to decide if it's in admin mode).
- `GET /portfolio` → public payload, filtered to `is_visible = true` items only.
- `GET /admin/portfolio` → same payload but unfiltered (includes hidden items), used to populate the editor.
- `PUT /admin/portfolio` → full replace-on-save: `update()` wraps everything in a `DB::transaction`, updates the profile's scalar fields via `Arr::only(...)`, then for each of the four child collections calls `replaceOrdered()`, which **deletes all existing rows for that relation and recreates them from the submitted array**, assigning `sort_order` from array position. There is no per-row PATCH/diffing — the admin UI always submits the complete current state of every collection on save.
- `POST /admin/portfolio/seed-defaults` → resets the active profile back to the content in `app/Support/DefaultPortfolioContent.php` (same replace-all pattern), so admins can undo edits.

When adding a new field to profile or a child model: add the DB column via migration, add it to the model's `$fillable`/`$casts`, add it to the relevant key-list inside `PortfolioController` (either the `Arr::only` call for profile fields, or the `$keys` array passed to `replaceOrdered` for a child relation), and add it to `DefaultPortfolioContent::content()` if it should ship with the seeded defaults.

**Frontend is a single large component**, `resources/js/App.vue` (~600 lines, no router, no Pinia/Vuex): it fetches the payload on mount, holds it in one `data` ref, and toggles between a read-only public rendering and an editable admin form for the same data based on the `isAdmin` flag. `lang` (persisted to `localStorage`) selects which side of each `{en, nl}` object is shown/edited; the `ui` object holds the EN/NL strings for interface chrome (not content) with the same shape. Admin editing is organized into tabs (`adminTabs`) mapping to the four child collections plus `profile`. `EditableCard.vue` is a generic wrapper for one row in an editable collection (title, reorder up/down, remove) used across all four admin tabs. `SkillGear.vue` renders one expertise item as an SVG "gear" whose size is driven by that item's `gear_size` column (clamped 82–180) — this is the one place a numeric field maps directly to visual layout, not just text. Icons are resolved through the `iconMap` object in `App.vue` (string key stored in DB → lucide-vue component) — a new `icon` value used in the DB/seed data must be added to `iconMap` or it silently renders nothing.

Styling is Tailwind v4 via the `@tailwindcss/vite` plugin (no `tailwind.config.js` — v4 is CSS-first, configured in `resources/css/app.css`). Fonts are loaded through `laravel-vite-plugin/fonts` (Bunny Fonts, not Google Fonts).
