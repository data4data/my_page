# TODO

What is left to do, worst first. How to do the work is in
[CONTRIBUTING.md](CONTRIBUTING.md).

## Security and the API

1. **Errors are handled three different ways.**
   `ApiError.validationMessage` (`api.js:22`) is never called, and seven
   `catch {}` blocks in `CalendarView`, `CategoriesView` and `ReportView` drop
   the real message for a fixed string. Add one shared handler next to
   `apiFetch` and call it from all of them.

## Code with no tests

2. **`showsIn()` is copied in two places and tested in one.** PHP is covered,
   `shared/portfolio.js` is not, and both files say to keep them matching.

3. **`planning.js` has no tests.** 251 lines holding every planner request.

4. **`theme.js` has no test.** The holder counting in `holdTheme()` decides
   whether the public page stays out of dark mode.

5. **`TaskModal` and `ReportView` have no tests.** 242 and 270 lines.

## Rebuild the public page

6. **The page text is drawn by JavaScript, so the HTML comes back empty.**
   Render the six sections in Blade from the same data `payload()` returns,
   and keep small Vue components for the carousel, the scroll-spy, the
   language toggle and the connect modal. This splits `PublicPage.vue` as a
   side effect.

7. **The public page has no light or dark mode.** That was deliberate while
   the two halves looked different. Decide again.

8. **There is no skip link.** A keyboard user tabs through the whole nav and
   side rail before reaching the content.

## Never built

9. **Tasks cannot be dragged between days** in Week and Month view.

10. **`TaskSource::AiChat` is unused.** Build it or remove the case.

---

# Architecture

Three programmes of work, not single fixes, in the order they have to happen.

**A** costs nothing and gets cheaper the sooner it happens — it is also the
only moment the schema itself is free to change. **B** decides the shape of the
backend. **C** is the big one: it needs that shape settled, and it needs item 6
done first, for a reason C explains.

Each item says what to do and what it breaks. Nothing here is started.

**Settled, so not planned for:** there will be no mobile app. That removes the
versioned API, token authentication, an OpenAPI document and Resources on the
planner endpoints — all of which existed only to serve a third-party consumer.
The second implementations that *are* coming are mail providers and calendar
sync, and B covers both.

## A. One migration per entity, and the schema fixes that ride with it

Nothing is deployed, so the history in `database/migrations` is 24 files
recording a private development log. Squash to one `create_*` per table — and
since every create migration is being rewritten in that pass, make the schema
changes at the same time rather than as an alter each.

11. **Fold the four profile alters into the create.**
    `add_quote_author`, `add_language_settings`, `add_headline_highlights` and
    `add_contact_email_and_social_image` all just add columns to
    `portfolio_profiles`. Move the columns into
    `2026_06_09_000001_create_portfolio_profiles_table` and delete the four.

12. **Fold the rest of the alters.**
    `drop_gear_size_from_expertise_items` becomes "do not create the column".
    `add_two_factor_columns_to_users` moves into the skeleton
    `0001_01_01_000000_create_users_table`.
    `neutralise_default_initials` is a default, not a schema change — move
    `default('AB')` onto the column in the create and delete the file.
    `add_missing_indexes` is the awkward one: it touches several tables, so
    each index goes back to the `create_` of the table it indexes.

13. **Leave two groups alone.**
    `create_permission_tables` is published by `spatie/laravel-permission` and
    should stay as that package wrote it. `create_cache_table` and
    `create_jobs_table` are Laravel's own and carry no project history.

14. **Say so once the squash lands.**
    Everyone with a local database needs `migrate:fresh --seed`; there is no
    upgrade path and there does not need to be one. `RefreshDatabase` means
    the test suite needs nothing. Note the cut-off date in `CLAUDE.md` so a
    later reader does not go looking for the missing history.

    Not `php artisan schema:dump`: it squashes to a MySQL dump, which pins the
    repo to one server version and hides the schema from review. One readable
    `create_` per table is the point.

