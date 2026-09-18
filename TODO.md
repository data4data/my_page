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

## C. Two servers, one codebase, data pushed one way

### The picture

- **Box A, private.** The database, the API, and the workspace screens. Only
  you can reach it.
- **Box B, public.** The visit card, and nothing else. Anyone can reach it.
- **When you press Save on A**, A sends the new page content to B. B keeps its
  own copy and serves that.
- **When a visitor sends the connect form on B**, B keeps it and passes it on
  to A.

Data moves because something was *saved*, never because someone *visited*.
That single rule is what makes the rest of this simple.

### Why push, and not let B ask A

The obvious design is for B to ask A for the text each time someone visits.
It has two problems, and both are avoided by sending the data instead.

**A would have to be switched on for the page to work.** If A is rebooting,
being updated, or broken, every visitor to the portfolio gets an error. A
public page that goes down because a private machine is restarting is a bad
trade.

**A would have to accept connections from B.** That means opening a door in
A's firewall. With push, A only ever makes *outgoing* calls to B, so A can
refuse every incoming connection except yours. Nothing on the public internet
can knock on A's door at all.

The cost is that B needs somewhere to keep its copy — a small database, or one
JSON file. That is the cheaper half of the trade.

### Why the workspace screens stay on the same address as the API

A browser is only allowed to call the address it was loaded from. Ask it to
call a different address and the browser blocks it unless the server adds
permission headers (this is called CORS), and the login cookie stops being
sent unless it is loosened too.

So keeping the workspace screens and the API on **one address** on Box A means
the login keeps working exactly as it does today — same cookie, same CSRF
protection, `session.same_site` stays `strict`. Giving the screens their own
address would mean undoing all three for no gain.

### Then how is the private part actually protected?

By blocking at the front door, on Box A, and by *path* rather than by address:

- `{ADMIN_PATH}/*` — the workspace screens. Reachable only from where you
  work: an IP allowlist, a VPN, or Cloudflare Access.
- `/api/v1/*` — reachable from Box B's address, and from yours.

You cannot block Box A as a whole, because the API on it is the thing B needs.
Hence: per path, not per machine.

And the win that started this: the workspace's JavaScript stops being served
from the public address. Today anyone can download `AdminPage`'s files and
`manifest.json` from the public site and read every endpoint and field name
out of them. After the split those files do not exist there.

### What an attack on the public box can actually reach

The worry is fair: if Box B is flooded or broken into, does Box A go with it?
With data pushed rather than asked for, mostly no — but only if three things
are true.

**A flood on B does not touch A.** A holds no open door for B; every call goes
A → B, outwards. So B being hammered does not slow A down, and you can keep
working in the workspace while the portfolio is unreachable. Saves queue up and
send themselves when B answers again.

**...unless both boxes share one machine or one connection.** Two virtual
servers on the same host, behind the same uplink, both die when that uplink is
saturated. "Two servers" only buys anything if they are genuinely separate —
different machines, different addresses, ideally different providers. This is
the part that is easy to get wrong while thinking the split is done.

**A break-in on B reaches exactly as far as B's token.** B never needs to read
anything from A, because A sends. So the only credential B holds is the one it
uses to pass connect-form messages back — and that must be scoped to *create
an inquiry*, nothing else. Then owning B gets an attacker spam in the
inquiries table and no way at all into the planner, the editor, or the login.
Had B been *asking* A for the page, it would also hold a read token and A
would have a hole in its firewall for B to come through.

---

20. **One repository, two deployments.**
    The same repository deployed twice with a different role in `.env` —
    `APP_ROLE=workspace` and `APP_ROLE=public` — and the route files
    registered to match.

    **Repository count is not a security boundary.** An attacker on Box B gets
    what is *installed* on Box B, not what is in git. Two repositories would
    make the boundary impossible for a build script to get wrong, which is
    their one real advantage — and it is paid for daily, because the payload
    shape and the design tokens then live in two places. This project already
    carries several pairs that must be kept in step by hand (`showsIn()` in PHP
    and in JS, `TaskStatus` and `TASK_STATUSES`); doubling that for one person
    is the thing that actually rots.

    So: one repository, and make the deployment boundary real and tested
    instead (item 26).

