# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel + Vue + Tailwind app with two halves behind one login:

1. A bilingual (EN/NL) public "visit card" / portfolio page. The seed ships placeholder content under placeholder initials (`AB`), all of it changeable from the workspace — nothing about the running app is hardcoded to whoever is using it.
2. A private **planning workspace** — calendar, task/time tracking, categories, reports and reflection notes.

The public page is fully open; everything else is authenticated and lives at an unlinked, non-obvious path rather than `/admin`. All content is DB-backed; public-page text is translatable (JSON columns with `en`/`nl` keys) and edited through the admin rather than in code.

## Commands

```bash
composer install && npm install         # install deps
cp .env.example .env && php artisan key:generate
php artisan migrate --seed              # placeholder content + categories (+ demo login and week, local only)
php artisan app:install                 # a real admin account and the profile — asks for them

composer run dev                        # serve + queue + schedule + logs + vite, all concurrently
php artisan serve                       # backend only
npm run dev                             # vite only
npm run build                           # production frontend assets

composer analyse                        # PHPStan via Larastan, level 5
composer test                           # clears config cache, then runs php artisan test
php artisan test --filter=test_name     # run a single test
php artisan test tests/Feature/Foo.php  # run one test file

npm test                                # frontend tests (Vitest, jsdom)
npm run test:watch                      # same, in watch mode

vendor/bin/pint                         # PHP code style (Laravel Pint)

php artisan db:seed --class=DemoWeekSeeder   # refresh the demo week onto the current week
php artisan db:seed --class=DemoAdminSeeder  # the local-only demo login, if it is missing
```

Note: **MySQL everywhere** — development, tests and production. `phpunit.xml` pins only the database *name* (`my_page_testing`, deliberately not derived from whatever the working database is called), so host and credentials come from your own `.env` and the suite never touches your development data. Running tests on a different engine from production hides exactly the differences that matter: strict mode, foreign-key indexing, date functions and JSON handling all differ. Run `php artisan app:install` to create the one admin login. `ADMIN_PATH` sets the URL prefix the whole private workspace sits behind (see Auth below); it is per-install and never hardcoded.

## Working on this project

**[`CONTRIBUTING.md`](CONTRIBUTING.md) is the single source for how work happens here** — branching, what must travel with a change, the verification gate, the by-hand checks automated tests cannot make, commit style, and the merge order. Read it before committing anything.

Its rules are **not** repeated here or in `.claude/skills/ship/`, on purpose: a rule written in two places is a rule that will drift. If a workflow rule changes, `CONTRIBUTING.md` is the only file to edit.

This file covers the other half — what the project *is*. Architecture, the aggregates, time handling, the design system.

## Decisions taken, and not reopened

Recorded here rather than in `TODO.md`, which holds only what is left to do.
Each of these was measured or argued out; reopening one needs a new reason,
not a fresh opinion.

**One Laravel app on one server, not two.** Splitting the public page and the
workspace onto separate machines buys a push pipeline, a second database, a
payload contract between two versions of the same code and a two-stage deploy.
What it was *for* — the public site serving the workspace's JavaScript — is
closed by the two Vite bundles instead. Security here comes from the login:
session auth with an `HttpOnly` cookie and CSRF is the strongest option for a
browser app, better than a token in JavaScript's reach. Reopen if there is real
traffic, a compliance requirement, or other people's data on the public side.

**No versioned API, no token auth, no OpenAPI document.** All three existed to
serve a third-party consumer. There is no mobile app. The workspace's JSON
endpoints stay in `web.php`, because they are session-authenticated and
same-origin — moving them to `routes/api.php` would 401 everything until
Sanctum put the session back. `routes/api.php` earns its place the day a
stateless caller does.

**Not Inertia.** It replaces the JSON API with controllers that return Vue
pages — no `apiFetch`, no loading flags, no hand-written error handling — and
it is what to choose when *starting* an app of this shape. This workspace
already works and has tests behind it, so moving it now is a rewrite that
changes nothing a user sees. The signal to reconsider is writing fetch, loading
and error code by hand for every new screen and getting tired of it.

**The public page stays drawn by JavaScript.** A request for `/` returns a full
`<head>` — around 2.4 KB of title, description, Open Graph, Twitter card and
schema.org — and an empty body. That is enough for the link previews a
portfolio is actually reached through, and Google indexes JavaScript pages
anyway. Rendering the six sections in Blade would buy only being *found* by a
search engine rather than *sent* to; it would cost two files that both know how
to draw a project card, and it would save 13 KB of a 252 KB bundle, because the
connect form keeps Vue and PrimeVue either way. If search ever matters, the
cheap version is the hero — role, headline, summary — inside `#app` in Blade:
Vue wipes `#app` on mount, so a browser never sees it twice and a crawler reads
the part that matters.

**Mail providers need no code.** `config/mail.php` plus `MAIL_MAILER` already
switches SMTP, SES, Postmark and Resend. Writing an interface over Laravel's
would add a layer and buy nothing.

**No `Task` subclasses.** Eloquent has no single-table inheritance, so
`ManualTask`/`SyncedTask` means overriding `newFromBuilder()` or adding
`tighten/parental` — and then `$timeLog->task` silently returns the base class
wherever that is missed. The differences between a hand-made task and a synced
one are guard rules (the remote owns the schedule, deleting unlinks rather than
deletes, it cannot be created by hand), and `TaskPolicy` and
`UpdateTaskRequest` are where rules live. They **would** belong on the
`TaskSource` enum — one `match` per rule in one file — and none of them exist
yet, because nothing syncs: the enum holds its three cases and no methods. See
`TODO.md` item 3, which is where that design is written down. **Revisit on columns, not behaviour:** if synced tasks need a
recurrence rule, attendees or a meeting link, that is a one-to-one
`task_calendar_details` table, not a subclass and not nullable columns empty
for most rows.

**What stays denormalised, and why.**

- **The `{en, nl}` JSON columns.** A translations table turns every read into a
  join and a pivot, for two languages on a page always read whole. Revisit if a
  translated value must be sorted or filtered in SQL.
- **`portfolio_projects.tags`.** Free text, never shared between projects,
  never queried. Revisit when something wants "every project tagged Laravel".
- **`headline_highlights`.** A short list tied to one string.
- **`portfolio_revisions.payload`.** The snapshot is what makes `restore()` the
  same code path as `save()`.
- **Four content child tables staying four tables.** One table with a `type`
  and a JSON blob would be *less* relational, not more.
- **`tasks.status` and `tasks.source` as strings.** A DB enum needs an
  `ALTER TABLE` to gain a case, and the enum classes plus validation already
  constrain them.

## Migrations

**One `create_` per table, and no alters.** The history was squashed on 2026-09-19, while nothing was deployed: every column, index and default lives in the migration that creates its table. Anyone with an older database runs `migrate:fresh --seed`; there is no upgrade path and there does not need to be one.

`create_permission_tables` is published by `spatie/laravel-permission` and `create_cache_table`/`create_jobs_table` are Laravel's own — all three stay as those packages wrote them.

Not `php artisan schema:dump`: it squashes to a MySQL dump, which pins the repo to one server version and hides the schema from review. One readable `create_` per table is the point.

## Setting up an install

**Three kinds of row, three owners.**

- **Roles are code.** `admin` is a name `role:admin` refers to — a constant that happens to live in a table. Created idempotently, never asked for.
- **The admin account and the profile are this install's identity.** `php artisan app:install` creates them, prompting for the email, the password and the initials. It replaced `AdminUserSeeder`, which read credentials from `.env` and then spent sixty lines refusing the weak ones it might be handed; a command can simply ask, so a real password never sits in a file and a placeholder one can never reach a live install. There is no `ADMIN_EMAIL` or `ADMIN_PASSWORD` any more. Safe to run twice: it updates rather than duplicating, and leaves edited content alone.
- **Placeholder content, the demo login and the demo week are samples.** `DatabaseSeeder` runs `DefaultPortfolioContent::seed()` and `CategorySeeder`, plus `DemoAdminSeeder` and `DemoWeekSeeder` **only when `app()->environment('local')`** — a `git pull` + `migrate --seed` on a live instance must never bury real planning data under sample rows, nor leave behind a login whose password is in this repository.

  **`DemoAdminSeeder` exists because `migrate:fresh --seed` drops the users table**, and `app:install` cannot be part of a seed run: it asks for a password. So development gets `DEMO_ADMIN_EMAIL` / `DEMO_ADMIN_PASSWORD` from `.env` (via `config/admin.php`, defaulting to `demo@my-page.test` / `demo-workspace`) and a real install still gets a typed password. **These are not the `ADMIN_EMAIL`/`ADMIN_PASSWORD` that were removed:** those configured the real admin on *any* install, which is how a placeholder could reach a live one. The seeder normalises both — blank falls back to `DEFAULT_EMAIL`/`DEFAULT_PASSWORD` — because a key present in `.env` but left empty is a likelier mistake than one left out, and either blank makes an account nobody can sign in to. Three things keep the two apart: the environment check is **repeated inside the seeder**, so `db:seed --class=DemoAdminSeeder` on a live box does nothing rather than trusting its caller; it **refuses when any admin already exists**, so re-running the seeders can never hand a real account a password from a repository; and the address is on `.test`, which cannot resolve. `DemoAdminSeederTest` is mostly tests of those refusals rather than of the account it makes.

  It runs **before** `DemoWeekSeeder`, which needs an admin to hang its sample tasks off and skips with a warning when there is none.

