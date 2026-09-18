# TODO

What is left to do, worst first. How to do the work is in
[CONTRIBUTING.md](CONTRIBUTING.md). Nothing here is started.

## Security and the API

1. **Errors are handled three different ways.** `ApiError.validationMessage`
   (`api.js:22`) is never called, and seven `catch {}` blocks in
   `CalendarView`, `CategoriesView` and `ReportView` drop the real message for
   a fixed string. One shared handler next to `apiFetch`.

## Code with no tests

2. **`showsIn()` is copied in two places and tested in one.** PHP is covered,
   `shared/portfolio.js` is not.
3. **`planning.js` has no tests.** 251 lines holding every planner request.
4. **`theme.js` has no test.** `holdTheme()`'s holder counting decides whether
   the public page stays out of dark mode.
5. **`TaskModal` and `ReportView` have no tests.** 242 and 270 lines.

## Rebuild the public page

6. **The page text is drawn by JavaScript, so the HTML comes back empty.**
   Render the six sections in Blade from the same data `payload()` returns;
   keep Vue for the carousel, scroll-spy, language toggle and connect modal.
   Splits `PublicPage.vue` as a side effect. **Prerequisite for C.**
7. **The public page has no light or dark mode.** Deliberate while the two
   halves looked different. Decide again.
8. **There is no skip link.** A keyboard user tabs through the whole nav and
   side rail first.

## Never built

9. **Tasks cannot be dragged between days** in Week and Month view.
10. **`TaskSource::AiChat` is unused.** Build it or remove the case.

---

# Architecture

## The decision

**One Laravel app, one server. Two front ends inside it, built separately.**

- **Public page** — HTML rendered by Blade, with Vue only where the page needs
  to move: the carousel, the scroll-spy, the language toggle, the connect
  modal.
- **Workspace** — stays the single-page app it is today, behind the session
  login.
- **Two Vite entry points, one `vite.config.js`.** The public page never ships
  the workspace's JavaScript.

Security here comes from the login, not from the number of servers.

### Three things that are easy to mix up

**Blade and Vite are not alternatives.** Blade writes HTML on the server. Vite
is the tool that compiles JavaScript and CSS. Every Laravel + Vue app uses
both. The real question is *who renders the page* — the server, or the browser.

**Two entry points, not two configs.** `vite.config.js` already takes a list:
`input: ['resources/css/app.css', 'resources/js/app.js']`. It becomes two
pairs, one public and one workspace, and `app.blade.php` picks which to load.
One config, one build command, two bundles.

**Inertia is a good tool at the wrong moment.** It replaces the JSON API with
controllers that return Vue pages directly — no `apiFetch`, no loading flags,
no hand-written error handling, and validation errors arrive on their own. It
is what to reach for when *starting* a Laravel + Vue app of this shape, and it
keeps the same session login.

But this workspace already works and has 143 frontend tests behind it. Moving
it to Inertia is a rewrite that changes nothing a user sees. The signal to
reconsider is writing fetch, loading and error code by hand for every new
screen and getting tired of it — item 1 is exactly that pain.

### What is already right

Session auth with an `HttpOnly` cookie and CSRF is the *strongest* option for a
browser app — better than a token in JavaScript's reach. On top of it: opt-in
two-factor, rate limiting per address and per account, a recorded sign-in
trail, a Content-Security-Policy, security headers, a per-install admin prefix,
and no `/login` for scanners to find.

That is a protected admin side already. The work below improves it; it does not
rescue it.

## Do these

Item 6 above belongs here too — rendering the public page in Blade is what
makes the split of front ends worth anything.

11. **Give the workspace its own Vite entry point.** A second pair of entries
    in `vite.config.js` — `public.js`/`public.css` and `admin.js`/`admin.css` —
    and `app.blade.php` chooses which to load, the same way it already chooses
    whether to emit the `admin-path` meta tag.

    The CSS splits along lines that already exist: `theme`, `base`, `layout`,
    `buttons`, `forms` and `overlays` are shared; `public.css` goes only to the
    public bundle; `admin.css`, `agenda.css` and `insights.css` only to the
    workspace one. Both bundles get smaller.

    This closes the one real leak: today anyone can fetch `AdminPage`'s chunk
    from the public site and read endpoint names out of it — `/two-factor`,
    `/security-events`, `/inquiries`. It does **not** leak `ADMIN_PATH`, which
    appears in no built asset, so what leaks is the shape of the API and not
    its location. Worth closing. Not worth a second server.

## A. One migration per entity, and the schema fixes that ride with it

