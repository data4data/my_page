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
it multiplies the number of callers every service has. **D** is a decision to
take before B, because it changes what B is worth.

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

## C. Split into three deployables

One backend, two frontends, hosted separately: the public visit card on one
server, the workspace on another, neither serving the other's code.

**The security this actually buys.** The admin URL prefix is unguessable, but
the JavaScript that calls it is not hidden — `AdminPage`'s chunks and
`manifest.json` are served from the public origin today, and anyone can read
every endpoint and field name out of them. Splitting means the admin bundle
does not exist on the public host at all. That is the win. Put the admin host
behind an IP allowlist, a VPN or Cloudflare Access and it stops being reachable
at all, which is worth more than the prefix ever was.

20. **Decide how far the public half moves first. Everything else depends on
    it.**
    The public page's server-rendered meta — title, description, canonical,
    `hreflang`, Open Graph, the schema.org block — lives in Blade, and it is
    the only reason link previews and crawlers see anything at all. A static
    bundle on another server has none of it.

    Recommended: the public half **stays a Laravel deployable** on its own
    vhost, serving only the public routes, and only the workspace becomes a
    standalone frontend against the API. That gets the whole security win for
    a fraction of the work. Revisit if item 6 (render the page in Blade) lands
    first, which would settle it the other way.

21. **Give the backend a real API surface.**
    `bootstrap/app.php` registers no `api` routes at all today. Add
    `routes/api.php` under `/api/v1`, split into a public group and an admin
    group. Version it from the first commit — a mobile app cannot pin to an
    unversioned URL.

22. **Move the workspace endpoints off the web session.**
    Add `laravel/sanctum`. Use it two ways, on purpose:

    - **Browser frontends: SPA cookie mode.** The session cookie stays
      `HttpOnly`, so a script cannot read it. This needs both hosts to be
      subdomains of one registrable domain, `SANCTUM_STATEFUL_DOMAINS` set,
      and `/sanctum/csrf-cookie` called before the first write.
    - **A future mobile app: personal access tokens.** A token in a phone's
      keychain is fine; a token in `localStorage` is not, which is why the
      browser apps do not use them. An XSS that could steal it is the same
      XSS `SecurityHeaders` exists to stop, and we should not hand it a
      second prize.

23. **Three config changes the split forces, each undoing a current
    decision.**
    - `config/session.php` — `same_site` must go from `strict` to `lax`. It
      is `strict` today precisely because nothing was meant to reach this app
      from another origin. Update the reasoning in `CLAUDE.md`; do not just
      change the value.
    - `config/cors.php` — not published yet. `php artisan config:publish
      cors`, then allow exactly the two frontend origins with
      `supports_credentials: true`. Not `*`.
    - `SecurityHeaders` — `connect-src` is `'self'` only, so every call to the
      API origin would be blocked. `SecurityHeadersTest` parses the policy and
      will fail on this, which is the test doing its job.

24. **Give the planner endpoints Resources too.**
    `TaskController` and `CategoryController` still return models. That was
    fine while the only reader was the owner's own browser. A published API
    with a mobile client is a different promise — the same argument that
    produced `app/Http/Resources/` for the portfolio.

25. **One frontend repo, three builds.**
    npm workspaces: `apps/public`, `apps/admin`, `packages/shared`. `api.js`,
    `i18n`, the `ui/` components and `vue-plugin.js` are shared by both apps
    and must not be forked into two copies. Three `package.json` files, three
    Vite builds, one place each shared thing lives.

26. **Write the contract down.**
    Three consumers against one backend drift unless something holds them
    together. Generate an OpenAPI document from the routes and Resources, and
    add a test that fails when a route exists that the document does not
    describe.

27. **`ADMIN_PATH` needs a decision once the workspace is its own host.**
    An unguessable prefix on an admin-only host is belt and braces; on the
    API it still has a job. Keep it or drop it deliberately, and write down
    which — it is load-bearing in `routes/web.php`, `phpunit.xml`,
    `admin-path.js` and the `app.blade.php` meta tag.

## D. Decide before B

28. **Is a mobile app real, or is it a maybe?**
    Several items above are cheap if the answer is yes and speculative if it
    is no — the versioned API (21), token auth (22), Resources on the planner
    (24) and the OpenAPI contract (26). The split itself (C) stands on its own
    security argument and is worth doing either way. Answer this first so the
    rest is not built for a consumer that never arrives.
