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

Note: `.env.example` defaults to MySQL; SQLite is simplest for local dev (`database/database.sqlite` exists in the repo). `phpunit.xml` runs tests against in-memory SQLite regardless. Set `ADMIN_EMAIL`/`ADMIN_PASSWORD` before seeding — `AdminUserSeeder` uses them to create the one admin login. `ADMIN_PATH` sets the URL prefix the whole private workspace sits behind (see Auth below); it is per-install and never hardcoded.

## Working on this project

**[`CONTRIBUTING.md`](CONTRIBUTING.md) is the single source for how work happens here** — branching, what must travel with a change, the verification gate, the by-hand checks automated tests cannot make, commit style, and the merge order. Read it before committing anything.

Its rules are **not** repeated here or in `.claude/skills/ship/`, on purpose: a rule written in two places is a rule that will drift. If a workflow rule changes, `CONTRIBUTING.md` is the only file to edit.

This file covers the other half — what the project *is*. Architecture, the aggregates, time handling, the design system.

## Seeding

`DatabaseSeeder` runs `DefaultPortfolioContent::seed()`, `AdminUserSeeder`, `CategorySeeder`, and — **only when `app()->environment('local')`** — `DemoWeekSeeder`. That guard is deliberate: a `git pull` + `migrate --seed` on a live instance must never bury real planning data under sample rows.

Both planner seeders are re-runnable: `CategorySeeder` uses `updateOrCreate`; `DemoWeekSeeder` deletes its own prior rows (`source = seeder`, that user only) before recreating them pinned to the current week. Neither touches manually created tasks.

## Architecture

### Public page aggregate

`PortfolioProfile` is the root model (`slug` unique, `is_active` selects which profile is live) with four `hasMany` children: `metrics`, `expertiseItems`, `projects`, `processSteps`. Each child carries its own `sort_order` and `is_visible`. Free-text fields on profile and children are cast `array` and store `{en: ..., nl: ...}` — there is no translations table. `default_language` and `show_language_toggle` on the profile control what a first-time visitor sees and whether the EN/NL switcher renders at all. `headline_highlights` is a flat `[{text, tone}]` list naming which words in the headline take an accent colour; `tone` is `blue` or `gold`, matching the `.headline-*` classes. It is deliberately not a translated field — both languages' spellings share one list, and only the words in the headline currently on screen can match.

`PortfolioRevision` is the page's undo history: one row per save holding the **complete** payload as a JSON snapshot, plus who saved it and when. Snapshots rather than soft deletes, because `update()` *updates* the profile row rather than deleting it — soft-deleted child rows would have left the 15 profile fields with no history at all, and carry nothing that groups them into a version. `user_id` is null only for the baseline snapshot taken before the very first save, which is what makes that first save undoable. Capped at the newest 20 per profile.

`DeveloperInquiry` (flat, untranslated) holds public connect-form submissions.

### Planner aggregate

Separate from the portfolio, all scoped to the signed-in user:

- `Task` — `title`, `start_datetime`, optional `end_datetime`, `planned_duration_minutes`, `status`, `result_notes`, `source`, `external_ref`, optional `category_id`.
- `Category` — self-referencing `parent_id` for **exactly one** level of nesting (a child never has children). `user_id` null = a shared/global seeded category.
- `TimeLog` — `started_at` / `ended_at` per task. `duration_minutes` is computed in `TimeLog::booted()`'s `saving` hook and **stored**, so report totals are one `SUM()` rather than per-row PHP date-diffing.
- `Reflection` — one note per (user, period_type, period_start, period_end), enforced by a unique index.

Enums in `app/Enums/`: `TaskStatus` (planned, in_progress, paused, done, skipped), `TaskSource` (manual, seeder, ai_chat — the last reserved for future AI-assisted task creation), `ReflectionPeriodType` (week, month). Statuses are plain string columns validated against the enum, not DB enums, for MySQL/SQLite portability. **`TASK_STATUSES` in `resources/js/shared/planning.js` mirrors `TaskStatus` — keep them in step.**

### Auth