`App\Rules\StrongPassword` is the bar the command applies: 12 characters and not one of the usual suspects. Deliberately not `Password::uncompromised()`, which asks haveibeenpwned over the network — installing should not pause, or behave differently, because the machine is offline.

`activeProfile()` seeds the defaults when no profile exists, so an install that ran `migrate` alone gets an editor full of placeholder content rather than a 404.

Both planner seeders are re-runnable: `CategorySeeder` uses `updateOrCreate`; `DemoWeekSeeder` deletes its own prior rows (`source = seeder`, that user only) before recreating them pinned to the current week. Neither touches manually created tasks.

**Everything seeded is deliberately generic.** This project is meant to be forked and made someone else's, so the default categories are buckets any week falls into (Work, Learning, Projects, Health, Home, Social, Other) rather than one person's situation. The sample week covers all five `TaskStatus` cases, so a fresh install shows every state the board can be in. `DefaultPortfolioContent` ships placeholder content under the initials `AB` and the slug `default`.

## Architecture

### Public page aggregate

`PortfolioProfile` is the root model with five `hasMany` children: `socialLinks`, `metrics`, `expertiseItems`, `projects`, `processSteps`. There is exactly **one** profile row — `DefaultPortfolioContent::seed()` is the only thing that creates it and matches on a fixed `slug` — so `activeProfile()` is "the first row", not a choice between rows. `is_active` and `activate()` are gone: a column and a method holding a rule for a second profile nothing can create. Each child carries its own `sort_order` and `is_visible`. Free-text fields on profile and children are cast `array` and store `{en: ..., nl: ...}` — there is no translations table. `default_language` and `show_language_toggle` on the profile control what a first-time visitor sees and whether the EN/NL switcher renders at all. **`default_language` also decides which language owns the bare URL** — see *One URL per language* below. `headline_highlights` is a flat `[{text, tone}]` list naming which words in the headline take an accent colour; `tone` is `blue` or `gold`, matching the `.headline-*` classes. It is deliberately not a translated field — both languages' spellings share one list, and only the words in the headline currently on screen can match.

`contact_email` and `social_image_url` are flat, untranslated columns. **Both are seeded null on purpose.** `contact_email` is the address behind the contact band's "get in touch" button — it used to be a `mailto:` written into `PublicPage.vue`, the one thing about the running app that could not be changed from the workspace — and the button hides itself while the column is empty, rather than mailing nowhere. `social_image_url` is the picture a link preview shows; it is validated with `url:http,https` rather than `SafeUrl`, because a crawler on another host has to fetch it, so the fragments and relative paths `SafeUrl` exists to permit are all useless here. `app.blade.php` emits `og:image`/`twitter:image` only when it is set and asks for `summary_large_image` only then — the large card with no picture renders as a blank slab. Both are edited under **Edit page → General**.

**Social links are a child table**, `portfolio_social_links`, edited under **Edit page → Social links** and carried in the payload as its own `social_links` collection. Each row is `{label, url, icon, in_rail, in_footer}` plus `sort_order`.

**The two placements are independent** — a link can sit in the side rail, in the page footer, in both, or in neither — so each place draws its own set and each disappears on its own when nothing wants it. `is_visible` on the row is a **generated** column, `(in_rail OR in_footer)`, which is what lets `payload()`'s ordinary child filtering drop a link shown nowhere without it becoming a third switch to get out of step with the two.

They were a JSON column on the profile until 2026-09-19, which is why `PortfolioContentService` used to carry a `withVisibleSocialLinks()` that filtered them on a *clone* — the child-collection filtering could not reach into a column. Both that method and the PHP half of the `showsIn()` pair are gone; `showsIn()` survives in `resources/js/shared/portfolio.js` only as a guard against a half-built object in the editor.

The public page renders them twice: in the desktop side rail, and in the middle of the page footer between its two free-text notes (`footer_note_left` and `footer_note_right`, named for where they sit — they were `location_note` and `availability_note`, and the second had not held availability for some time). That footer is a three-track grid rather than `justify-between`, so the links sit in the true centre whatever length those two notes are, and `.site-footer-spacer` holds the middle track open when there are none. `icon` resolves through `iconMap`, so `AppIconSelect` is the editor rather than a free-text field.

`PortfolioRevision` is the page's undo history: one row per save holding the **complete** payload as a JSON snapshot, plus who saved it and when. Snapshots rather than soft deletes, because `update()` *updates* the profile row rather than deleting it — soft-deleted child rows would have left the 15 profile fields with no history at all, and carry nothing that groups them into a version. `user_id` is null only for the baseline snapshot taken before the very first save, which is what makes that first save undoable. Capped at the newest 20 per profile.

`DeveloperInquiry` (flat, untranslated) holds public connect-form submissions.

### Planner aggregate

Separate from the portfolio, all scoped to the signed-in user:

- `Task` — `title`, `start_datetime`, optional `end_datetime`, `planned_duration_minutes`, `status`, `result_notes`, `source`, `external_ref`, optional `category_id`. UNIQUE on `(user_id, source, external_ref)`, so an overlapping calendar sync or a retry cannot import one remote event twice; manual tasks hold a NULL `external_ref` and NULLs never collide.
- `Category` — self-referencing `parent_id` for **exactly one** level of nesting (a child never has children). `user_id` null = a shared/global seeded category.
- `TimeLog` — `started_at` / `ended_at` per task, plus a `user_id` denormalised from it. `duration_minutes` and `running_user_id` are generated columns; see *Two rules worth knowing* below.
- `User.timezone` — the zone the report measures a period in, `UTC` until it is
  set. See *Time handling* below for the one query that reads it.
- `Reflection` — one note per (user, period_type, period_start). `period_end` is a **generated** column derived from the type and the start, so the two cannot disagree; `scopeForPeriod()` therefore looks up on the first three and the controller neither writes nor matches on the fourth.

`App\Enums\VisualStyle` (dashboard, flow, cms) is the project card's decorative panel — each case is a class on `.project-visual` in `public.css`, so a value with no rule behind it draws a blank panel. That is why `visual_style` is validated with `Rule::enum` and edited with a select rather than typed. `VISUAL_STYLES` in `resources/js/shared/portfolio.js` mirrors it, `portfolio-fields.test.js` reads the PHP file and fails on drift, and `normalizePortfolio()` rewrites anything unrecognised to the first case so an older row cannot make the editor save a payload the rules reject.

Enums in `app/Enums/`: `TaskStatus` (planned, in_progress, paused, done, skipped), `TaskSource` (manual, seeder, ai_chat — the last reserved for future AI-assisted task creation and unused today), `ReflectionPeriodType` (week, month, plus `startFor()`/`endFor()`, which own the period boundaries the report and the reflection upsert both key on). Statuses are plain string columns validated against the enum, not DB enums, because adding a case to a DB enum needs an `ALTER TABLE`. **`TASK_STATUSES` in `resources/js/shared/planning.js` mirrors `TaskStatus` — keep them in step.**

### Auth

`spatie/laravel-permission` provides roles; one admin user holds the `admin` role. Login is plain Laravel session auth (`AuthController`), no Breeze/Fortify. All `{admin}*` routes carry `['auth', 'role:admin']` (the `role` alias is registered in `bootstrap/app.php`, since Laravel 11+ has no `Kernel.php`).

**`{admin}` is a placeholder, not a literal path.** The private workspace's URL prefix is per-install — `ADMIN_PATH` in `.env` → `config/admin.php` → `config('admin.path')` — so nothing hardcodes it.

