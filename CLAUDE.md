# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel + Vue + Tailwind app with two halves behind one login:

1. A bilingual (EN/NL) public "visit card" / portfolio page, seeded by default for one person (initials `OA`, changeable via the admin UI — nothing about the running app is hardcoded to that persona).
2. A private **planning workspace** — calendar, task/time tracking, categories, reports and reflection notes.

The public page is fully open; everything else is authenticated and lives at an unlinked, non-obvious path rather than `/admin`. All content is DB-backed; public-page text is translatable (JSON columns with `en`/`nl` keys) and edited through the admin rather than in code.

## Commands

```bash
composer install && npm install         # install deps
cp .env.example .env && php artisan key:generate
php artisan migrate --seed              # content + admin user + categories (+ demo week, local only)

composer run dev                        # serve + queue + logs + vite, all concurrently
php artisan serve                       # backend only
npm run dev                             # vite only
npm run build                           # production frontend assets

composer test                           # clears config cache, then runs php artisan test
php artisan test --filter=test_name     # run a single test
php artisan test tests/Feature/Foo.php  # run one test file

npm test                                # frontend tests (Vitest, jsdom)
npm run test:watch                      # same, in watch mode

vendor/bin/pint                         # PHP code style (Laravel Pint)

php artisan db:seed --class=DemoWeekSeeder   # refresh the demo week onto the current week
```

Note: `.env.example` defaults to MySQL; SQLite is simplest for local dev (`database/database.sqlite` exists in the repo). `phpunit.xml` runs tests against in-memory SQLite regardless. Set `ADMIN_EMAIL`/`ADMIN_PASSWORD` before seeding — `AdminUserSeeder` uses them to create the one admin login.

## Seeding

`DatabaseSeeder` runs `DefaultPortfolioContent::seed()`, `AdminUserSeeder`, `CategorySeeder`, and — **only when `app()->environment('local')`** — `DemoWeekSeeder`. That guard is deliberate: a `git pull` + `migrate --seed` on a live instance must never bury real planning data under sample rows.

Both planner seeders are re-runnable: `CategorySeeder` uses `updateOrCreate`; `DemoWeekSeeder` deletes its own prior rows (`source = seeder`, that user only) before recreating them pinned to the current week. Neither touches manually created tasks.

## Architecture

### Public page aggregate

`PortfolioProfile` is the root model (`slug` unique, `is_active` selects which profile is live) with four `hasMany` children: `metrics`, `expertiseItems`, `projects`, `processSteps`. Each child carries its own `sort_order` and `is_visible`. Free-text fields on profile and children are cast `array` and store `{en: ..., nl: ...}` — there is no translations table. `default_language` and `show_language_toggle` on the profile control what a first-time visitor sees and whether the EN/NL switcher renders at all.

`DeveloperInquiry` (flat, untranslated) holds public connect-form submissions.

### Planner aggregate

Separate from the portfolio, all scoped to the signed-in user:

- `Task` — `title`, `start_datetime`, optional `end_datetime`, `planned_duration_minutes`, `status`, `result_notes`, `source`, `external_ref`, optional `category_id`.
- `Category` — self-referencing `parent_id` for **exactly one** level of nesting (a child never has children). `user_id` null = a shared/global seeded category.
- `TimeLog` — `started_at` / `ended_at` per task. `duration_minutes` is computed in `TimeLog::booted()`'s `saving` hook and **stored**, so report totals are one `SUM()` rather than per-row PHP date-diffing.
- `Reflection` — one note per (user, period_type, period_start, period_end), enforced by a unique index.

Enums in `app/Enums/`: `TaskStatus` (planned, in_progress, paused, done, skipped), `TaskSource` (manual, seeder, ai_chat — the last reserved for future AI-assisted task creation), `ReflectionPeriodType` (week, month). Statuses are plain string columns validated against the enum, not DB enums, for MySQL/SQLite portability. **`TASK_STATUSES` in `resources/js/shared/planning.js` mirrors `TaskStatus` — keep them in step.**

### Auth

`spatie/laravel-permission` provides roles; one admin user holds the `admin` role. Login is plain Laravel session auth (`AuthController`), no Breeze/Fortify. All `/control-room-ao*` routes carry `['auth', 'role:admin']` (the `role` alias is registered in `bootstrap/app.php`, since Laravel 11+ has no `Kernel.php`).

`bootstrap/app.php` also fixes a `shouldRenderJsonWhen()` gotcha — without the `|| $request->expectsJson()` clause, every non-`api/*` validation failure (e.g. a wrong login password) renders as an HTML redirect instead of JSON, breaking every `fetch()`-based form in `resources/js`.

