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

Three programmes, in the order they have to happen.

**A — squash the migrations and fix the schema in one pass.** Free now and
never again.
**B — settle the shape of the backend.** Before C multiplies every caller.
**C — split into two servers.** Needs item 6 first.

**Not planned for:** no mobile app, so no versioned API, no token auth, no
OpenAPI document, no Resources on the planner endpoints. The second
implementations that *are* coming are mail providers and calendar sync.

## A. One migration per entity, and the schema fixes that ride with it

24 migration files record a private development log and nothing is deployed.
Squash to one `create_` per table — and since every create is being rewritten,
change the schema in the same pass rather than as an alter each.

11. **Fold the four profile alters into the create.** `add_quote_author`,
    `add_language_settings`, `add_headline_highlights`,
    `add_contact_email_and_social_image`.
12. **Fold the rest.** `drop_gear_size` becomes "do not create the column";
    `add_two_factor_columns` moves into the skeleton users table;
    `neutralise_default_initials` is a default, so put `default('AB')` on the
    column; `add_missing_indexes` splits back to the table each index belongs
    to.
13. **Leave vendor and skeleton alone.** `create_permission_tables` is
    Spatie's; `create_cache_table` and `create_jobs_table` are Laravel's.
14. **After it lands:** everyone runs `migrate:fresh --seed`, there is no
    upgrade path and there need not be one, and `CLAUDE.md` notes the cut-off.
    Not `schema:dump` — it pins the repo to one MySQL version and hides the
    schema from review.

**Schema changes.** Two of these move a rule from PHP into the database. Both
were tried against this project's MySQL 8.4 first. A constraint is worth adding
when it defends against *concurrency* — one admin still means double clicks,
retries and second tabs — and not when it defends a state nothing can create.

15. **Make `social_links` a child table.** A repeating group with its own
    fields, ordering and visibility is a table, not a JSON column. Removes
    `withVisibleSocialLinks()` and its clone, and lets rows be validated as
    rows.
16. **Let the database hold "one timer at a time".** Add `user_id` to
    `time_logs`, a stored `running_user_id AS (IF(ended_at IS NULL, user_id,
    NULL))`, and a UNIQUE index on it. Keep `TimerService`'s lock — it turns a
    violation into an orderly pause; the constraint catches the path that
    forgets to lock.
17. **Make `duration_minutes` a generated column.** `TIMESTAMPDIFF(MINUTE,
    started_at, ended_at)` STORED. Removes `TimeLog::booted()` and the trap
    that a builder `update()` silently skips it. Still stored, so totals stay
    one `SUM()`.
18. **Do not add a one-active-profile index.** It works, but nothing in the app
    can create a second profile — the only path is
    `updateOrCreate(['slug' => 'oa'])` — and the index would break
    `test_only_one_profile_stays_active`. The real question is whether
    `is_active` and `activate()` earn their place at all. If multi-profile is
    ever built, the column and the index come back together.
19. **One remote event, one task.** UNIQUE on
    `(user_id, source, external_ref)`, so an overlapping sync or a retry cannot
    import the same event twice. Manual tasks have a NULL `external_ref` and a
    unique index does not compare NULLs, so they are unaffected.
20. **`portfolio_profiles.type` is dead.** Seeded `person`, never read —
    `personSchema()` hardcodes it. Wire it up or drop it.
21. **The seeded slug still names the author.** `seed()` matches on
    `['slug' => 'oa']`; `initials` was neutralised to `AB` and the slug was
    not. Also decide what `slug` is *for* — nothing reads it.
22. **`reflections.period_end` can disagree with itself.** Derivable from
    `period_type` + `period_start`. Make it generated (the unique index uses
    it, so that is the smaller change).
23. **Say what `categories.user_id = NULL` means** in the migration, next to
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

24. **Three kinds of row, three owners.** *Roles are code* — `admin` is a name
    the middleware refers to, so it stays seeded and idempotent. *The admin
    user and the profile are this install's identity* — they move to
    `php artisan app:install`, which asks for email, password and initials.
    *Placeholder content and the demo week are samples* — seeder, local only.

    The command deletes most of `AdminUserSeeder`, whose sixty lines of
    password-refusal exist only because it reads `.env`. Keep
    `DefaultPortfolioContent` as *content* — the reset button needs it. Then
    handle "not set up yet": `activeProfile()` is `firstOrFail()`, so the
    workspace would throw rather than say so.

25. **Mail providers need no work.** `config/mail.php` plus `MAIL_MAILER`
    already switches SMTP, SES, Postmark, Resend. Do not write an interface
    over Laravel's.

26. **Calendar sync is the one interface that earns its place.** Google,
    Microsoft 365 and CalDAV are three implementations of one idea:
    `CalendarProvider` with `pull()` and `push()`, bound in
    `AppServiceProvider::register()`. Providers speak a plain readonly
    `CalendarEvent`; one mapper turns that into a `Task`. `TaskSource` gains a
    `calendar` case.

    **Design before code:** where OAuth tokens live, one-way or two-way, how a
    change is detected without rewriting everything each run, what a remote
    deletion means here, and what happens when both sides changed the same
    event.