**Never document a way to derive that prefix, and never ship a real-looking default.** This repository is public, so any convention written down here is the first thing someone would try against a live install. The fallback is an obvious placeholder and the docs say to generate a random value.


- `routes/web.php` registers two `Route::prefix(config('admin.path'))` groups: one carrying `['auth', 'role:admin']` for the SPA shell routes and JSON endpoints, and one without it for `login` and `logout`, which cannot require a session you do not have yet. **There is no `/login`** — it 404s, so the commodity scanners that probe for a login form find nothing, and the workspace being unguessable is not undone by the door to it sitting at the web's most predictable URL.
- `AuthController` falls back to `'/'.config('admin.path')` for the post-login redirect.
- `resources/views/app.blade.php` emits `<meta name="admin-path">` **only for a request already inside the workspace prefix**, login page included — every route renders this one shell, so emitting it unconditionally put the private URL in the public page's source. The login page is told the prefix because it must build its own form action and router path, and reaching that URL already required knowing it. `AdminAccessTest` covers both directions; `resources/js/shared/admin-path.js` reads it once and exports `adminBase` / `adminUrl(suffix)`. `router-admin.js` builds its route paths from `adminUrl()`, and `planning.js` + `AdminPage.vue` build every fetch URL from it. Components navigate by route **name**, so none of them know the prefix.
- `phpunit.xml` sets `ADMIN_PATH=test-workspace` — deliberately *not* the shipped default — and tests build URLs via `Tests\TestCase::adminUrl()`. Anything that reintroduces a literal prefix fails the suite rather than passing by coincidence.

Changing `ADMIN_PATH` requires `php artisan route:clear` (a cached route table holds the old prefix).

`bootstrap/app.php` appends `SecurityHeaders` to the `web` group, so every route carries `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` and a Content Security Policy. The CSP is `'self'`-only for script: Vite builds all the JS and CSS and `laravel-vite-plugin/fonts` self-hosts the fonts, so nothing legitimate loads from another origin, and the Vite dev server's origin is added back automatically by reading its hot file. That origin must be a *name* — `vite.config.js` pins `server.host` to `localhost` because Vite otherwise binds to IPv6 loopback and writes `http://[::1]:5173`, and CSP's host-source grammar has no form for a bracketed IPv6 literal. A browser drops a source it cannot parse and enforces the rest, so one such entry blocks the whole dev bundle and reports it only in the console. `SecurityHeadersTest` parses every source the policy emits. That policy is also what stops an owner-supplied link that slipped past `App\Rules\SafeUrl` from executing.

Session cookies are hardened in `config/session.php` rather than left to whoever writes the `.env`: `secure` defaults to on everywhere except `APP_ENV=local`, and `same_site` is `strict` rather than Laravel's `lax`, since nothing here is meant to be reached from another site.

Login is rate-limited by the named `login` limiter defined in `AppServiceProvider::boot()` — per address *and* per account, since keying on one alone leaves either a distributed attempt on a single account or a lockout of the owner.

**Two-factor is opt-in.** The workspace works with a password alone until the owner turns it on from Insights → Security, so a fresh install or a fork never depends on having an authenticator to hand. `TwoFactorService` (TOTP via `pragmarx/google2fa`, QR via `bacon/bacon-qr-code`) owns the whole lifecycle; `users.two_factor_secret` and `two_factor_recovery_codes` are `encrypted` casts and are in the model's `#[Hidden]` list, so they never serialize. Enrolment is two steps on purpose: a secret alone is never enforced, and `two_factor_confirmed_at` is set only once a real code has been checked, so a mis-scanned QR is a retry rather than a lockout. `AuthController::store()` uses `Auth::validate()` rather than `Auth::attempt()` — it checks the password without starting a session, so an account with two-factor on is never briefly signed in, and the `Login` event the trail records as "signed in" fires only once the second factor has passed too. Recovery codes are single-use, and `php artisan two-factor:disable {email}` is the way back in when the phone and the codes are both gone.

**Every sign-in attempt is recorded.** `SecurityEvent` holds one row per attempt with its outcome (`SecurityEventType`), address and the email that was typed. `RecordSignIn` and `RecordFailedSignIn` in `app/Listeners/` handle Laravel's `Login` and `Failed` events, and the limiter's own `response()` callback records the blocked ones — those never reach a controller, so without that hook the trail would go quiet exactly when an attack got loud. Rows carry IP addresses and pile up fastest when something is wrong, so they expire: `MassPrunable` plus a daily `model:prune` scheduled in `routes/console.php`, keeping `SecurityEvent::RETENTION_DAYS`. **That needs a scheduler running** — `schedule:work` locally (it is one of the five processes `composer run dev` starts) and a `schedule:run` cron entry on a server, as README's *On a real server* says. Without one the retention is a comment rather than a behaviour, and the same goes for the queue worker `MailTheInquiry` waits on.

`config/filesystems.php` sets `'serve' => false` on the `local` disk, against Laravel's default. `true` registers `GET` and `PUT` at `/storage/{path}` with no middleware; both are gated by a signed URL so neither is a hole, but nothing here uses `Storage` at all. `RouteProtectionTest` reads the route table and fails on any route carrying neither `auth` nor `guest` that is not on its short list of deliberately public ones — which is how those two were found.

`bootstrap/app.php` also fixes a `shouldRenderJsonWhen()` gotcha — without the `|| $request->expectsJson()` clause, every non-`api/*` validation failure (e.g. a wrong login password) renders as an HTML redirect instead of JSON, breaking every `fetch()`-based form in `resources/js`.

#### One URL per language

The public page is published at one address per language: the profile's `default_language` keeps the bare path and every other locale in `config('app.locales')` gets a prefix — `/` and `/nl`, or `/` and `/en` if the owner makes Dutch the default. `routes/web.php` registers `/{locale}` and `/{locale}/hi-developer` behind a `where` constraint built from that config, so no other path can be swallowed.

**The language used to live only in `localStorage`.** Both languages therefore shared `/`: nobody could send a Dutch link, and a crawler — which carries no storage and runs no JavaScript — only ever saw the default one, so half the page was unfindable.

`PortfolioController::app()` takes the locale as an optional route parameter and **redirects `/{default}` to the bare path with a 301**, so no page is reachable at two addresses. `publicMeta()` builds `alternates` — an absolute URL per locale — and `app.blade.php` emits them as `hreflang` links plus `x-default`, which is what tells a crawler that `/nl` is the same page in Dutch rather than a duplicate or an unrelated one. The title, description and schema all follow the active locale, not `en`.

On the frontend, `localeFromPath()` / `barePath()` / `pathForLocale()` in `shared/i18n.js` mirror that rule — **keep them in step with the route constraint and `config('app.locales')`**. `lang` initialises from the URL *before* `localStorage`, because the URL is what was shared and indexed. `preferredPath()` turns a stored choice into a `router.replace()` rather than letting it quietly paint a language the URL does not claim, which would leave the server's meta and the rendered body disagreeing. The public switcher navigates; the workspace's own EN/NL toggle still just sets `lang`, since that is a working preference and the workspace has no locale prefix.

`SetPublicLocale` middleware puts the request into the URL's language so Laravel's own validator answers in it.

**The workspace has no URL prefix, so it says which language it is in with a header.** `SetWorkspaceLocale` reads `X-App-Language`, which `apiFetch` sets from `lang` on every request, and checks it against `config('app.locales')` — the header is client-supplied, and `setLocale()` with anything else loads a path off it. `lang/nl/validation.php` covers the rules either half can trip; everything else falls back to `en`. **This app's own rule messages live in `lang/{locale}/rules.php`** — `SafeUrl`, the task range cap, the end-before-start check and the category parent rules — because a `$fail('...')` with a literal string is English whatever the request asked for. `StrongPassword` is the exception and stays literal: only `app:install` applies it, and a CLI prompt has no locale.

## What a crawler sees

The public page is painted by Vue in the browser, so the HTML that comes back holds no copy. The crawlers behind link previews — LinkedIn, WhatsApp, Slack, iMessage — **do not run JavaScript**, so whatever is missing from that response is missing from the preview. `PortfolioController::publicMeta()` therefore reads the active profile and `app.blade.php` renders the title, description, canonical, Open Graph, Twitter card and a schema.org `Person` block server-side, with `<html lang>` following `default_language`. The ld+json is encoded with `JSON_HEX_*` so a field containing `</script>` cannot close the block and turn the rest of the head into page content — `PublicPageMetaTest` covers that case specifically.