21. **Render the public page on the server.**
    Item 6, promoted to a prerequisite. Box B renders Blade from the copy it
    holds, so a visitor's browser never talks to Box A at all. `publicMeta()`,
    the `hreflang` alternates and the schema.org block move across unchanged —
    they already read a payload rather than the models.

22. **Give the backend a real API surface.**
    `bootstrap/app.php` registers no `api` routes today. Add `routes/api.php`
    under `/api/v1`. Version it from the first commit — a second consumer
    cannot pin to an unversioned URL.

23. **Send the page across when it is saved.**
    `PortfolioSaved` and `PortfolioRestored` (item 18) queue a job that POSTs
    the payload to Box B. Queued, so a failed send retries instead of losing
    the edit; Laravel gives the retries for nothing.

    Both ends hold a shared token in `.env` and B rejects anything that does
    not carry it. B stores what arrives and serves it until the next one.

24. **Pass the connect form back the same way.**
    A visitor posts to Box B. B saves it locally, then queues a send to A.
    If A is off, it waits and retries rather than losing the message.

    **Rate limiting has to happen on B**, before the send. `throttle:10,1` and
    the honeypot both key on whoever is calling. After forwarding, the caller
    is Box B — so left alone, ten submissions would lock out every visitor at
    once and every inquiry row would record the same address.

25. **Split the stylesheets, do not copy them.**
    `packages/shared` holds the tokens and the pieces both halves use:
    `theme.css`, `base.css`, `layout.css`, `buttons.css`, `forms.css`, the
    `ui/` components, `i18n`, `api.js`, `vue-plugin.js`. `public.css` builds
    only into B; `admin.css`, `agenda.css` and `insights.css` only into A.
    Both bundles get smaller as a side effect.

    `overlays.css` already separates cleanly — `.public-modal` to B,
    `.admin-modal` to A.

    **Copying any of it is the failure mode.** Two copies of `theme.css` is
    two palettes, and they will not stay the same colour.

26. **Each deployment must ship only its own half, and prove it.**
    This is the whole security boundary, and it is the half of the split that
    lives outside the code. A build or deploy script that copies everything to
    both machines undoes it silently, without failing a single test.

    - A check in the deploy that Box B carries no admin bundle and no admin
      route file. It should fail the deploy, not warn.
    - Two genuinely separate machines with separate addresses, per the note
      above. Same provider is acceptable; same host is not.
    - Box B behind a CDN. A flood is absorbed at the edge or not at all — no
      amount of application code on B helps once the connection is full.
    - B's token scoped to creating an inquiry and nothing else, and different
      from anything A uses elsewhere.

27. **Write the contract down.**
    Two halves against one payload shape drift unless something holds them
    together. Generate an OpenAPI document from the routes and Resources, and
    add a test that fails when a route exists the document does not describe.

28. **Give the planner endpoints Resources too.**
    `TaskController` and `CategoryController` still return models. That was
    fine while the only reader was the owner's own browser behind a session.
    A token-authenticated API is a different promise — the same argument that
    produced `app/Http/Resources/` for the portfolio.

## D. Decide before B

29. **Is a mobile app real, or is it a maybe?**
    One thing about it is hard to undo later. A phone connects from whatever
    network it happens to be on — home, office, a cafe — and its address is
    different every time. So there is no list of allowed addresses that would
    let the phone in and keep everyone else out, and `/api/v1` on Box A would
    have to stay open to the whole internet, protected by its token alone.

    That is a real weakening of the shape above, and worth deciding on purpose
    rather than discovering. Everything else a phone touches — versioning
    (22), Resources on the planner (28), the OpenAPI document (27) — is cheap
    if the answer is yes and speculative if it is no.

    The split itself stands on its own and is worth doing either way.