`spatie/laravel-permission` provides roles; one admin user holds the `admin` role. Login is plain Laravel session auth (`AuthController`), no Breeze/Fortify. All `{admin}*` routes carry `['auth', 'role:admin']` (the `role` alias is registered in `bootstrap/app.php`, since Laravel 11+ has no `Kernel.php`).

**`{admin}` is a placeholder, not a literal path.** The private workspace's URL prefix is per-install — `ADMIN_PATH` in `.env` → `config/admin.php` → `config('admin.path')` — so nothing hardcodes it:

- `routes/web.php` registers one `Route::prefix(config('admin.path'))` group for both the SPA shell routes and the JSON endpoints.
- `AuthController` falls back to `'/'.config('admin.path')` for the post-login redirect.
- `resources/views/app.blade.php` emits `<meta name="admin-path">`; `resources/js/shared/admin-path.js` reads it once and exports `adminBase` / `adminUrl(suffix)`. `router.js` builds its admin route paths from `adminUrl()`, and `planning.js` + `AdminPage.vue` build every fetch URL from it. Components navigate by route **name**, so none of them know the prefix.
- `phpunit.xml` sets `ADMIN_PATH=test-workspace` — deliberately *not* the shipped default — and tests build URLs via `Tests\TestCase::adminUrl()`. Anything that reintroduces a literal prefix fails the suite rather than passing by coincidence.

Changing `ADMIN_PATH` requires `php artisan route:clear` (a cached route table holds the old prefix).

`bootstrap/app.php` appends `SecurityHeaders` to the `web` group, so every route carries `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` and a Content Security Policy. The CSP is `'self'`-only for script: Vite builds all the JS and CSS and `laravel-vite-plugin/fonts` self-hosts the fonts, so nothing legitimate loads from another origin, and the Vite dev server's origin is added back automatically by reading its hot file. That policy is also what stops an owner-supplied link that slipped past `App\Rules\SafeUrl` from executing.

Login is rate-limited by the named `login` limiter defined in `AppServiceProvider::boot()` — per address *and* per account, since keying on one alone leaves either a distributed attempt on a single account or a lockout of the owner.

`bootstrap/app.php` also fixes a `shouldRenderJsonWhen()` gotcha — without the `|| $request->expectsJson()` clause, every non-`api/*` validation failure (e.g. a wrong login password) renders as an HTML redirect instead of JSON, breaking every `fetch()`-based form in `resources/js`.

## Backend API

No `/api` prefix — admin JSON endpoints live under `{admin}/...` alongside the SPA shell routes.

**SPA shell** (all render the same Blade view; `resources/js/router.js` picks the page): `/`, `/login`, `/hi-developer`, `{admin}`, `{admin}/mijn-agenda`, `{admin}/insights`, `{admin}/edit-content`.

**Portfolio** (`PortfolioController`):
- `GET /portfolio` → public payload, `is_visible = true` only.
- `GET|PUT {admin}/portfolio` → unfiltered payload / full replace-on-save.
- `POST {admin}/portfolio/seed-defaults` → reset to `DefaultPortfolioContent`.
- `GET {admin}/portfolio/revisions` → saved-version list (`id`, `created_at`, `author`). Deliberately **excludes** `payload`, which runs to tens of KB per row.
- `POST {admin}/portfolio/revisions/{revision}/restore` → re-applies that snapshot; 404 if it belongs to another profile.

All four writes go through `PortfolioContentService` — `save()`, `seedDefaults()` and `restore()` share one transaction and one write path, so a restored version can never be built differently from a saved one, and all three record history without any of them remembering to. `save()` applies profile scalars via `Arr::only(...)`, then `replaceOrdered()` per child collection, which **deletes all rows for that relation and recreates them from the submitted array**, assigning `sort_order` by position. There is no per-row PATCH — the admin always submits complete collection state. `restore()` is literally `save($revision->payload)`, which is why it has no logic of its own.

*Adding a profile/child field:* migration → model `$fillable`/`$casts` → the matching key-list constant in `PortfolioContentService` (`PROFILE_KEYS` or `CHILD_KEYS`) → a rule in `UpdatePortfolioRequest` → `DefaultPortfolioContent::content()` if it should ship seeded.