## Backend API

No `/api` prefix — admin JSON endpoints live under `/control-room-ao/...` alongside the SPA shell routes.

**SPA shell** (all render the same Blade view; `resources/js/router.js` picks the page): `/`, `/login`, `/hi-developer`, `/control-room-ao`, `/control-room-ao/mijn-agenda`, `/control-room-ao/insights`, `/control-room-ao/edit-content`.

**Portfolio** (`PortfolioController`):
- `GET /portfolio` → public payload, `is_visible = true` only.
- `GET|PUT /control-room-ao/portfolio` → unfiltered payload / full replace-on-save.
- `POST /control-room-ao/portfolio/seed-defaults` → reset to `DefaultPortfolioContent`.

`update()` wraps everything in a transaction, applies profile scalars via `Arr::only(...)`, then calls `replaceOrdered()` per child collection, which **deletes all rows for that relation and recreates them from the submitted array**, assigning `sort_order` by position. There is no per-row PATCH — the admin always submits complete collection state.

*Adding a profile/child field:* migration → model `$fillable`/`$casts` → the relevant key-list in `PortfolioController` (the `Arr::only` call, or the `$keys` array passed to `replaceOrdered`) → `DefaultPortfolioContent::content()` if it should ship seeded.

**Planner**:
- `GET|POST /control-room-ao/tasks`, `PUT|DELETE /control-room-ao/tasks/{task}` — index requires `start`/`end` date params; the calendar fetches by visible range.
- `POST /control-room-ao/tasks/{task}/timer/start|stop`.
- `GET|POST /control-room-ao/categories`, `PUT|DELETE /control-room-ao/categories/{category}`.
- `GET /control-room-ao/reports?period_type=week|month&period_start=Y-m-d`.
- `GET|PUT /control-room-ao/reflections` (upsert by period).
- `GET /control-room-ao/inquiries` — intentionally **view-only**; no update/destroy exists.

Every planner controller checks ownership (`abort_unless($task->user_id === $request->user()->id, 403)`).

### Two rules worth knowing before editing planner code

**Only one timer runs at a time.** `TimeLogController::start()` calls `pauseOtherRunningTasks()`, which closes any other open log for that user and sets those tasks to `paused`. Without it the same minutes count against several tasks and every report total overstates the day. Those logs are closed **one model at a time** (`$log->update(...)`), not via a mass query update — a builder `update()` bypasses `TimeLog::booted()` and would silently skip computing `duration_minutes`.

**Planned time has a fallback.** `ReportController::plannedMinutes()` prefers `planned_duration_minutes`, falls back to the scheduled `start → end` span (tasks added via the calendar set times but no explicit duration), and returns 0 for open-ended tasks.

## Frontend

`resources/js/router.js` maps paths to lazily-loaded pages: `PublicPage.vue` (also renders `DeveloperConnectModal` on `/hi-developer`), `LoginPage.vue`, and `AdminPage.vue` for all four admin routes — `AdminPage` derives its active section from the route name, so each section is a real bookmarkable/refreshable URL.

**Admin shell** (`resources/js/components/admin/`):
- `AdminLayout.vue` — header + left nav rail + content column. The rail and header share `bg-cream/90` so the chrome reads as one surface; the rail's right border is the single vertical divider, which is why `.admin-panel` drops its own left/bottom border at `lg` and runs flush into it.
- `SectionTabs.vue` — the shared "folder bookmark" tab strip + `.admin-panel` card. **The only place the panel is rendered.** Passing `:tabs="[]"` still yields the card, just with no tab row.

For the panel to stretch to the bottom of the page, its ancestors must form an unbroken flex column. `AdminLayout`'s content column and `CalendarView`'s wrapper both participate — Agenda nests the panel one level deeper than Insights/Edit, so a change there needs checking on all three sections.

**Agenda** (`resources/js/pages/admin/agenda/`): `CalendarView` (mode switching, filters, period navigation, task CRUD wiring) → `DayView` / `WeekView` / `MonthView` / `CategoriesView` / `ReportView`, plus `TaskCard`, `TaskModal`, `CategoryModal`.

