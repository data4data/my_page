# TODO

What is left to do, worst first. How to do the work is in
[CONTRIBUTING.md](CONTRIBUTING.md). Nothing here is started.

## Security and the API

1. **Errors are handled three different ways.** `ApiError.validationMessage`
   (`api.js:22`) is never called, and seven `catch {}` blocks in
   `CalendarView`, `CategoriesView` and `ReportView` drop the real message for
   a fixed string. One shared handler next to `apiFetch`.

## Code with no tests

2. **`showsIn()` has no test.** The PHP half is gone with the JSON column;
   what is left in `shared/portfolio.js` guards a half-built editor object.
3. **`planning.js` has no tests.** 251 lines holding every planner request.
4. **`theme.js` has no test.** `holdTheme()`'s holder counting decides whether
   the public page stays out of dark mode.
5. **`TaskModal` and `ReportView` have no tests.** 242 and 270 lines.

## Accessibility

6. **There is no skip link.** A keyboard user tabs through the whole nav and
   side rail first.

## Never built

7. **Tasks cannot be dragged between days** in Week and Month view.
8. **`TaskSource::AiChat` is unused.** Build it or remove the case.

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

## Decided, not doing

**The public page stays drawn by JavaScript.** A request for `/` returns a full
`<head>` — 2,387 bytes of title, description, Open Graph, Twitter card and
schema.org — and an empty body. That is enough for the link previews a
portfolio is actually reached through, and Google indexes JavaScript pages
anyway. Rendering the six sections in Blade would buy only being *found* by a
search engine rather than *sent* to; it would cost two files that both know how
to draw a project card, and it would save 13 KB of a 252 KB bundle, because the
connect form keeps Vue and PrimeVue either way.

If search ever matters, the cheap version is to put the hero — role, headline,
summary — inside `#app` in Blade. Vue wipes `#app` when it mounts, so a browser
never sees it twice and a crawler reads the part that matters. Full server
rendering is Inertia with SSR, and a Node process beside PHP.

## B. Interfaces, actions, and who creates what

9. **Mail providers need no work.** `config/mail.php` plus `MAIL_MAILER`
    already switches SMTP, SES, Postmark, Resend. Do not write an interface
    over Laravel's.

10. **Calendar sync is the one interface that earns its place.** Google,
    Microsoft 365 and CalDAV are three implementations of one idea:
    `CalendarProvider` with `pull()` and `push()`. Providers speak a plain
    readonly `CalendarEvent`; one mapper turns that into a `Task`. `TaskSource`
    gains a `calendar` case.

    **Design before code:** where OAuth tokens live, one-way or two-way, how a
    change is detected without rewriting everything each run, what a remote
    deletion means here, and what happens when both sides changed the same
    event.

11. **No `Task` subclasses.** Eloquent has no single-table inheritance, so
    `ManualTask`/`SyncedTask` means `newFromBuilder()` or `tighten/parental`,
    and `$timeLog->task` silently returns the base class wherever it is missed.
    The differences are guard rules — remote owns the schedule, deleting
    unlinks, cannot be created by hand — and `TaskPolicy` and
    `UpdateTaskRequest` are where rules live. Put them on the `TaskSource`
    enum: `ownsSchedule()`, `canBeEditedHere()`, `deletesRemotely()`.

    **Revisit on columns, not behaviour.** If synced tasks need a recurrence
    rule, attendees or a meeting link, that is a one-to-one
    `task_calendar_details` table.

12. **The other two interfaces.** `TwoFactorService` → a `TwoFactorProvider`
    contract (TOTP now, passkeys later). `DefaultPortfolioContent` → a contract
    for where the seeded page comes from, so a fork ships its own. Nothing
    else: an interface with one class behind it is a file and an indirection.

13. **Split `PortfolioContentService`.** 273 lines with five reasons to change
    — reading, writing, history, activation, seeding. An interface in front
    would preserve the problem. The one transaction and one write path must
    survive the split.

14. **Put one-off jobs in `app/Actions/`.** Fortify and Jetstream set the
    precedent; prefer it to `lorisleiva/laravel-actions`. First candidates:
    restore a revision, reset to defaults, enrol a second factor, start and
    stop a timer.

15. **Raise events for what something else reacts to.** `PortfolioSaved` /
    `PortfolioRestored`, `InquiryReceived` to email you when the connect form
    is used, `TimerStarted` / `TimerStopped` so calendar sync can react without
    `TimerService` growing a branch. Move the inline `Login` / `Failed`
    listeners to `app/Listeners/` when a third appears.

16. **Update the "no bindings" note in `CLAUDE.md`** once 10 and 12 land.