### The schema changes that ride with the squash

The create migrations are being rewritten anyway and nothing is deployed, so
this is the cheapest these will ever be. Doing them later means an alter
migration each, and the squash was about not having those.

Three of the items below move a rule the application currently holds in PHP
into the database, where it cannot be bypassed by a bug, a console command or
a future second caller. All three were tried against this project's MySQL
(8.4) before being written down.

15. **Make `social_links` a child table.**
    It is a JSON array of `{label, url, icon, in_rail, in_footer}` on the
    profile — a repeating group with its own fields, its own ordering and its
    own visibility. Every other repeating group on the page is already a
    table; this one is a table in a JSON costume.

    Making it `portfolio_social_links` with `sort_order`, `in_rail` and
    `in_footer` as real columns:

    - removes `withVisibleSocialLinks()` and the clone it works on, because
      the `is_visible` filtering `payload()` already applies to the child
      collections would reach it like everything else;
    - lets `UpdatePortfolioRequest` validate a row as a row;
    - lets a link be ordered without rewriting the whole column.

    The `showsIn()` pair in PHP and JS stays either way — the fallback for a
    link saved before the two placements existed is about old data, not about
    where it is stored.

16. **Let the database hold "one timer at a time".**
    `TimerService` prevents two running timers with a lock on the user row,
    and that is the only thing standing between a race and every report total
    being wrong. MySQL can guarantee it outright:

    - add `user_id` to `time_logs` (it reaches the user through `task_id`
      today, so this is a denormalisation, and it is what makes the rest
      possible);
    - add a stored generated column `running_user_id AS (IF(ended_at IS NULL,
      user_id, NULL))`;
    - put a UNIQUE index on it.

    A second open log for the same user is then rejected by the database.
    Closed logs hold NULL, and a unique index does not compare NULLs, so any
    number of finished logs coexist. Verified: the second insert is refused,
    another user's is accepted, and a new one is accepted once the first is
    closed.

    **Keep the lock.** It turns a constraint violation into an orderly pause
    of the other task, which is the behaviour the app wants. The constraint is
    what catches the path that forgets to take the lock.

17. **Make `duration_minutes` a generated column.**
    `TIMESTAMPDIFF(MINUTE, started_at, ended_at)` STORED. It is computed in
    `TimeLog::booted()`'s saving hook today, which is why a builder `update()`
    silently skips it — a trap `CLAUDE.md` has to warn about. As a generated
    column it cannot disagree with the timestamps it is derived from, by any
    path at all, and the hook goes away.

    Still stored, so report totals stay one `SUM()`.

18. **Let the database hold "one active profile".**
    `activate()` keeps exactly one `is_active` row by hand because MySQL has
    no partial unique index. It has something just as good: a stored generated
    column `only_active AS (IF(is_active, 1, NULL))` with a UNIQUE index on
    it. Verified — one active row plus any number of inactive ones is fine,
    and a second active row is refused.

19. **`portfolio_profiles.type` is dead.**
    Seeded `'person'`, never read. `personSchema()` hardcodes `Person`
    instead. Either wire it up — it is the obvious switch for a schema.org
    `Organization` — or drop the column.

20. **The seeded slug still carries the author's initials.**
    `DefaultPortfolioContent::seed()` matches on `['slug' => 'oa']`. The
    `initials` default was neutralised to `AB`; the slug it is looked up by
    was not. It is the one identifier in a fresh install that still names a
    particular person, in a project whose seed rules say the opposite.

    Change it with the squash. While in there, decide what `slug` is *for* —
    nothing reads it, `is_active` is what finds the live profile, and being
    the seeder's idempotency key is the only job it has.

21. **`reflections.period_end` can disagree with itself.**
    It is derivable from `period_type` plus `period_start` — a week's end is
    its start plus six days. Two columns that encode one fact can drift.
    Either make it generated, or drop it and derive it in
    `scopeForPeriod()`. Note the unique index uses it, so a generated column
    is the smaller change.