24 migration files record a private development log and nothing is deployed.
Squash to one `create_` per table — and since every create is being rewritten,
change the schema in the same pass rather than as an alter each.

12. **Fold the four profile alters into the create.** `add_quote_author`,
    `add_language_settings`, `add_headline_highlights`,
    `add_contact_email_and_social_image`.
13. **Fold the rest.** `drop_gear_size` becomes "do not create the column";
    `add_two_factor_columns` moves into the skeleton users table;
    `neutralise_default_initials` is a default, so put `default('AB')` on the
    column; `add_missing_indexes` splits back to the table each index belongs
    to.
14. **Leave vendor and skeleton alone.** `create_permission_tables` is
    Spatie's; `create_cache_table` and `create_jobs_table` are Laravel's.
15. **After it lands:** everyone runs `migrate:fresh --seed`, there is no
    upgrade path and there need not be one, and `CLAUDE.md` notes the cut-off.
    Not `schema:dump` — it pins the repo to one MySQL version and hides the
    schema from review.

**Schema changes.** Two of these move a rule from PHP into the database. Both
were tried against this project's MySQL 8.4 first. A constraint is worth adding
when it defends against *concurrency* — one admin still means double clicks,
retries and second tabs — and not when it defends a state nothing can create.

16. **Make `social_links` a child table.** A repeating group with its own
    fields, ordering and visibility is a table, not a JSON column. Removes
    `withVisibleSocialLinks()` and its clone, and lets rows be validated as
    rows.
17. **Let the database hold "one timer at a time".** Add `user_id` to
    `time_logs`, a stored `running_user_id AS (IF(ended_at IS NULL, user_id,
    NULL))`, and a UNIQUE index on it. Keep `TimerService`'s lock — it turns a
    violation into an orderly pause; the constraint catches the path that
    forgets to lock.
18. **Make `duration_minutes` a generated column.** `TIMESTAMPDIFF(MINUTE,
    started_at, ended_at)` STORED. Removes `TimeLog::booted()` and the trap
    that a builder `update()` silently skips it. Still stored, so totals stay
    one `SUM()`.
19. **Do not add a one-active-profile index.** It works, but nothing in the app
    can create a second profile — the only path is
    `updateOrCreate(['slug' => 'oa'])` — and the index would break
    `test_only_one_profile_stays_active`. The real question is whether
    `is_active` and `activate()` earn their place at all. If multi-profile is
    ever built, the column and the index come back together.
20. **One remote event, one task.** UNIQUE on
    `(user_id, source, external_ref)`, so an overlapping sync or a retry cannot
    import the same event twice. Manual tasks have a NULL `external_ref` and a
    unique index does not compare NULLs, so they are unaffected.
21. **`portfolio_profiles.type` is dead.** Seeded `person`, never read —
    `personSchema()` hardcodes it. Wire it up or drop it.
22. **The seeded slug still names the author.** `seed()` matches on
    `['slug' => 'oa']`; `initials` was neutralised to `AB` and the slug was
    not. Also decide what `slug` is *for* — nothing reads it.
23. **`reflections.period_end` can disagree with itself.** Derivable from
    `period_type` + `period_start`. Make it generated (the unique index uses
    it, so that is the smaller change).
24. **Say what `categories.user_id = NULL` means** in the migration, next to
    the self-referencing key — and that one level of nesting is enforced only
    in PHP, because a CHECK cannot see another row.

**Leave denormalised, on purpose.** So the question is not reopened every six
months.

- **`{en, nl}` JSON columns.** A translations table turns every read into a
  join and a pivot, for two languages on a page always read whole. Revisit if a
  translated value must be sorted or filtered in SQL.
- **`projects.tags`.** Free text, never shared, never queried. Revisit when
  something wants "every project tagged Laravel".
- **`headline_highlights`.** A short list tied to one string.
- **`portfolio_revisions.payload`.** The snapshot is what makes `restore()` the
  same code path as `save()`.
- **Four child tables staying four tables.** One table with a `type` and a JSON
  blob would be *less* relational.
- **`tasks.status` and `source` as strings.** A DB enum needs an `ALTER TABLE`
  to gain a case.

## B. Interfaces, actions, and who creates what

25. **Three kinds of row, three owners.** *Roles are code* — `admin` is a name
    the middleware refers to, so it stays seeded and idempotent. *The admin
    user and the profile are this install's identity* — they move to
    `php artisan app:install`, which asks for email, password and initials.
    *Placeholder content and the demo week are samples* — seeder, local only.

    The command deletes most of `AdminUserSeeder`, whose sixty lines of
    password-refusal exist only because it reads `.env`. Keep
    `DefaultPortfolioContent` as *content* — the reset button needs it. Then
    handle "not set up yet": `activeProfile()` is `firstOrFail()`, so the
    workspace would throw rather than say so.