The workspace renders the same shell and gets the opposite treatment: `noindex, nofollow`, no description, no card. It is already behind a login and an unguessable prefix, but a prefix that ever leaks should not then be handed to an index.

**This is meta only — the body is still client-rendered.** Googlebot does execute JavaScript, so the page is indexed, but on a second pass and less reliably than served markup. Fixing that properly means server-rendering this one page (Blade, Inertia, or prerendering at build time), which is a real change to how the app is served and has not been made.

## Backend API

No `/api` prefix — admin JSON endpoints live under `{admin}/...` alongside the SPA shell routes.

**SPA shell** (all render the same Blade view; the bundle's router picks the page — see *Two bundles* below): `/`, `/hi-developer`, `{admin}/login`, `{admin}`, `{admin}/agenda`, `{admin}/insights`, `{admin}/edit-content`, `{admin}/settings`.

**Portfolio** (`PortfolioController`):
- `GET /portfolio` → public payload, `is_visible = true` only. No login, so the shape is deliberate — see *One payload shape* below.
- `GET|PUT {admin}/portfolio` → unfiltered payload / full replace-on-save.
- `POST {admin}/portfolio/seed-defaults` → reset to `DefaultPortfolioContent`.
- `GET {admin}/portfolio/revisions` → saved-version list (`id`, `created_at`, `author`). Deliberately **excludes** `payload`, which runs to tens of KB per row.
- `POST {admin}/portfolio/revisions/{revision}/restore` → re-applies that snapshot; 404 if it belongs to another profile.

All four writes go through `PortfolioContentService` — `save()`, `seedDefaults()` and `restore()` share one transaction and one write path, so a restored version can never be built differently from a saved one, and all three record history without any of them remembering to. `save()` applies profile scalars via `Arr::only(...)`, then `PortfolioProfile::replaceChildren()` per child collection, which **deletes all rows for that relation and recreates them from the submitted array**, assigning `sort_order` by position. There is no per-row PATCH — the admin always submits complete collection state.

`replaceChildren()` lives on the model because **both** writers use it: a save, and `PortfolioSeeder` writing the starting content. They were two near-identical private methods until 2026-09-21, which is two places to remember that social links derive `is_visible` and MySQL rejects an INSERT naming a generated column.

**Because a save replaces each collection whole, an omitted key is a request to delete it** — so `UpdatePortfolioRequest` marks all five `present`. Not `required`, which rejects an empty array: having no projects is allowed, forgetting to mention them is not. Nine tests were quietly emptying `social_links` this way before the rule was added. `restore()` is literally `save($revision->payload)`, which is why it has no logic of its own.

The five child collections all go through the same machinery: a key list in `PortfolioFields::CHILDREN`, an entry in `PortfolioFields::RELATIONS`, and `replaceChildren()`. It sets `is_visible` **only where the relation has the column** — social links derive theirs, and MySQL rejects an INSERT naming a generated column.

*Adding a profile/child field:* migration → model `$fillable`/`$casts` → the matching list in `App\Support\PortfolioFields` (`PROFILE` or `CHILDREN`) → `DefaultPortfolioContent::content()` if it should ship seeded. **A translated field needs no rule written by hand:** `UpdatePortfolioRequest` builds its `{en, nl}` rules from `PortfolioFields::TRANSLATED_PROFILE` and `TRANSLATED_CHILDREN`, so adding it to the list is what makes it validated. Those two constants are mirrored by `translatableProfile` / `translatableItemFields` in `resources/js/shared/portfolio.js`, which cannot import PHP — `portfolio-fields.test.js` reads the constants out of the file and fails when the two sides disagree.

#### One payload shape

`payload()` is the only shape any read of the page returns — the public endpoint, the admin endpoint, and the JSON a `PortfolioRevision` stores. `PortfolioPayload` builds it from `PortfolioProfileResource` and `PortfolioItemResource`, which emit **exactly** `PortfolioFields::PROFILE` and `PortfolioFields::CHILDREN[$relation]`.

**It used to hand out the models.** `GET /portfolio` needs no login, so `id`, `slug`, `type`, `is_active` and the timestamps were public — and because `activeProfile()` eager-loads the four relations, the profile serialized them too, so every child row was sent once nested under `profile` and once at the top level. The cost of the metadata was small; the cost of the *default* was not, since any column added later joined the public response with no code change and no decision.

The resources are built from the same constants `save()` writes by, on purpose: **a field is published because it was made editable, and nothing else ever is.** That is also why adding a field is still the five steps above — the resource follows the constant.

What they drop per child row is `id`, `portfolio_profile_id`, the timestamps and **`sort_order`**: position is the order of the array, which is the only thing the page reads. `PortfolioContentTest` and `PortfolioHistoryTest` therefore check resequencing against the column in the database rather than against the response.

`PortfolioPayloadShapeTest` guards both halves — the key sets, derived from the constants so adding a field needs no test edit, and a hardcoded list of metadata keys that must not appear, which is the claim itself and stays put.

`UpdatePortfolioRequest` validates **types and lengths, not presence**: the editor lets fields be cleared, so `required` on free text would reject payloads the UI legitimately produces. Presence is demanded only where the column is NOT NULL (`metrics.*.value`), because Laravel's `ConvertEmptyStringsToNull` middleware turns a cleared field into `null` and the insert would otherwise 500 instead of returning a readable 422. Two tests in `PortfolioContentTest` guard this by fetching the admin payload and PUTting it straight back — the same round trip pressing Save performs.

**Planner**:
The public connect form has three layers against spam: `throttle:10,1` on the route, a `website` honeypot field that is off-screen and `aria-hidden` (a filled one gets the same 200 a real submission does, and is discarded before validation so probing cannot tell them apart), and the validation rules themselves.

- `GET|POST {admin}/tasks`, `PUT|DELETE {admin}/tasks/{task}` — index requires `start`/`end` date params and rejects a span wider than a year; the calendar fetches by visible range.

  **Every one of these returns `TaskResource`, and a task carries `running_log`, not `time_logs`.** The board asks one question of a timer — is it running, and since when — and the answer is one row: `Task::runningTimeLog()` is a `hasOne` filtered to the open log, which the UNIQUE index on `running_user_id` makes at most one per owner. It used to eager-load the whole relation, so a month grid shipped every finished sitting of every task on it, and a year-wide range shipped a year of them. Closed logs are report input, and the report sums them in SQL without sending one. `runningTimeLog()` in `resources/js/shared/planning.js` reads that field; `TaskPayloadShapeTest` holds the key list.
- `POST {admin}/tasks/{task}/timer/start|stop`.
- `GET|POST {admin}/categories`, `PUT|DELETE {admin}/categories/{category}` — the index applies "mine or global" to **both** levels. A global parent is shared by everyone, so eager-loading its `children` unfiltered handed every user the others' private subcategories; that is what it did until 2026-09-21.
- `GET {admin}/reports?period_type=week|month&period_start=Y-m-d` — totals come from `withSum` on `time_logs.duration_minutes`, so the stored column is summed in SQL rather than in PHP. `by_category` groups by `category_id`, not by name, and leaves the uncategorized bucket's label to the frontend.

  **Tracked and planned are counted over different rows, on purpose.** A task and the minutes spent on it can fall in different periods, so the `withSum` is *constrained to logs started inside the period* and the task set is "scheduled here **or** tracked here". Summing a task's whole history instead — which is what it did until 2026-09-21 — put last month's minutes in this week's total and hid minutes tracked this week on last week's task. Planned time is the mirror rule: a task that merely collected minutes here contributes 0, because its plan is counted in the period it was scheduled in.

  **`period_start` is normalised, not trusted.** `ReflectionPeriodType::startFor()`/`endFor()` own the week/month boundaries — one `match` per rule, the same pair the reflection upsert keys on, so a Wednesday and the Monday before it cannot name two different weeks or buy a half-width report.
- `GET|PUT {admin}/reflections` (upsert by period).
- `GET|POST {admin}/two-factor`, `POST {admin}/two-factor/confirm`, `POST {admin}/two-factor/recovery-codes`, `DELETE {admin}/two-factor` — enrolment. The delete takes the password in its body rather than relying on the open session, so a machine left unlocked cannot strip the account back to one factor.
- `POST {admin}/two-factor-challenge` — the second step at sign-in, throttled by the same `login` limiter as the password step.
- `GET|PUT {admin}/timezone` — the owner's zone, and the list of zones the save
  accepts, so the picker cannot offer one it would then reject. Saved the
  moment it is picked rather than through the Edit page's payload: it belongs
  to the account, not to the public page.
- `GET {admin}/security-events` — the sign-in trail: a per-address rollup over the last 12 hours, outcome totals for that window, and the 50 most recent attempts. Rendered by the **Security** tab under Insights.
- `GET {admin}/inquiries?page=N` — intentionally **view-only**; no update/destroy exists. `simplePaginate`d into `{inquiries, page, has_more}`, since the public form that fills it is throttled per minute rather than in total.

Ownership lives in `app/Policies/` (`TaskPolicy`, `CategoryPolicy`), found by naming convention — nothing registers them. `CategoryPolicy` keeps the rule that a **global** category (`user_id` null) is editable by anyone, but *deletable* only while no other user's subcategory hangs off it — `parent_id` cascades and `tasks.category_id` nulls, so deleting a shared row takes someone else's subcategories with it and unfiles their tasks. The base `Controller` carries `AuthorizesRequests`, which Laravel 11+ leaves off, so `$this->authorize()` works. Write endpoints with a request body check ownership in their Form Request's `authorize()`; `destroy` and the timer endpoints (no body, so no Form Request) call `$this->authorize()` directly.

### Where the logic lives

Controllers validate, authorize, delegate, and return JSON. Rules that outlive a request live elsewhere:

- **`app/Contracts/`** — the only two interfaces in the app, and both have somewhere to go. `TwoFactorProvider` is the second factor's lifecycle without saying what the factor is: TOTP today, passkeys the plausible next one, and `qrCodeSvg()`/`otpauthUri()` are deliberately *not* on it because a passkey provider has no answer for them — the controller asks `enrolmentDetails()` instead, which each provider fills with whatever its own screen shows. `PortfolioSeedContent` is the content a fresh install starts with, so a fork binds its own words without editing ours. `AppServiceProvider::register()` binds exactly these two and nothing else: an interface with one implementation behind it is a file and an indirection.

- **`app/Events/` and `app/Listeners/`** — `PortfolioSaved`, `InquiryReceived`, `TimerStarted` / `TimerStopped`. Laravel discovers listeners from their `handle()` signature, so nothing registers them.

  **They dispatch after the transaction commits, never inside it** — a listener that reads the page must not see a version a rollback is about to undo. And not for a no-op: `start()` on a task that is already running dispatches nothing.

  `PortfolioSaved` carries a `restored` flag rather than there being a separate `PortfolioRestored`, because a restore *is* a save through the same method — that is the property `PortfolioContentService` exists for, so two classes would be two wrappers over one boolean.

  `MailTheInquiry` is how the owner hears about a connect-form message without opening Insights. It mails the **admin account's** address, not the profile's public `contact_email`, and the notification is `ShouldQueue` so the public form answers the visitor whether or not the mail provider is reachable. It finds the owner with `whereHas('roles', ...)` rather than Spatie's `role()` scope, which throws `RoleDoesNotExist` before anything has created the role — that would turn a stranger's message into a 500 on a fresh install.

- **`app/Services/`** — `PortfolioContentService`, `PortfolioPayload`, `PortfolioHistory`, `PortfolioSeeder` and `TimerService`.

  The portfolio's three are one job each, split out of a class that had four reasons to change. **`PortfolioContentService` is the single write path** — `save()`, `seedDefaults()` and `restore()` in one transaction, which is the property the class exists for and the one a split could quietly lose (`PortfolioHistoryTest` has a test that a failed save takes its revision back with it). **`PortfolioPayload`** decides the shape of a read. **`PortfolioHistory`** owns the undo snapshots and their cap. What a profile and its children are *made of* lives in neither: `App\Support\PortfolioFields` holds the lists, so the read and the write cannot disagree about which fields exist. **`PortfolioSeeder`** writes the starting content, taking the words themselves from the `PortfolioSeedContent` contract — only one of those two is worth replacing, since a fork wants its own copy, not its own way of inserting rows. Plain concrete classes injected via `__construct()`; the container resolves them by reflection, so **`AppServiceProvider` registers no bindings** — no interfaces, no singletons. Add one only when a second implementation actually exists. Its `boot()` holds the `login` rate limiter and nothing else.
- **`app/Http/Resources/`** — `PortfolioProfileResource`, `PortfolioItemResource` and `TaskResource`, the classes that decide what leaves the app. See *One payload shape* above for the portfolio's two. `TaskResource` is there for a different reason: not to withhold anything from an owner reading their own rows, but because a task used to be handed over as the model, and a model carries its whole `timeLogs` relation. Categories still go out as models — a category is four columns and its children, and nothing about it grows with use.
- **`app/Http/Requests/`** — `Store`/`Update` pairs for Task and Category, plus `UpdatePortfolioRequest`. Pairs, not single classes: the partial-update path swaps `required` for `sometimes`, so one rule set genuinely cannot serve both.
- **`app/Policies/`** — ownership, as above.
- **The models themselves** — `Task::plannedMinutes()`, `Reflection::scopeForPeriod()` (the read and the upsert must find a row identically, and the `whereDate()` reasoning belongs in one place).

`AuthController`, `DeveloperInquiryController`, `ReflectionController` and `ReportController` deliberately keep inline `$request->validate()`. Their rules are short and single-use; converting them would be ceremony.

### Two rules worth knowing before editing planner code

**Only one timer runs at a time.** `TimerService::start()` calls `pauseOtherRunningTasks()`, which closes any other open log for that user and sets those tasks to `paused`. Without it the same minutes count against several tasks and every report total overstates the day. Those logs are closed **one model at a time** (`$log->update(...)`), not via a mass query update — a builder `update()` bypasses `TimeLog::booted()` and would silently skip computing `duration_minutes`. `TimerServiceTest` calls the service directly, with no HTTP involved.

**That rule is a read followed by a write, so it is held twice — by a lock and by the database.** "Is anything running?" then "insert a log" is a race: two clicks landing together both read *no* and both insert. `start()` and `stop()` each run in a `DB::transaction()` that opens by taking `lockForUpdate()` on the **user** row. The user, not the task — the rule is per-user, so two *different* tasks started at the same instant would take two different task locks and both still open a log. The user row is the one row every timer change for that owner has in common, and it always exists: `lockForUpdate()` on a query matching nothing takes no row lock, only an index gap lock.

Underneath it, `time_logs` carries a **virtual generated column** `running_user_id AS (IF(ended_at IS NULL, user_id, NULL))` with a UNIQUE index on it, so a second open log for one user is refused outright. Closed logs hold NULL, and a unique index does not compare NULLs, so any number of finished logs coexist. The lock stays because it turns the race into an orderly *pause* of the other task, which is the behaviour the app wants; the constraint catches the path that forgets to take it.

`running_user_id` is **virtual, not stored**: MySQL refuses `ON DELETE CASCADE` on a column a stored generated column is built from, and the cascade on `user_id` is worth more than storing a value only ever read through the index. `time_logs.user_id` is itself denormalised from the task — filled by a `creating` hook on `TimeLog`, not at each call site, so it cannot disagree with `tasks.user_id`.

**`duration_minutes` is generated too**, `GREATEST(TIMESTAMPDIFF(MINUTE, started_at, ended_at), 0)` STORED. It used to be computed in a `saving` hook, which a builder `update()` bypassed — so a mass update silently left it null and every report total was short. Derived by the database it cannot disagree with its own timestamps by any path. Still stored, so report totals stay one `SUM()`. The catch: **the model that did the insert does not know the value** — read it back with `->refresh()`. Nothing in the app needs to; reports sum it in SQL.

`Carbon::now()` is called directly here on purpose — `Carbon::setTestNow()` already makes it controllable, so a Clock abstraction would solve a problem the framework has solved.

**Planned time has a fallback.** `Task::plannedMinutes()` prefers `planned_duration_minutes`, falls back to the scheduled `start → end` span (tasks added via the calendar set times but no explicit duration), and returns 0 for open-ended tasks.

## Frontend

`resources/js/pages/` is split by audience: `public/` holds what an anonymous visitor sees, `admin/` everything behind the login (including `LoginPage.vue`, which is the door to it).

**`admin/` is then split by section** — `agenda/`, `edit/`, `insights/`, `settings/` — each holding its own page component and the tabs only it renders. They were one flat folder mixing pages, editor tabs and settings panels while `agenda/` already had its own; now the four look the same.

**Each section loads what it shows.** `AdminPage.vue` is the rail and a `v-if` over four components, and nothing else: opening Agenda used to fetch the connect-form messages, the sign-in trail and the saved-version list too, because one component owned all four loaders.

**Template comments are compiled away.** A comment in a `<template>` is markup, so Vue turns it into a real DOM node — readable in the inspector and served in the page. `vue-plugin.js` at the repo root sets `compilerOptions.comments: false` and **both** `vite.config.js` and `vitest.config.js` build their Vue plugin from it, so the app and the tests compile a component the same way. The production build already dropped them; this drops them in development too, which is where they were showing. Vue's own `<!--v-if-->` anchors are a different thing — they mark the place an absent branch would go — and they stay. `template-comments.test.js` covers both halves.

So a `<!-- -->` next to the markup it explains costs nothing, and that is where such a note belongs. What does not belong there is anything the markup already says: a comment earns its place by recording a decision or a trap, not by narrating the next line.

**Two bundles, not one.** `vite.config.js` builds `app-public.js` and `app-admin.js`, and `app.blade.php` loads one or the other from `$inWorkspace` — the login page counts as inside, since it is the door. One bundle used to serve both halves, so anyone could fetch `AdminPage`'s chunk from the public site and read the private API's endpoint names out of it. It never leaked `ADMIN_PATH`, which is in a meta tag and in no built asset, so what leaked was the shape of the API rather than its location.

`$inWorkspace` is passed separately from `$adminPath` rather than derived from it: they share a condition today, and a change to what the meta tag is for should not silently change which JavaScript is served.

Each entry has its own router — `router-public.js` and `router-admin.js` — and they share `create-app.js`, which holds the PrimeVue options both need. The split is only worth what enforces it, so two tests do: `bundle-split.test.js` walks the real import graph from each entry and fails if the public one can reach anything under `pages/admin/`, `components/admin/` or `shared/planning.js`; `AdminAccessTest` checks the Blade shell serves the right one to each half.

`router-public.js` maps `/`, `/hi-developer` and their language-prefixed twins to `public/PublicPage.vue` (which also renders `DeveloperConnectModal` on the connect routes). `router-admin.js` maps `admin/LoginPage.vue` and `admin/AdminPage.vue` for every workspace route — `AdminPage` derives its active section from the route name, so each section is a real bookmarkable/refreshable URL.

The CSS is still one entry (`app.css`) for both. Splitting it would save bytes, not secrets: Tailwind generates its utilities by scanning the same sources either way, so the saving is the hand-written partials only.

**The four workspace sections**, and the split between them:

| Section | Holds |
|---|---|
| Agenda | the planner |
| Insights | connect-form messages, news, and the sign-in trail — `Security` last and `right: true`, since it is a log you check rather than a feed you read |
| Edit page | the public page's content, and nothing else — the six content tabs plus **General**, trailing, for the values that are the same in both languages (initials, the CTA URLs, the accent word lists) |
| Settings | Language & time, two-step sign-in, Content versions — changed rarely, and none of it is page copy |

Only the Edit page and Settings' Language & time tab put content in the unsaved
payload — and within that tab, only its two language rows. The timezone sitting
under them is a property of the account rather than of the page, so it persists
through `{admin}/timezone` the moment it is picked, and says so in its own
hint — as does everything else in Settings and Insights.

**Those two share one payload, so it is the one thing the shell still owns.** `usePortfolioEditor.js` holds the unsaved payload, `dirty`, `save()`, the two restores and the revision list; `AdminPage` calls `providePortfolioEditor()` once and `EditPage`/`SettingsPage` `inject()` it, so an edit made on one survives walking over to the other. Per-section instances would each fetch, and switching sections would throw the edit away.

**The Save button is disabled until a payload has actually arrived** (`ready`), and a failed load draws a retry rather than an empty editor. A page with no content and a page that failed to load look identical, and saving the second would replace the live page with nothing.

**The editor tabs emit, they do not call.** `@add` / `@remove` / `@move` carry the collection name, which each tab knows about itself; they used to be functions passed down as props, which is what made `ProjectsTab` take six props, four of them callbacks. `EditableCard` takes no `collection` prop for the same reason — it reports a position, and the tab that rendered it says which list that position is in. **A tab's "add" defaults live in its `blank()`**, in the script, not written into a `@click` in markup where nobody looking for a default would find them.

**Admin shell** (`resources/js/components/admin/`):
- `AdminLayout.vue` — the rail and the frame the sheet sits in. **There is no header:** the initials badge, the EN/NL switch, the theme switch and sign-out all live at the bottom of the rail, which is what retired `--admin-header`, the `ResizeObserver` that measured it, and the sticky offset the old rail nav hung off.

  **The rail is two groups.** Destinations at the top; anything flagged `foot: true` in `navItems` is pinned to the bottom above the switchers — the same flag-on-the-item convention the tab strip uses for `right`. Settings carries it.

  **A fixed panel inside a placeholder aside.** `.admin-rail` reserves `--admin-rail` in the flex row and `.admin-rail-panel` is drawn fixed at that same width, so opening the rail below `lg` overlays the sheet rather than reflowing it — content under an open rail must not slide sideways while you are pointing at something. One token moves both halves, so they cannot disagree about where the sheet starts.

  **Below `lg` the rail collapses to icons** (`--admin-rail-collapsed`) and opens to `--admin-rail-open` on hover, keyboard focus, or the chevron, which pins it for touch (no hover, no focus) and persists that choice. The three differences between open and shut — label opacity, the switcher stack's axis, the auto margin before sign-out — are carried by three custom properties set on the panel in `responsive.css`, so a state lives in one rule instead of a descendant selector per property. The open state keys off the **panel**, not the placeholder: the panel grows past the placeholder's box, so a pointer resting on an open rail would otherwise leave it and collapse it again. Labels are hidden with `opacity`, not `display`, so the icon-only rail still announces each row.

  The small-screen bottom bar is gone with `--admin-bottom-nav`; so are both floating buttons and `--z-fab`, replaced by the sheet's action bar.
- `AdminSheet.vue` — the card every section renders into: page heading, the underline tab strip, the body, and the action bar along the bottom. **The only place that shape is rendered** (it replaced `SectionTabs.vue`, and with it `.admin-panel` and the folder-bookmark tabs). Agenda's day/week/month switch and the Edit page's content tabs were two different controls doing one job; now they are the same strip.

  Real tab semantics — `role="tablist"`/`tab`/`tabpanel`, roving `tabindex`, Left/Right/Home/End — because a `role="tab"` without keyboard handling is a promise the widget does not keep. Passing `:tabs="[]"` still yields the card and its heading, just with no tab row. The `status` and `actions` slots fill the action bar; a section that supplies neither gets no empty strip. The bar is `sticky bottom-0`, which is how the save button stays reachable down a long editor — the job the floating button did.

The **Content versions** tab (`pages/admin/ContentVersionsTab.vue`, under Settings) is a single list of states you can go back to. The seeded defaults are its **first row**, not a separate section — they are just another version to restore. `AdminPage.vue` owns the state (`revisions`, `revisionsLoading`, `restoring`, `restoringId`) and refreshes the list after every save, reset and restore, so a new version appears without a reload. Both restore paths go through the shared `confirm()` and both lock every button in the list while one is in flight: they overwrite the live public page, and the defaults row is now one click away from the saved versions rather than guarded by its own warning block.

Like its sibling tabs it carries no heading of its own — the sheet's title and subtitle already name the section and say what the tab edits, which is the job the `.admin-note` paragraphs inside the old panel were doing.

For the sheet to stretch to the bottom of the page, its ancestors must form an unbroken flex column: `.admin-shell` → `.admin-frame` → `.admin-sheet`. Agenda, Insights and Edit each render their own sheet, so a change to that chain needs checking on all three.

**Agenda** (`resources/js/pages/admin/agenda/`): `CalendarView` (mode switching, filters, period navigation, task CRUD wiring) → `DayView` / `WeekView` / `MonthView` / `CategoriesView` / `ReportView`, plus `TaskCard`, `TaskModal`, `CategoryModal`.

**A skip link is the first focusable element in both halves** — `#main-content` on the visit card, `#workspace-content` in the workspace. Both targets carry `tabindex="-1"`, so the next Tab continues from the content rather than restarting at the top of the document; `.skip-link` in `base.css` is off-screen by transform rather than `display:none`, which would take it out of the tab order entirely. `pages.smoke.test.js` checks all three properties on both pages.

**Tests live in `resources/tests/`**, mirroring `resources/js/` — not beside the files they cover. `vitest.config.js` points there and loads `resources/tests/setup.js` first.

**Shared** (`resources/js/shared/`):
- `api.js` — `apiFetch(url, {method, body})`, `errorMessage()`, `reportError()` and `csrfToken()`. **Every** call to a JSON endpoint goes through `apiFetch`; no component calls `fetch()` directly. It sets `Accept: application/json` and the CSRF header, throws an `ApiError` carrying `status` and the parsed `body` on any non-2xx, and redirects to `/login` on a 401. The `Accept` header is the load-bearing part: without it an expired session takes the auth middleware's HTML redirect instead of a JSON 401, `response.json()` throws on the HTML, and whichever `loading` ref was in flight never clears. Loaders pair it with `try/finally` for the same reason.

  **`apiFetch` carries no error text of its own.** What a person is shown is either the server's own message or the words the calling component passes, in the language on screen. It used to take a `message` option and fall back to it — and since that message was always set, `error.message` always won, so every translated fallback a caller passed was unreachable and a Dutch workspace reported failures in English. Now it throws with an empty message when the server said nothing showable, and `api.test.js` holds that contract.

  **And every `catch` goes through `errorMessage()` / `reportError()`**, so one kind of failure reads the same wherever it happens. It decides what a person is shown: a field-level validation message wins, because it names what to fix; a 4xx `message` is passed through, because those are written for a reader; a **5xx message never is**, because that is the exception's own text and with `APP_DEBUG` on it is the first line of a stack trace. A `TypeError` is a failed `fetch` — no network — and gets said so. Anything else is a bug: it goes to the console, and the user gets the caller's own words. A 401 says nothing at all, since the page is already bouncing to the login form.

  `reportError(error, fallback)` is that, shown as a toast — the one line a `catch` block needs. `errorMessage()` is the same decision returned as a string, for the two forms that show it inline instead: the connect modal and the login page.

- `admin-path.js` — `adminBase` / `adminUrl(suffix)`, read once from the `admin-path` meta tag. Every admin URL in the frontend goes through it; never write the prefix out by hand. The tag is emitted only for a signed-in admin (`PortfolioController::app()`), since every page renders the same shell and the prefix is meant to be unguessable.
- `portfolio.js` — fetch/normalize + `usePortfolioSource`.
- `planning.js` — date helpers, `usePlanning` (all planner fetches/mutations), `useRunningElapsed` (the ticking timer label, shared by `TaskCard` and `TaskModal` so they can't drift).
- `i18n.js` — `lang`/`copy`/`t`, the strings both halves use, and `registerUi()`. **`lang` is also what `apiFetch` sends as `X-App-Language`**, so the server answers in the same language the screen is in. The dictionary is **split by audience** the same way `pages/` is: `i18n-public.js` for the visit card and the connect modal, `i18n-admin.js` for the workspace, and the handful both use left in `i18n.js`.

  **`i18n.js` imports neither half.** Each entry point registers its own — `app-public.js` calls `registerUi(publicUi)`, `app-admin.js` calls `registerUi(adminUi)` — because importing both put all 493 workspace strings in the public bundle, where a visitor could read "Two-step sign-in" and "Recovery codes" out of it. That is the same leak the two bundles exist to close, and `bundle-split.test.js` now guards it too. Tests mount components directly and run no entry point, so `resources/tests/setup.js` registers both for them.

  Registration happens before the app mounts, so `copy()` and `t()` are unchanged and no component knows about the split. `lang` is a module-level singleton persisted to `localStorage`. **`copy()` falls back to English on a missing key**, so a dropped Dutch string ships silently; `i18n.test.js` checks each dictionary *file* for `en`/`nl` parity rather than the merged `ui`, which after the split would only ever hold what that test registered.
- `toast.js` / `confirm.js` — module-level singletons rendered once by `ToastStack` / `ConfirmDialog` in `AdminLayout`'s slot. Use `confirm()` (returns a promise) rather than `window.confirm`.
- `icons.js` — `iconMap` string-key → lucide component. A new `icon` value in DB/seed data must be added here or it silently renders nothing.

### Time handling — read this before touching dates

The backend runs `APP_TIMEZONE=UTC` and Eloquent serializes datetimes with a `Z` suffix, but **not all of them are real instants**:

- `start_datetime` / `end_datetime` are the user's **wall-clock** time ("09:00" as typed). Parse with `parseServerDatetime()`, which reads the literal Y-M-D H:i:s digits. Using `new Date(iso)` would apply a UTC→local shift and silently move every displayed time by the browser's offset.
- `TimeLog.started_at` **is** a genuine instant (server `Carbon::now()`). Parse with `parseServerInstant()` (plain `new Date`), which is what elapsed-time maths needs.

Send datetimes back with `formatForApi()`.

**The two kinds meet in the report, and that is what `users.timezone` is for.**
A week is a wall-clock idea — Monday 00:00 to Sunday 23:59 where the owner is
standing — so the period boundaries compare to `start_datetime` as they are.
`started_at` is an instant, so the same boundaries have to be read in the
owner's zone and converted to UTC before they can be compared to it:
`shiftTimezone($zone)->utc()` in `ReportController`, which keeps the digits and
then converts. Without it a timer run at 00:30 on Monday in Amsterdam is stored
as 22:30 on Sunday and its minutes land in the week before the one they were
spent in — and at the far edge, a UTC window runs six hours into the next week
and claims Monday morning for the week that ended. `TimezoneTest` holds all
three edges, east and west of UTC.

The column **defaults to `UTC`**, which is exactly the behaviour every install
had before it existed, and it is set under **Settings → Language & time**. It
is on `users` rather than in config because it travels with the person, not
with the install. Nothing else in the app reads it: the calendar is wall-clock
end to end, and the sign-in trail's window is relative.

Weeks are Monday-based everywhere: Carbon's default `startOfWeek()` server-side, `startOfWeek()` in `planning.js`, and `locale: { firstDayOfWeek: 1 }` in the PrimeVue config so the DatePicker agrees.

`addMonths()` **clamps** to the last day of the target month. `setMonth()` alone overflows — 31 January plus a month is 31 February, which JavaScript rolls into March, so a "previous month" step from a 31st would skip February entirely. Both callers normalise to the 1st first, so the clamp changes nothing today; it is there so the next caller need not know to.

## Design system

`resources/js/components/ui/` wraps PrimeVue — installed **unstyled** and pinned to the **MIT-licensed v4 line** (v5 moved to a commercial licence requiring a key) — with this app's look, styled once in the `resources/css/` partials (see below).

Components: `AppButton`, `AppInput`, `AppTextarea`, `AppSelect` (optional `filterable`), `AppMultiSelect`, `AppIconSelect`, `AppCheckbox`, `AppDatePicker`, `AppPillSwitch`, `AppLanguageCards`, `AdminModal`, `PublicModal`, plus `ConfirmDialog` and `ToastStack`.

`AppButton`'s variants come in two families, and they are not interchangeable: **public page** (`primary`/`secondary`/`accent`/`menu`/`menu-gold`/`lang`/`link`) are the uppercase tracked pills the visit card is built from; **workspace** (`solid`/`outline`/`icon`/`icon-danger`) are quieter 13px controls drawn from the surface tokens, so they invert with the theme. A settings row full of tracked capitals reads as shouting.

**There are two modal shells, not one.** `AdminModal` is the workspace's: head, scrolling body, action bar — the same three bands `AdminSheet` has, drawn entirely from the surface tokens, so it follows light and dark. `PublicModal` is the visit card's: the brand palette, one scrolling body, and the heading left to the caller, because the connect form swaps its whole head for the thank-you. One `AppModal` used to serve both, which is why every popup in the workspace was a cream card with sand borders that stayed light when the sheet behind it went dark.

**What they share is behaviour, not looks** — `useModalDialog()` in `shared/modal.js` holds `role="dialog"`, Escape, the Tab trap and returning focus to whatever opened it. It skips an Escape that another component already called `preventDefault()` on, and leaves focus alone when it sits outside the card, because `AppSelect` and `AppDatePicker` overlays are appended to `<body>`. `modal.test.js` runs every one of those tests against **both** shells, so a shell that forgets to call the composable fails rather than passing by association. `ConfirmDialog` draws the `AdminModal` card by hand rather than using the component: it is an `alertdialog` raised from inside these, so it carries its own higher `--z-confirm` layer.

**`AdminModal`'s action bar sits outside the scrolling body**, so a submit button in it reaches its form by `form="<id>"` rather than by being inside the `<form>`. `TaskModal` and `CategoryModal` both do this; forget the attribute and the button silently stops submitting.

**The other shapes that repeat.** `EditableCard.vue` is one item in an ordered collection: its head carries the item's name, its position, the Visible toggle and the reorder/remove buttons, and its body holds the fields. `AppLanguageCards.vue` renders its slot once per language into two side-by-side cards, which replaced the per-field EN/NL pair — a card with four translated fields used to interleave them, so reading one language end to end meant reading every other row. `AppPillSwitch.vue` is the one switcher shape in the workspace (EN/NL, light/dark, default language, show/hide); it takes its colours from `--pill-*` set by whatever contains it rather than a variant prop.

**Unstyled mode means PrimeVue ships no CSS at all** — every class comes from our `pt` map, and anything the default theme would have done for us has to be done by hand. Two consequences that have already bitten:

- PrimeVue's `Checkbox` binds `onChange` to its `<input>` only. `AppCheckbox` gets away with an `sr-only` input because its root is a `<label>` that forwards the click; `AppMultiSelect`'s "select all" header checkbox has no such wrapper, so its input is a transparent full-size overlay (`.field-checkbox-input-overlay`) instead. Option rows keep `sr-only` — the `<li>` carries MultiSelect's own handler, and an overlay there would double-toggle.
- State attributes differ per component: `Checkbox` uses `data-p-checked`, `DatePicker` uses `aria-selected` / a space-separated `data-p` token. Check the rendered DOM rather than assuming.

Styling is Tailwind v4 via `@tailwindcss/vite` (no `tailwind.config.js` — v4 is CSS-first) plus `tailwindcss-primeui`. Colours come from the `@theme` token block; prefer `text-ink`/`bg-cream`/`border-sand` on the public page and the surface tokens in the workspace (see Light and dark) over raw hex. Fonts load through `laravel-vite-plugin/fonts` (Bunny Fonts, not Google Fonts).

### Light and dark

Both halves have both. The visit card was light-only until the brand palette gained dark halves; `LoginPage` is still the exception, below.

**Every value that differs between the two lives in one `light-dark()`** in `theme.css`, not in a `:root` block restated under `[data-theme='dark']` — two lists of the same twenty colours is two lists that drift. Which half applies is decided by `color-scheme`, which `theme.css` sets from a `data-theme` attribute on `<html>`.

**`data-theme` is held, not set.** `shared/theme.js` exposes `holdTheme()`/`releaseTheme()`; `AdminLayout` holds it for the workspace and `PublicPage` for the visit card, both on mount, releasing on unmount. Holders are counted because a route change can mount the next page before the previous one tears down. With no holder the attribute comes off and `color-scheme` returns to `normal`, which resolves every `light-dark()` to its light half — that is what the login page and the first paint before the script runs get. The watcher that applies it is declared at module scope: one created inside a component's setup belongs to that component's effect scope, so the first holder's unmount would kill it while a second was still holding.

**One preference for the whole site**, stored under `site-theme`. It is the same browser and the same pair of eyes; a site that flips theme as you cross from the visit card into the workspace reads as broken. Until someone chooses, both halves follow the OS.

**Workspace rules draw from the surface tokens** — `--color-sheet`, `--color-sunken`, `--color-raised`, `--color-field`, `--color-body`/`--color-strong`/`--color-mute`/`--color-faint`, `--color-hairline`/`--color-ring`, `--color-solid`, `--color-marker`. They are named for the job, not the colour, because the value flips: `sheet` is white in light and near-black in dark, so **a literal `bg-white` or `border-sand` inside `admin.css`, `agenda.css` or `insights.css` is a bug** — it stays light when everything around it does not. **The public page has the same rule now, against the brand palette.** `--color-ink`, `--color-cream`, `--color-sand` and the rest carry both halves, and `ink` and `cream` swap roles — ink is near-black text on cream in light, warm off-white text on near-black in dark. Three consequences worth knowing before editing `public.css`:

- **Anything painted `bg-ink` needs `text-ink-text` on it**, not a literal white, which would vanish once `ink` turns light. `--color-accent-text` exists for the same reason on `bg-accent`.
- **`bg-white/78` and the rest were replaced by `--color-surface-*`** — a card above a dark ground is *lighter* than it, not more opaque, so the alpha could not simply carry over.
- **The hand-tuned washes over the photographs became `--veil-*`** in `base.css`, named by alpha so the light values could not move, and `--photo-dim` lays a dim over the photographs themselves in dark mode. The photographs do not change with the theme, so something has to.

**The contact band stays dark in both**, with a literal `#071523` and `text-white`. It is a dark feature panel over a photograph in the light design; flipping it would put a light slab on a dark page, which is the opposite of what the band is for.

`theming.test.js` enforces the first half of this: every brand colour carries two halves, and `public.css` writes no white or cream by hand.

`forms.css` is shared by both halves and is written against the surface tokens, so a field themes itself wherever it is rendered — including the Select and DatePicker panels, which portal to `<body>` and so sit outside `.admin-shell` but still inside the `:root` that carries `color-scheme`. `.public-modal` in `overlays.css` remaps those tokens onto the brand palette, so the connect form's fields are sand-and-ink rather than the workspace's neutral greys. That remap is itself written in brand tokens now, not literals, so the connect modal follows the theme along with the page it opens over.

`LoginPage` is deliberately excluded — it is the door to the workspace rather than part of it, mounts neither `AdminLayout` nor `PublicPage`, and so never carries the attribute.

**`resources/css/app.css` is only an entry point** — it imports partials split by concern, and import order *is* cascade order:

| File | Holds |
|---|---|
| `theme.css` | `@theme` tokens: brand colours, the workspace surface tokens (both halves, via `light-dark()`), fonts, breakpoints |
| `base.css` | `@layer base`: the `--z-*`, rail-width and page-width scales on `:root`, `html`/`body`, the ambient wash |
| `layout.css` | `.page-grid` track system, `.u-*` span helpers, `.layer-*` stacking utilities |
| `buttons.css` | `.eyebrow` and every button variant |
| `public.css` | the visit card: header, hero, rails, sections, projects, contact |
| `admin.css` | the workspace frame: the rail, the sheet, the shared controls, the toasts |
| `agenda.css` | the planner: day/week/month views, task cards, categories |
| `insights.css` | Insights and Settings: inquiries, the sign-in trail, two-step sign-in, settings rows |
| `forms.css` | the field label bits, `.admin-grid` and the PrimeVue field styling, on the surface tokens so a field themes itself |
| `overlays.css` | the two modal shells — `.admin-modal` on the surface tokens, `.public-modal` on the brand palette — over one shared `.modal-overlay` |
| `responsive.css` | every media query, together so breakpoints stay reviewable |

Four rules when editing:

1. All `@import`s must stay above `@plugin`/`@source` (CSS requires `@import` first, and Lightning CSS enforces it).
2. **Anything competing in the page's root stacking context takes a `--z-*` token** from the scale in `base.css` — `raised`, `section`, `rail`, `header`, `overlay`, `field`, `confirm`, `toast` — used as `z-index: var(--z-x)` in CSS or a `.layer-x` class in a template. Never a bare number at that level. Equal ad-hoc `z-50` values on the toast container and the modal overlay are what once buried error toasts behind the modal scrim, and `field` exists because `AppSelect`/`AppDatePicker` panels append to `<body>`, so inside a modal they are the scrim's sibling rather than its child. A small literal lift *inside* an element's own stacking context — text over its card's decorative pseudo-element, a focused input over its sibling — is a different thing and stays local. `layering.test.js` enforces both halves.
3. **Media queries reference the breakpoint tokens, not numbers** — `@media (width >= theme(--breakpoint-lg))`, and `(width < theme(--breakpoint-md))` for the "below" side. Hand-computed boundaries like `47.9375rem` drift away from the ones Tailwind's own `md:` variants use, which is how a band of widths once fell through to the desktop layout. The one literal left is the ambient-wash threshold in `base.css`, which corresponds to no breakpoint.
4. **Workspace rules use the surface tokens, never the brand palette or a literal.** `bg-sheet` not `bg-white`, `border-hairline` not `border-sand`, `text-body` not `text-ink`. The brand palette has one value; the surface tokens have two, and only the second kind follows the theme. This is the rule that decides whether a new card is still readable in dark mode.

## Not yet built

Drag-and-drop rescheduling of tasks between days (Week/Month) is the one item from the original planner spec that remains unimplemented. `TaskSource::AiChat` is reserved for future AI-assisted task creation but unused.
