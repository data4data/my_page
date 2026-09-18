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

Four programmes of work, not single fixes. They are ordered by what has to
happen first, which is not the order they were asked for.

**A** costs nothing and gets cheaper the sooner it happens. **B** decides the
shape of the backend. **C** is the big one and needs that shape settled, since
it multiplies the number of callers every service has — and it needs item 6
done first, for a reason C explains. **D** is a decision to take before B,
because it changes what B is worth.

Each item says what to do and what it breaks. Nothing here is started.

## A. One migration per entity

Nothing is deployed, so the history in `database/migrations` is 24 files
recording a private development log. Squash to one `create_*` per table.

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

## B. Interfaces, actions and events

The aim is to be able to swap an implementation without editing its callers.
Worth being blunt about what does and does not get us there.

15. **Add interfaces only where a second implementation is named.**
    An interface per service is a file and an indirection that buys nothing
    while there is one class behind it — which is why
    `AppServiceProvider::register()` is empty today. Three candidates that
    genuinely have a second implementation in view:

    - `TwoFactorService` → a `TwoFactorProvider` contract. TOTP now, passkeys
      or WebAuthn are a real possibility, and the whole lifecycle is already
      behind one class.
    - `SecurityEventRecorder` → a sink contract. Rows in MySQL now; an
      external log or SIEM is where this goes if the install gets real
      traffic.
    - `DefaultPortfolioContent` → a contract for "where the seeded page comes
      from", so a fork can ship its own without editing ours.

    Everything infrastructural — cache, filesystem, mail, queue — already has
    a Laravel contract. Use those rather than writing ours over the top.

16. **Split `PortfolioContentService` instead of wrapping it.**
    273 lines with five reasons to change: shaping a read (`payload`),
    writing (`save`, `replaceOrdered`), history (`recordRevision`,
    `pruneRevisions`), choosing the live profile (`activate`), and seeding
    (`seedDefaults`). That is the SOLID problem here, and an interface in
    front of it would preserve the problem rather than fix it. The one
    transaction and one write path must survive the split — that property is
    why the class exists.

17. **Put the one-off jobs in `app/Actions/`.**
    Laravel has no first-party Action class, but Fortify and Jetstream both
    use plain invokable classes in `app/Actions/`, so that is the convention
    with precedent. Prefer it to `lorisleiva/laravel-actions`, which is one
    more dependency and blurs controller, job and command into one class.
    First candidates: restoring a revision, resetting to defaults, enrolling
    a second factor, starting and stopping a timer.

18. **Raise events for the things another consumer will care about.**
    Laravel 13 discovers listeners automatically, so this costs a class and
    no registration. `PortfolioSaved` and `PortfolioRestored` are the ones
    that pay: once the public frontend is deployed separately (C) they are
    what purges its cache or triggers its rebuild. `InquiryReceived` is where
    a notification belongs. `TimerStarted` / `TimerStopped` keep
    `TimerService` from growing every time something new wants to know.

    The `Login` and `Failed` listeners currently sit inline in
    `AppServiceProvider::boot()`. Move them to `app/Listeners/` as soon as a
    third one appears.

19. **Reverse the "no bindings" note in `CLAUDE.md`** once 15 lands, with the
    reasoning, rather than leaving the file arguing against the code.

## C. Two boxes, one API

The workspace and the backend ship together — one repo, one server, one
origin — and talk over `/api/v1` like any other client. The public visit card
is its own deployment on its own server, and gets everything it draws by
calling that API.

**Why this is the right way round.** Keeping the admin frontend on the same
origin as the API means the browser never makes a cross-origin request, so
there is no CORS to configure, no Sanctum SPA cookie mode to set up, and
`session.same_site` stays `strict` — three current decisions that a split onto
separate hostnames would have forced us to undo for nothing. The security win
is unchanged: the public host serves only public code, and `AdminPage`'s
chunks and `manifest.json` stop being readable by anyone who asks the public
origin for them, which is how every endpoint and field name is discoverable
today.

**The condition the whole shape rests on.** The public page must fetch
**server to server**. If the visitor's browser calls the workspace API, then
the workspace has to be reachable from every network on earth and the lockdown
is gone before it starts. So this makes item 6 — render the page server-side —
a prerequisite rather than an improvement. That is a fair trade: it was wanted
anyway, for the crawlers.