26. **Mail providers need no work.** `config/mail.php` plus `MAIL_MAILER`
    already switches SMTP, SES, Postmark, Resend. Do not write an interface
    over Laravel's.

27. **Calendar sync is the one interface that earns its place.** Google,
    Microsoft 365 and CalDAV are three implementations of one idea:
    `CalendarProvider` with `pull()` and `push()`, bound in
    `AppServiceProvider::register()`. Providers speak a plain readonly
    `CalendarEvent`; one mapper turns that into a `Task`. `TaskSource` gains a
    `calendar` case.

    **Design before code:** where OAuth tokens live, one-way or two-way, how a
    change is detected without rewriting everything each run, what a remote
    deletion means here, and what happens when both sides changed the same
    event.

28. **No `Task` subclasses.** Eloquent has no single-table inheritance, so
    `ManualTask`/`SyncedTask` means `newFromBuilder()` or `tighten/parental`,
    and `$timeLog->task` silently returns the base class wherever it is missed.
    The differences are guard rules — remote owns the schedule, deleting
    unlinks, cannot be created by hand — and `TaskPolicy` and
    `UpdateTaskRequest` are where rules live.

    Put the differences on the `TaskSource` enum instead: `ownsSchedule()`,
    `canBeEditedHere()`, `deletesRemotely()`. One `match` per rule in one file.

    **Revisit on columns, not behaviour.** If synced tasks need a recurrence
    rule, attendees or a meeting link, that is a one-to-one
    `task_calendar_details` table — not a subclass, and not nullable columns
    empty for most rows.

29. **The other two interfaces.** `TwoFactorService` → a `TwoFactorProvider`
    contract (TOTP now, passkeys later). `DefaultPortfolioContent` → a contract
    for where the seeded page comes from, so a fork ships its own. Nothing
    else: an interface with one class behind it is a file and an indirection.

30. **Split `PortfolioContentService`.** 273 lines with five reasons to change
    — reading, writing, history, activation, seeding. An interface in front
    would preserve the problem. The one transaction and one write path must
    survive the split.

31. **Put one-off jobs in `app/Actions/`.** Fortify and Jetstream set the
    precedent; prefer it to `lorisleiva/laravel-actions`. First candidates:
    restore a revision, reset to defaults, enrol a second factor, start and
    stop a timer.

32. **Raise events for what something else reacts to.** `PortfolioSaved` /
    `PortfolioRestored` send the page to the public server (C).
    `InquiryReceived` emails you when someone uses the connect form.
    `TimerStarted` / `TimerStopped` let
    calendar sync react without `TimerService` growing a branch. Move the
    inline `Login` / `Failed` listeners to `app/Listeners/` when a third
    appears.

33. **Update the "no bindings" note in `CLAUDE.md`** once 27 and 29 land.

## C. Two servers — on the shelf

Not on the roadmap. Written down so the thinking is not lost and so the trigger
is recognisable: **real traffic, a compliance requirement, or other people's
data living on the public side.** None is true today, and item 11 gets most of
the benefit for an afternoon.

If it ever happens, the decisions already taken:

- **Box A private** (database + workspace, one origin so the login is
  untouched), **Box B public** (the visit card only). Blocked at the front door
  by path: `{ADMIN_PATH}/*` only from where you work.
- **Push, never pull.** Saving on A sends the page to B. B never calls A and
  holds no key to it, so a break-in on B reaches a copy of its own public page.
  Asking would mean A must be up for the page to work, and must open a door.
- **Send `payload($profile, publicOnly: true)`** — the admin payload carries
  `is_visible = false` rows, and the two differ by one argument.
- **B stores the connect form; A collects it**, with a UUID unique on both
  sides so a repeated pull cannot duplicate it, and a delete from B once
  collected.
- **One repository, two deploy jobs — not two branches.** Deploy B first and
  make B ignore payload keys it does not know. The verification gate moves into
  CI, with a check that B's artifact carries no admin bundle.
- **Let the boxes pull.** CI holding an SSH key into the private box undoes the
  firewall. A manual deploy over the VPN is a fine answer for one person.
- **`routes/api.php` earns its place here and only here** — the stateless,
  token-authenticated endpoint on B that A pushes to. The workspace's own JSON
  endpoints stay in `web.php`, because they are session-authenticated and
  same-origin; moving them would 401 everything until Sanctum put the session
  back.
- Two genuinely separate machines, B behind a CDN, B's database holding only
  the published payload and the pending inquiries.
