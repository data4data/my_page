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

**Read this before any of it.** What follows got long, and the length is worth
explaining: it is the price of one requirement — *two servers* — not accidental
complexity. Two boxes means a push pipeline, a second database, a payload
contract between two versions of the same code, server-rendering as a
prerequisite, an inquiry hand-off, and a deploy that ships two things in order.
None of that is avoidable once the requirement is there. All of it is avoidable
by not having the requirement.

**What the split actually buys, measured rather than assumed.** The public site
serves the workspace's JavaScript. A visitor can fetch `AdminPage`'s chunk and
read endpoint names out of it — `/two-factor`, `/security-events`,
`/inquiries`. It does **not** leak `ADMIN_PATH`: that is in a meta tag served
only inside the workspace, and the prefix appears in no built asset. So what
leaks is the shape of the API, not its location, and knowing the shape gets
nobody past `auth` and `role:admin`.

That is worth fixing. It is not worth two servers.

**So the recommendation is:**

- **Do A, item 6 and item 11.** The schema work is free right now and never
  again; server-rendering fixes a real problem the site has today; and item 11
  gets most of the security benefit of the split for an afternoon's work, on
  one server.
- **Do B when something asks for it.** The install command has a reason
  already. The calendar interfaces have one the day you start that feature.
  The rest can wait for the annoyance that justifies it.
- **Leave C on the shelf.** It is written down so the thinking is not lost, and
  so the trigger is recognisable: real traffic, a compliance requirement, or
  other people's data living on the public side. None of those is true today.

## The cheap version of the split

11. **Give the workspace its own Vite entry point.** One server, no pipeline,
    no second database. A second entry in `vite.config.js` and its own
    manifest, referenced only by the workspace's Blade shell, so the public
    page's HTML never mentions admin JavaScript and the public manifest does
    not list `AdminPage.vue`.

    Pair it with item 6 and the public page stops needing the SPA bundle for
    its content at all. That is the leak above closed, on the setup that
    already exists.

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
    `InquiryReceived` emails you (item 37). `TimerStarted` / `TimerStopped` let
    calendar sync react without `TimerService` growing a branch. Move the
    inline `Login` / `Failed` listeners to `app/Listeners/` when a third
    appears.

33. **Update the "no bindings" note in `CLAUDE.md`** once 27 and 29 land.

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

34. **One repository, two deployments.** Same repo, `APP_ROLE=workspace` and
    `APP_ROLE=public`, route files registered to match. Repository count is not
    a security boundary — an attacker on B gets what is *installed* there. Two
    repos would put the payload shape and the design tokens in two places, and
    this project already has enough pairs kept in step by hand.
35. **Render the public page on the server.** Item 6, promoted to a
    prerequisite: a visitor's browser must never need to talk to A.
    `publicMeta()`, the `hreflang` alternates and the schema.org block move
    across unchanged.
36. **Send the public payload, and only that.** `payload($profile, publicOnly:
    true)` — the admin payload carries `is_visible = false` rows, and the two
    differ by one argument. Test that a hidden row never reaches B. B checks a
    shared token, or whoever finds the endpoint can replace your portfolio.
37. **B validates and stores the connect form; A collects it later.** Keep the
    form — it already has the throttle, the honeypot and the rules, and
    `contact_email` offers a plain button beside it. A `mailto:` hands your
    address to scrapers; a LinkedIn redirect leaves no record.

    `DeveloperInquiryController` and its validation move to B unchanged, and
    rate limiting stays there, where the visitor actually is.

    **A asks B, never the reverse.** A presents a token that B checks. The
    one-way trust still holds: owning B reveals a value B *verifies*, not a
    credential that opens A. Pull on the scheduler and when Insights is
    opened.

    **Give each inquiry a UUID and make it unique on both sides**, so a
    repeated pull or a failed acknowledgement cannot duplicate it — the same
    pattern as item 20. Add the column with the squash (A).

    **Delete from B once A has it.** Somebody else's name, email and message
    should not sit on the public machine after they have been collected. Email
    on arrival too (item 32) — that is how you find out without opening
    Insights.
38. **Each deployment ships only its own half, and proves it.** A deploy check
    that B carries no admin bundle and no admin route file, failing the deploy.
    Two genuinely separate machines. B behind a CDN, because a flood is
    absorbed at the edge or not at all.
39. **Give B a database with one thing in it** — the published payload. The
    full migration set would create an empty `users`, `tasks` and
    `security_events` on a public machine. B needs its own `.env` too.
40. **Split the routes by middleware, not by filename.** `routes/api.php` is
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
    B that A pushes to (item 36). Stateless, token-authenticated, no session,
    no CSRF — the api group as intended. Run `install:api` when C starts, not
    before.

    The rest is splitting shell routes from JSON endpoints so each role
    registers its own, which is what item 34 needs. Note that
    `shouldRenderJsonWhen()` in `bootstrap/app.php` stays either way — it
    exists because the session-authenticated JSON endpoints sit outside
    `api/*`, and they still will.

41. **One branch, two deploy jobs — not two branches.** Long-lived
    `main-public` / `main-admin` branches mean merging twice, cherry-picking
    every shared fix, and a permanent "which branch is on which box" question.
    Keep `main`, and let one pipeline deploy both boxes.

    **The gate has to move into CI.** Pint, PHPStan, `composer test`, `npm
    test` and `npm run build` are run by hand today. Once `main` deploys
    anything, they have to block the merge instead — plus the check from item
    37 that B's artifact carries no admin bundle.

    **Deploy B before A**, and make B ignore payload keys it does not know and
    default the ones it misses. Then a version skew between the two boxes
    renders an older page rather than an error, and the ordering stops being
    load-bearing.

    **Let the boxes pull; do not open a door for the pipeline.** CI should not
    hold an SSH key into A — that is a credential with the run of the private
    machine, sitting in a third party. Either the box fetches and deploys
    itself, or A is deployed by hand over the VPN. For one person, a manual
    deploy of the private box is a defensible answer, not a gap.

    **Back up before every migrate.** `mysqldump` first, `migrate --force`
    second, keep the last few. Deploy into a releases directory behind a
    symlink so a rollback is switching the link, not a restore.

    **No emergency path around the gate.** A hotfix is still branch → gate →
    `main`. Skipping it is a thing people do when they are stressed, which is
    exactly when the gate is worth most.