`UpdatePortfolioRequest` validates **types and lengths, not presence**: the editor lets fields be cleared, so `required` on free text would reject payloads the UI legitimately produces. Presence is demanded only where the column is NOT NULL (`metrics.*.value`), because Laravel's `ConvertEmptyStringsToNull` middleware turns a cleared field into `null` and the insert would otherwise 500 instead of returning a readable 422. Two tests in `PortfolioContentTest` guard this by fetching the admin payload and PUTting it straight back — the same round trip pressing Save performs.

**Planner**:
- `GET|POST {admin}/tasks`, `PUT|DELETE {admin}/tasks/{task}` — index requires `start`/`end` date params and rejects a span wider than a year; the calendar fetches by visible range.
- `POST {admin}/tasks/{task}/timer/start|stop`.
- `GET|POST {admin}/categories`, `PUT|DELETE {admin}/categories/{category}`.
- `GET {admin}/reports?period_type=week|month&period_start=Y-m-d` — totals come from `withSum` on `time_logs.duration_minutes`, so the stored column is summed in SQL rather than in PHP. `by_category` groups by `category_id`, not by name, and leaves the uncategorized bucket's label to the frontend.
- `GET|PUT {admin}/reflections` (upsert by period).
- `GET {admin}/inquiries?page=N` — intentionally **view-only**; no update/destroy exists. `simplePaginate`d into `{inquiries, page, has_more}`, since the public form that fills it is throttled per minute rather than in total.

Ownership lives in `app/Policies/` (`TaskPolicy`, `CategoryPolicy`), found by naming convention — nothing registers them. `CategoryPolicy` keeps the rule that a **global** category (`user_id` null) is editable by anyone. The base `Controller` carries `AuthorizesRequests`, which Laravel 11+ leaves off, so `$this->authorize()` works. Write endpoints with a request body check ownership in their Form Request's `authorize()`; `destroy` and the timer endpoints (no body, so no Form Request) call `$this->authorize()` directly.

### Where the logic lives

Controllers validate, authorize, delegate, and return JSON. Rules that outlive a request live elsewhere:

- **`app/Services/`** — `PortfolioContentService` (the single write path for the public page, above) and `TimerService`. Plain concrete classes injected via `__construct()`; the container resolves them by reflection, so **`AppServiceProvider` registers no bindings** — no interfaces, no singletons. Add one only when a second implementation actually exists. Its `boot()` holds the `login` rate limiter and nothing else.
- **`app/Http/Requests/`** — `Store`/`Update` pairs for Task and Category, plus `UpdatePortfolioRequest`. Pairs, not single classes: the partial-update path swaps `required` for `sometimes`, so one rule set genuinely cannot serve both.
- **`app/Policies/`** — ownership, as above.
- **The models themselves** — `Task::plannedMinutes()`, `Reflection::scopeForPeriod()` (the read and the upsert must find a row identically, and the `whereDate()` reasoning belongs in one place).

`AuthController`, `DeveloperInquiryController`, `ReflectionController` and `ReportController` deliberately keep inline `$request->validate()`. Their rules are short and single-use; converting them would be ceremony.

### Two rules worth knowing before editing planner code

**Only one timer runs at a time.** `TimerService::start()` calls `pauseOtherRunningTasks()`, which closes any other open log for that user and sets those tasks to `paused`. Without it the same minutes count against several tasks and every report total overstates the day. Those logs are closed **one model at a time** (`$log->update(...)`), not via a mass query update — a builder `update()` bypasses `TimeLog::booted()` and would silently skip computing `duration_minutes`. `TimerServiceTest` calls the service directly, with no HTTP involved.

`Carbon::now()` is called directly here on purpose — `Carbon::setTestNow()` already makes it controllable, so a Clock abstraction would solve a problem the framework has solved.

**Planned time has a fallback.** `Task::plannedMinutes()` prefers `planned_duration_minutes`, falls back to the scheduled `start → end` span (tasks added via the calendar set times but no explicit duration), and returns 0 for open-ended tasks.

## Frontend

`resources/js/pages/` is split by audience: `public/` holds what an anonymous visitor sees, `admin/` everything behind the login (including `LoginPage.vue`, which is the door to it).