**Shared** (`resources/js/shared/`):
- `portfolio.js` — fetch/normalize + `usePortfolioSource`.
- `planning.js` — date helpers, `usePlanning` (all planner fetches/mutations), `useRunningElapsed` (the ticking timer label, shared by `TaskCard` and `TaskModal` so they can't drift).
- `i18n.js` — the `ui` EN/NL dictionary plus `lang`/`copy`/`t`. `lang` is a module-level singleton persisted to `localStorage`.
- `toast.js` / `confirm.js` — module-level singletons rendered once by `ToastStack` / `ConfirmDialog` in `AdminLayout`'s slot. Use `confirm()` (returns a promise) rather than `window.confirm`.
- `icons.js` — `iconMap` string-key → lucide component. A new `icon` value in DB/seed data must be added here or it silently renders nothing.

### Time handling — read this before touching dates

The backend runs `APP_TIMEZONE=UTC` and Eloquent serializes datetimes with a `Z` suffix, but **not all of them are real instants**:

- `start_datetime` / `end_datetime` are the user's **wall-clock** time ("09:00" as typed). Parse with `parseServerDatetime()`, which reads the literal Y-M-D H:i:s digits. Using `new Date(iso)` would apply a UTC→local shift and silently move every displayed time by the browser's offset.
- `TimeLog.started_at` **is** a genuine instant (server `Carbon::now()`). Parse with `parseServerInstant()` (plain `new Date`), which is what elapsed-time maths needs.

Send datetimes back with `formatForApi()`.

Weeks are Monday-based everywhere: Carbon's default `startOfWeek()` server-side, `startOfWeek()` in `planning.js`, and `locale: { firstDayOfWeek: 1 }` in the PrimeVue config so the DatePicker agrees.

## Design system

`resources/js/components/ui/` wraps PrimeVue — installed **unstyled** and pinned to the **MIT-licensed v4 line** (v5 moved to a commercial licence requiring a key) — with this app's look, styled once in the `resources/css/` partials (see below).

Components: `AppButton` (variants: primary/secondary/accent/menu/menu-gold/lang/link/icon/icon-danger), `AppInput`, `AppTextarea`, `AppSelect` (optional `filterable`), `AppMultiSelect`, `AppIconSelect`, `AppCheckbox`, `AppDatePicker`, `AppTranslatedField` (one label over grouped EN/NL inputs — the standard way every bilingual field is edited), plus `ConfirmDialog` and `ToastStack`. `EditableCard.vue` is the reorder/remove wrapper used across the editable content tabs.

**Unstyled mode means PrimeVue ships no CSS at all** — every class comes from our `pt` map, and anything the default theme would have done for us has to be done by hand. Two consequences that have already bitten:

- PrimeVue's `Checkbox` binds `onChange` to its `<input>` only. `AppCheckbox` gets away with an `sr-only` input because its root is a `<label>` that forwards the click; `AppMultiSelect`'s "select all" header checkbox has no such wrapper, so its input is a transparent full-size overlay (`.field-checkbox-input-overlay`) instead. Option rows keep `sr-only` — the `<li>` carries MultiSelect's own handler, and an overlay there would double-toggle.
- State attributes differ per component: `Checkbox` uses `data-p-checked`, `DatePicker` uses `aria-selected` / a space-separated `data-p` token. Check the rendered DOM rather than assuming.

Styling is Tailwind v4 via `@tailwindcss/vite` (no `tailwind.config.js` — v4 is CSS-first) plus `tailwindcss-primeui`. Colours come from the `@theme` token block; prefer `text-ink`/`bg-cream`/`border-sand` over raw hex. Fonts load through `laravel-vite-plugin/fonts` (Bunny Fonts, not Google Fonts).

**`resources/css/app.css` is only an entry point** — it imports partials split by concern, and import order *is* cascade order:

| File | Holds |
|---|---|
| `theme.css` | `@theme` tokens: colours, fonts, breakpoints |
| `base.css` | `@layer base`: the `--z-*` and width scales on `:root`, `html`/`body`, the ambient wash |
| `layout.css` | `.page-grid` track system, `.u-*` span helpers, `.layer-*` stacking utilities |
| `buttons.css` | `.eyebrow` and every button variant |
| `public.css` | the visit card: header, hero, rails, sections, projects, contact |
| `admin.css` | admin chrome and the agenda/planning views |
| `forms.css` | `.admin-grid` and the PrimeVue field styling |
| `overlays.css` | modals shared by public and admin |
| `responsive.css` | every media query, together so breakpoints stay reviewable |

Two rules when editing: all `@import`s must stay above `@plugin`/`@source` (CSS requires `@import` first, and Lightning CSS enforces it), and **never set a raw `z-index`** — add a `--z-*` token in `base.css` and a matching `.layer-*` class. Equal ad-hoc `z-50` values on the toast container and the modal overlay are what once buried error toasts behind the modal scrim.

## Not yet built

Drag-and-drop rescheduling of tasks between days (Week/Month) is the one item from the original planner spec that remains unimplemented. `TaskSource::AiChat` is reserved for future AI-assisted task creation but unused.