22. **Write down what `categories.user_id = NULL` means.**
    Null means "shared by everyone", which makes one column carry two kinds
    of row, and it is why `CategoryPolicy` needs its special rule that a
    global category is editable by anyone but deletable only when nobody
    else's subcategory hangs off it.

    It is idiomatic Laravel and probably worth keeping. What is missing is the
    same for depth: one level of nesting is enforced only in PHP, so a
    subcategory of a subcategory is representable in the table. A CHECK cannot
    see another row, so this stays application-side — but it should be said
    out loud in the migration, next to the self-referencing key.

### What to leave alone, and why

Normalising these would make the schema worse, not better. Recorded so the
question is not reopened every six months.

- **The `{en, nl}` JSON columns.** The relational alternative is a
  translations table keyed by model, id, field and locale. For two languages
  and a page that is always read whole, that turns every read into a join and
  a pivot and gives up column types for nothing. Revisit only if a translated
  value ever has to be filtered or sorted in SQL, or if the two languages need
  to be published separately.
- **`portfolio_projects.tags`.** A tags table and a pivot would be more
  normalised, but these are free text typed into one field, never shared
  between projects and never queried. Revisit when something wants "every
  project tagged Laravel".
- **`headline_highlights`.** A short list tied to one string, with no ordering
  that matters and no life of its own.
- **`portfolio_revisions.payload`.** A deliberate snapshot. Storing it whole is
  exactly what makes `restore()` the same code path as `save()`.
- **The four child tables staying four tables.** Folding metrics, expertise,
  projects and process steps into one table with a `type` and a JSON blob
  would be *less* relational, not more — four sets of real columns replaced by
  one bag.
- **`tasks.status` and `tasks.source` as strings.** Already the right call:
  adding a case to a DB enum needs an `ALTER TABLE`, and the enum classes plus
  validation already constrain them.

## B. Interfaces, actions and events

The aim is to swap an implementation without editing its callers. Worth being
blunt about what gets us there and what does not.

23. **Mail providers need no work at all.**
    Laravel already abstracts them. `config/mail.php` lists the mailers and
    `MAIL_MAILER` picks one — SMTP, SES, Postmark, Resend. Switching provider
    is an `.env` change. Writing our own mail interface over the top of
    Laravel's would add a layer and buy nothing.

24. **Calendar sync is the one that genuinely needs an interface.**
    Google Calendar, Microsoft 365 and CalDAV are three real implementations
    of one idea, so `CalendarProvider` earns its keep — `pull()` and `push()`,
    one class per service, bound in `AppServiceProvider::register()`.

    The schema is half ready for it already: `tasks.source` and
    `tasks.external_ref` exist for exactly this, and `TaskSource` gains a
    `calendar` case beside `manual` and `seeder`.

    What is missing and needs designing before any code: where the OAuth
    tokens live (a table per user per provider), whether sync is one way or
    two, and what happens when both sides changed the same event. Sketch that
    first; it is the whole difficulty.

25. **The other two interfaces worth having.**
    - `TwoFactorService` → a `TwoFactorProvider` contract. TOTP now, passkeys
      later.
    - `DefaultPortfolioContent` → a contract for where the seeded page comes
      from, so a fork ships its own without editing ours.

    Nothing else. An interface with one class behind it is a file and an
    indirection, which is why `AppServiceProvider::register()` is empty today.
    Cache, filesystem, mail and queue already have Laravel contracts.

26. **Split `PortfolioContentService` instead of wrapping it.**
    273 lines with five reasons to change: shaping a read (`payload`),
    writing (`save`, `replaceOrdered`), history (`recordRevision`,
    `pruneRevisions`), choosing the live profile (`activate`), and seeding
    (`seedDefaults`). That is the SOLID problem here, and an interface in
    front of it would preserve the problem rather than fix it. The one
    transaction and one write path must survive the split — that property is
    why the class exists.