20. **Render the public page from data the public server fetched.**
    Item 6, promoted. The public box asks the workspace for the payload,
    caches it, and renders Blade from it. `publicMeta()`, the `hreflang`
    alternates and the schema.org block move across as they are — they already
    read a payload rather than the models.

    **Cache it hard and serve stale on failure.** Otherwise the workspace
    being off takes the visit card down with it, and a portfolio page that
    404s because a private admin box is rebooting is a bad trade for anyone.

21. **Give the backend a real API surface.**
    `bootstrap/app.php` registers no `api` routes at all today. Add
    `routes/api.php` under `/api/v1`, split into a group the public server may
    read and a group only the owner may touch. Version it from the first
    commit — a second consumer cannot pin to an unversioned URL.

22. **Two kinds of caller, two kinds of auth.**
    - **The admin frontend** keeps the session cookie it has. Same origin,
      `HttpOnly`, `same_site: strict`, CSRF as today. Nothing changes.
    - **The public server** gets a `laravel/sanctum` token with read scope,
      sent server to server. No cookies, no CSRF, no CORS, and the token lives
      in the public box's `.env` rather than in anyone's browser.

23. **Lock down by path, not by host.**
    One origin means the edge rules go on paths: allow `/api/v1/*` from the
    public server's address (and the owner's, for a phone later), and allow
    `{ADMIN_PATH}/*` only from where the owner actually works — IP allowlist,
    VPN or Cloudflare Access. That is worth more than the unguessable prefix
    ever was, and the prefix keeps its job on top.

    The moment the admin frontend moves to its own hostname, CORS and Sanctum
    SPA mode come back. Do not, unless something forces it.

24. **The connect form is the exception, and the trap.**
    It is a write from an anonymous visitor, so the public box has to accept
    the POST and forward it — a second token scope, `inquiries:create` and
    nothing else.

    **The visitor's address has to travel with it.** `throttle:10,1` and the
    honeypot both key on the caller, and after forwarding the caller is the
    public server. Left alone, ten submissions lock out every visitor at once
    and the inquiry rows all record one address. Either rate-limit on the
    public box before forwarding, or forward the address and configure trusted
    proxies to believe it. Decide which; do not do half of each.

25. **Consider pushing instead of pulling.**
    The alternative to 20: on `PortfolioSaved` / `PortfolioRestored` (item 18)
    the workspace *sends* the payload to the public box, which then reads only
    local data and has no API client at all. The workspace can be fully
    offline and the visit card does not notice.

    Worth real thought, because this content changes about monthly. The cost
    is a delivery mechanism and a shared secret; the gain is that the public
    page stops depending on a private box being up.

26. **One repo, two deploy targets.**
    Two repos for one person is two repos that drift, and the payload shape is
    the one thing both halves must agree on. Keep `apps/api`,
    `apps/admin-web` and `apps/public-web` in this repo with npm workspaces,
    and put the shared frontend pieces — `api.js`, `i18n`, `ui/`,
    `vue-plugin.js` — in `packages/shared` so neither app forks them.

    **The deploy for each host must ship only its own app.** That discipline
    is the whole security boundary; a build script that copies everything
    undoes the split without failing anything.

27. **Write the contract down.**
    Two consumers against one API drift unless something holds them together —
    three if a phone arrives. Generate an OpenAPI document from the routes and
    Resources, and add a test that fails when a route exists the document does
    not describe.

28. **Give the planner endpoints Resources too.**
    `TaskController` and `CategoryController` still return models. That was
    fine while the only reader was the owner's own browser behind a session.
    A token-authenticated API is a different promise — the same argument that
    produced `app/Http/Resources/` for the portfolio.

## D. Decide before B

29. **Is a mobile app real, or is it a maybe?**
    It changes one thing that is hard to undo later: a phone dials in from
    whatever network it is on, so `/api/v1` has to stay open to the internet
    and cannot sit behind the allowlist in item 23. Everything else it touches
    — versioning (21), Resources on the planner (28), the OpenAPI document
    (27) — is cheap if the answer is yes and speculative if it is no.

    The split itself stands on its own security argument and is worth doing
    either way. Answer this before B, so the rest is not built for a consumer
    that never arrives.