`resources/js/router.js` maps paths to lazily-loaded pages: `public/PublicPage.vue` (also renders `DeveloperConnectModal` on `/hi-developer`), `admin/LoginPage.vue`, and `admin/AdminPage.vue` for all four admin routes — `AdminPage` derives its active section from the route name, so each section is a real bookmarkable/refreshable URL.

**Admin shell** (`resources/js/components/admin/`):
- `AdminLayout.vue` — header + left nav rail + content column. The rail and header share `bg-cream/90` so the chrome reads as one surface; the rail's right border is the single vertical divider, which is why `.admin-panel` drops its own left/bottom border at `lg` and runs flush into it.
- `SectionTabs.vue` — the shared "folder bookmark" tab strip + `.admin-panel` card. **The only place the panel is rendered.** Passing `:tabs="[]"` still yields the card, just with no tab row.

The **Content versions** tab (`pages/admin/ContentVersionsTab.vue`) is a single list of states you can go back to. The seeded defaults are its **first row**, not a separate section — they are just another version to restore. `AdminPage.vue` owns the state (`revisions`, `revisionsLoading`, `restoring`, `restoringId`) and refreshes the list after every save, reset and restore, so a new version appears without a reload. Both restore paths go through the shared `confirm()` and both lock every button in the list while one is in flight: they overwrite the live public page, and the defaults row is now one click away from the saved versions rather than guarded by its own warning block.

Like its sibling tabs it opens straight into an `.admin-note` with no `<h3>` — the tab strip already names the section.

For the panel to stretch to the bottom of the page, its ancestors must form an unbroken flex column. `AdminLayout`'s content column and `CalendarView`'s wrapper both participate — Agenda nests the panel one level deeper than Insights/Edit, so a change there needs checking on all three sections.

**Agenda** (`resources/js/pages/admin/agenda/`): `CalendarView` (mode switching, filters, period navigation, task CRUD wiring) → `DayView` / `WeekView` / `MonthView` / `CategoriesView` / `ReportView`, plus `TaskCard`, `TaskModal`, `CategoryModal`.

**Shared** (`resources/js/shared/`):
- `api.js` — `apiFetch(url, {method, body, message})` and `csrfToken()`. **Every** call to a JSON endpoint goes through it; no component calls `fetch()` directly. It sets `Accept: application/json` and the CSRF header, throws an `ApiError` carrying `status` and the parsed `body` on any non-2xx, and redirects to `/login` on a 401. The `Accept` header is the load-bearing part: without it an expired session takes the auth middleware's HTML redirect instead of a JSON 401, `response.json()` throws on the HTML, and whichever `loading` ref was in flight never clears. Loaders pair it with `try/finally` for the same reason.
- `admin-path.js` — `adminBase` / `adminUrl(suffix)`, read once from the `admin-path` meta tag. Every admin URL in the frontend goes through it; never write the prefix out by hand. The tag is emitted only for a signed-in admin (`PortfolioController::app()`), since every page renders the same shell and the prefix is meant to be unguessable.
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

Components: `AppButton` (variants: primary/secondary/accent/menu/menu-gold/lang/link/icon/icon-danger), `AppInput`, `AppTextarea`, `AppSelect` (optional `filterable`), `AppMultiSelect`, `AppIconSelect`, `AppCheckbox`, `AppDatePicker`, `AppTranslatedField` (one label over grouped EN/NL inputs — the standard way every bilingual field is edited), `AppModal`, plus `ConfirmDialog` and `ToastStack`.

**`AppModal` is the shell every modal uses** — overlay, card, close button, `role="dialog"`, Escape, a Tab trap, and returning focus to whatever opened it. `TaskModal`, `CategoryModal` and `DeveloperConnectModal` each had the first three copied by hand and none of the rest. It skips an Escape that another component already called `preventDefault()` on, and leaves focus alone when it sits outside the card, because `AppSelect` and `AppDatePicker` overlays are appended to `<body>`. `ConfirmDialog` stays separate: it is an `alertdialog` raised from inside these, so it carries its own higher `--z-confirm` layer. `EditableCard.vue` is the reorder/remove wrapper used across the editable content tabs.

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