27. **Put the one-off jobs in `app/Actions/`.**
    Laravel has no first-party Action class, but Fortify and Jetstream both
    use plain invokable classes in `app/Actions/`, so that is the convention
    with precedent. Prefer it to `lorisleiva/laravel-actions`, which is one
    more dependency and blurs controller, job and command into one class.
    First candidates: restoring a revision, resetting to defaults, enrolling
    a second factor, starting and stopping a timer.

28. **Raise events for the things something else reacts to.**
    Laravel 13 discovers listeners automatically, so this costs a class and no
    registration. Three that pay for themselves:

    - `PortfolioSaved` / `PortfolioRestored` — what sends the page to the
      public server (C).
    - `InquiryReceived` — what emails you (item 33).
    - `TimerStarted` / `TimerStopped` — so calendar sync can react later
      without `TimerService` growing a branch for it.

    The `Login` and `Failed` listeners sit inline in
    `AppServiceProvider::boot()`. Move them to `app/Listeners/` when a third
    one appears.

29. **Update the "no bindings" note in `CLAUDE.md`** once 24 and 25 land, with
    the reasoning, rather than leaving the file arguing against the code.

## C. Two servers, one codebase, data pushed one way

### The picture

- **Box A, private.** The database and the workspace. Only you reach it.
- **Box B, public.** The visit card, and nothing else. Anyone reaches it.
- **You press Save on A**, and A sends the new page to B. B keeps its own copy
  and serves that.
- **A visitor sends the connect form on B**, and B emails you.

Everything flows one way, A → B, except the email. B never calls A, holds no
key to A, and A has no door open for B to come through.

### Why push rather than let B ask

**A would have to be switched on for the page to work.** If A is rebooting or
broken, every visitor gets an error. A public page that goes down because a
private machine is restarting is a bad trade.

**A would have to accept connections from B.** With push, A only makes
outgoing calls, so it can refuse every incoming connection except yours.

The cost is that B needs somewhere to keep its copy — a small database, or one
JSON file. That is the cheaper half of the trade.

### What B holds

The *published page*: the headline, summary, metrics, projects, social links
and contact address — the same words a visitor reads, already filtered to
`is_visible = true`. Copying public text onto the public server adds no
exposure.

None of the private data goes near B: not the planner, not the users table
with its password hash and two-factor secret, not the sign-in trail, not the
saved revisions, not `ADMIN_PATH`.

Nor is that a cost of pushing. Any design that serves the page quickly holds a
copy somewhere — asking would put the same words in a cache on B. The question
is never "copy or no copy", it is "a copy of *what*".

### If B is flooded or broken into

**A flood on B does not touch A**, because A holds no open door for B. You keep
working while the portfolio is unreachable, and saves send themselves when B
answers again.

**Unless both boxes share one machine or one connection** — two virtual servers
behind one uplink die together. Separate machines, separate addresses. And put
B behind a CDN, because a flood is absorbed at the edge or not at all.

**A break-in on B reaches nothing**, once the connect form emails rather than
forwards. B holds a mail credential and a copy of its own public page. There is
no token pointing at A to steal.

### Why the workspace screens stay on the same address as the API

A browser may only call the address it was loaded from. Calling a different one
needs permission headers (CORS) and a loosened login cookie. Keeping the
workspace screens and their JSON endpoints on **one address** on Box A means
the login keeps working exactly as it does now — same cookie, same CSRF,
`session.same_site` stays `strict`.

### How the private part is protected

Block at the front door on Box A, by path:

- `{ADMIN_PATH}/*` — reachable only from where you work: IP allowlist, VPN or
  Cloudflare Access.
- Everything else on A — nothing public needs to reach it at all, now that B
  never calls in.

