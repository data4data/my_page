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
   Splits `PublicPage.vue` as a side effect. **Worth doing? See item 11.**
7. **The public page has no light or dark mode.** Deliberate while the two
   halves looked different. Decide again.
8. **There is no skip link.** A keyboard user tabs through the whole nav and
   side rail first.

## Never built

9. **Tasks cannot be dragged between days** in Week and Month view.
10. **`TaskSource::AiChat` is unused.** Build it or remove the case.

## The architecture, decided

One Laravel app, one server. Two front ends inside it, built separately.

- **Public page** — HTML rendered by Blade, with Vue only where the page moves:
  the carousel, the scroll-spy, the language toggle, the connect modal.
- **Workspace** — stays the single-page app it is, behind the session login.
- **Two Vite entry points, one `vite.config.js`.** The public page never ships
  the workspace's JavaScript.

Security here comes from the login, not from splitting servers. Session auth
with an `HttpOnly` cookie and CSRF is the strongest option for a browser app —
better than a token in JavaScript's reach — and it is already in place, with
opt-in two-factor, rate limiting per address and per account, a recorded
sign-in trail, a CSP, security headers, a per-install admin prefix, and no
`/login` for scanners to find.

**Not doing:** separate servers for the two halves, a versioned API, token
authentication, an OpenAPI document. Splitting the bundles got what the split
was for.

**Inertia** replaces the JSON API with controllers that return Vue pages — no
`apiFetch`, no loading flags, no hand-written error handling. It is what to
choose when *starting* an app of this shape. This workspace already works and
has tests behind it, so moving it now is a rewrite that changes nothing a user
sees. The signal to reconsider is item 1 becoming a chore on every new screen.

## Do this next — but decide item 11 first

11. **Decide whether the public page's text has to be in the HTML.** Item 6
    above is the work; this is whether it is worth doing, and the honest answer
    is "it depends on one thing you have to answer".

    **Measured, so the trade is not guessed at.** A request for `/` returns
    2,387 bytes of head — description, Open Graph, Twitter card, the
    schema.org block — and a body of exactly `<div id="app"></div>`. So:

    - **Link previews already work.** LinkedIn, WhatsApp, Slack and iMessage
      read the head, and the head is server-rendered. Nothing to fix.
    - **Search engines get an empty body.** Google runs JavaScript and will
      index it, but on a second pass and less reliably. Bing, DuckDuckGo and
      the AI crawlers are worse at it.
    - **It saves almost no JavaScript.** The public bundle is 270 KB, of which
      `PublicPage.vue` is 13 KB. The other 243 KB is Vue, vue-router and
      PrimeVue — and the connect modal keeps all of it, because `AppInput`,
      `AppTextarea` and `AppButton` wrap PrimeVue components.

    **So the only thing it buys is being *found* by a search engine**, rather
    than being *shared* by a link. If the page is reached from a CV, an email
    or a LinkedIn profile, this is already done and item 6 should be dropped.

    **And the cost is real:** Blade and Vue would both know how to draw a
    project card, and two renderers for one thing drift.

    If the answer is that search matters, the alternative worth weighing is
    **Inertia with server-side rendering** — one set of Vue components,
    rendered to HTML by a Node process next to PHP. It keeps a single
    renderer, which is the thing item 6 gives up, at the cost of a second
    process to run and deploy.

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
    `is_active` and `activate()` earn their place at all.
20. **One remote event, one task.** UNIQUE on
    `(user_id, source, external_ref)`, so an overlapping calendar sync or a
    retry cannot import the same event twice. Manual tasks have a NULL
    `external_ref` and a unique index does not compare NULLs.
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
- **`projects.tags`.** Free text, never shared, never queried.
- **`headline_highlights`.** A short list tied to one string.
- **`portfolio_revisions.payload`.** The snapshot is what makes `restore()` the
  same code path as `save()`.
- **Four child tables staying four tables.** One table with a `type` and a JSON
  blob would be *less* relational.
- **`tasks.status` and `source` as strings.** A DB enum needs an `ALTER TABLE`
  to gain a case.

## B. When something asks for it

25. **Three kinds of row, three owners.** *Roles are code* — `admin` is a name
    the middleware refers to, so it stays seeded and idempotent. *The admin
    user and the profile are this install's identity* — they move to
    `php artisan app:install`, which asks for email, password and initials.
    *Placeholder content and the demo week are samples* — seeder, local only.

    The command deletes most of `AdminUserSeeder`, whose sixty lines of
    password-refusal exist only because it reads `.env`. Keep
    `DefaultPortfolioContent` as *content* — the reset button needs it. Then
    handle "not set up yet": `activeProfile()` is `firstOrFail()`, so the
    workspace would throw rather than say so. **Has a reason already — item
    24.**

26. **Mail providers need no work.** `config/mail.php` plus `MAIL_MAILER`
    already switches SMTP, SES, Postmark, Resend. Do not write an interface
    over Laravel's.

27. **Calendar sync is the one interface that earns its place.** Google,
    Microsoft 365 and CalDAV are three implementations of one idea:
    `CalendarProvider` with `pull()` and `push()`. Providers speak a plain
    readonly `CalendarEvent`; one mapper turns that into a `Task`. `TaskSource`
    gains a `calendar` case.

    **Design before code:** where OAuth tokens live, one-way or two-way, how a
    change is detected without rewriting everything each run, what a remote
    deletion means here, and what happens when both sides changed the same
    event.

28. **No `Task` subclasses.** Eloquent has no single-table inheritance, so
    `ManualTask`/`SyncedTask` means `newFromBuilder()` or `tighten/parental`,
    and `$timeLog->task` silently returns the base class wherever it is missed.
    The differences are guard rules — remote owns the schedule, deleting
    unlinks, cannot be created by hand — and `TaskPolicy` and
    `UpdateTaskRequest` are where rules live. Put them on the `TaskSource`
    enum: `ownsSchedule()`, `canBeEditedHere()`, `deletesRemotely()`.

    **Revisit on columns, not behaviour.** If synced tasks need a recurrence
    rule, attendees or a meeting link, that is a one-to-one
    `task_calendar_details` table.

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
    `PortfolioRestored`, `InquiryReceived` to email you when the connect form
    is used, `TimerStarted` / `TimerStopped` so calendar sync can react without
    `TimerService` growing a branch. Move the inline `Login` / `Failed`
    listeners to `app/Listeners/` when a third appears.

33. **Update the "no bindings" note in `CLAUDE.md`** once 27 and 29 land.