27. **No `Task` subclasses.** Eloquent has no single-table inheritance, so
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

28. **The other two interfaces.** `TwoFactorService` → a `TwoFactorProvider`
    contract (TOTP now, passkeys later). `DefaultPortfolioContent` → a contract
    for where the seeded page comes from, so a fork ships its own. Nothing
    else: an interface with one class behind it is a file and an indirection.

29. **Split `PortfolioContentService`.** 273 lines with five reasons to change
    — reading, writing, history, activation, seeding. An interface in front
    would preserve the problem. The one transaction and one write path must
    survive the split.

30. **Put one-off jobs in `app/Actions/`.** Fortify and Jetstream set the
    precedent; prefer it to `lorisleiva/laravel-actions`. First candidates:
    restore a revision, reset to defaults, enrol a second factor, start and
    stop a timer.

31. **Raise events for what something else reacts to.** `PortfolioSaved` /
    `PortfolioRestored` send the page to the public server (C).
    `InquiryReceived` emails you (item 36). `TimerStarted` / `TimerStopped` let
    calendar sync react without `TimerService` growing a branch. Move the
    inline `Login` / `Failed` listeners to `app/Listeners/` when a third
    appears.

32. **Update the "no bindings" note in `CLAUDE.md`** once 26 and 28 land.

## C. Two servers, one repository

- **Box A, private.** Database and workspace, on one origin so the login is
  untouched — same cookie, same CSRF, `same_site` stays `strict`. Blocked at
  the front door by path: `{ADMIN_PATH}/*` reachable only from where you work.
- **Box B, public.** The visit card and nothing else.
- **Save on A** sends the page to B. **The connect form on B** emails you.

Everything flows A → B. B never calls A and holds no key to it, so a break-in
on B reaches a mail credential and a copy of its own public page. A flood on B
does not touch A — unless both boxes share one machine or one connection.

B holds the *published page*: the same words a visitor reads, already filtered
to `is_visible = true`. Not the planner, the users table, the sign-in trail, the
revisions or `ADMIN_PATH`. That is not a cost of pushing — asking would put the
same words in a cache on B.

The win that started this: the workspace's JavaScript stops being served from
the public address. Today anyone can download `AdminPage`'s chunks and
`manifest.json` from the public site and read every endpoint and field name.

33. **One repository, two deployments.** Same repo, `APP_ROLE=workspace` and
    `APP_ROLE=public`, route files registered to match. Repository count is not
    a security boundary — an attacker on B gets what is *installed* there. Two
    repos would put the payload shape and the design tokens in two places, and
    this project already has enough pairs kept in step by hand.
34. **Render the public page on the server.** Item 6, promoted to a
    prerequisite: a visitor's browser must never need to talk to A.
    `publicMeta()`, the `hreflang` alternates and the schema.org block move
    across unchanged.
35. **Send the public payload, and only that.** `payload($profile, publicOnly:
    true)` — the admin payload carries `is_visible = false` rows, and the two
    differ by one argument. Test that a hidden row never reaches B. B checks a
    shared token, or whoever finds the endpoint can replace your portfolio.
36. **The connect form emails you and stores nothing on B.** Keep the form: it
    already has the throttle, the honeypot and the rules, and `contact_email`
    already offers a plain button beside it. A `mailto:` hands your address to
    scrapers; a LinkedIn redirect leaves no record. Rate limiting stays on B,
    where the visitor is. If the Insights archive must keep working, *A* asks
    *B* — so the one-way trust holds.
37. **Each deployment ships only its own half, and proves it.** A deploy check
    that B carries no admin bundle and no admin route file, failing the deploy.
    Two genuinely separate machines. B behind a CDN, because a flood is
    absorbed at the edge or not at all.
38. **Give B a database with one thing in it** — the published payload. The
    full migration set would create an empty `users`, `tasks` and
    `security_events` on a public machine. B needs its own `.env` too.
39. **Split the routes by middleware, not by filename.** `routes/api.php` is
    not "the API file" — it is the *stateless* group: no session, no cookies,
    no CSRF. In Laravel 11+ it is not even installed until `php artisan
    install:api` adds it together with Sanctum, which is the framework saying
    what it is for.

    **The workspace's JSON endpoints stay in `web.php`.** They are a
    same-origin SPA authenticated by the session cookie, so moving them would
    401 everything until Sanctum's stateful middleware put the session back —
    undoing the move. A JSON endpoint in the web group is normal; Breeze and
    Inertia do the same.

    **`routes/api.php` is right for exactly one thing here:** the endpoint on
    B that A pushes to (item 35). Stateless, token-authenticated, no session,
    no CSRF — the api group as intended. Run `install:api` when C starts, not
    before.

    The rest is splitting shell routes from JSON endpoints so each role
    registers its own, which is what item 33 needs. Note that
    `shouldRenderJsonWhen()` in `bootstrap/app.php` stays either way — it
    exists because the session-authenticated JSON endpoints sit outside
    `api/*`, and they still will.