And the win that started this: the workspace's JavaScript stops being served
from the public address. Today anyone can download `AdminPage`'s files and
`manifest.json` from the public site and read every endpoint and field name.
After the split those files are not there.

---

30. **One repository, two deployments.**
    The same repository deployed twice with a different role in `.env` —
    `APP_ROLE=workspace` and `APP_ROLE=public` — and the route files
    registered to match.

    **Repository count is not a security boundary.** An attacker on Box B gets
    what is *installed* on B, not what is in git. Two repositories would make
    the boundary impossible for a build script to get wrong, and that is paid
    for daily: the payload shape and the design tokens would live in two
    places. This project already carries pairs that must be kept in step by
    hand (`showsIn()` in PHP and JS, `TaskStatus` and `TASK_STATUSES`);
    doubling that for one person is what actually rots.

    So: one repository, and make the deployment boundary real and tested
    instead (item 34).

31. **Render the public page on the server.**
    Item 6, promoted to a prerequisite — a visitor's browser must never need
    to talk to Box A. B renders Blade from the copy it holds. `publicMeta()`,
    the `hreflang` alternates and the schema.org block move across unchanged;
    they already read a payload rather than the models.

32. **Send the page across when it is saved — the public payload, and only
    that.**
    `PortfolioSaved` and `PortfolioRestored` (item 28) queue a job that POSTs
    to B. Queued, so a failed send retries instead of losing the edit.

    **It must be `payload($profile, publicOnly: true)`.** The admin payload
    carries rows with `is_visible = false` — content deliberately kept off the
    page — and sending it would put your drafts on a public server. The two
    differ by one argument, which is exactly how this gets got wrong. Add a
    test that pushes a hidden row and asserts B never received it.

    **B must check the push really came from A.** A shared token in both
    `.env` files. Otherwise whoever finds that endpoint can replace your
    portfolio with their own text.

33. **The connect form emails you, and stores nothing on B.**
    This is how you find out somebody wants to reach you, and it is what keeps
    B from holding any key to A.

    Keep the form. It already has three layers against spam — the throttle,
    the honeypot and the validation rules — and `contact_email` already puts a
    plain "get in touch" button beside it for people who prefer their own mail
    client. A `mailto:` on its own hands your address to every scraper and
    throws the spam protection away; a LinkedIn redirect forces everyone onto
    one account and leaves you no record.

    **Rate limiting stays on B**, where the visitor is. That part is unchanged
    and needs no forwarding logic at all.

    **If you want the Insights archive to keep working**, B has to store rows
    and A has to collect them — and then *A* asks *B*, on a schedule or when
    you open the page, so the one-way trust still holds. Decide whether the
    archive is worth that; a mailbox is an archive too.

34. **Each deployment must ship only its own half, and prove it.**
    This is the whole security boundary, and the half that lives outside the
    code. A deploy script that copies everything to both machines undoes it
    silently, without failing a test.

    - A check in the deploy that B carries no admin bundle and no admin route
      file. It should fail the deploy, not warn.
    - Two genuinely separate machines with separate addresses. Same provider
      is fine; same host is not.
    - B behind a CDN.

35. **Give B a database with one thing in it.**
    The published payload. Running the full migration set on B would create an
    empty `users`, `tasks` and `security_events` on a public machine — tables
    nothing fills, that a later bug or a careless seeder could. Give the
    public role its own short migration path.

    B needs its own `.env` too: a different `APP_KEY`, its own database
    credentials, its mail credentials, and none of A's.

36. **Tidy the routes; do not invent an API.**
    The workspace frontend already talks to the backend over JSON —
    `apiFetch` and the `{admin}/...` endpoints are an API, just not spelled
    `/api/`. With no third-party consumer there is nothing to version and no
    contract document to publish.

    Worth doing: split `routes/web.php` so the SPA shell routes and the JSON
    endpoints are in separate files, and register the public role's routes
    separately from the workspace role's. That is what item 30 needs. The rest
    is renaming.
