# TODO

What is left to do, worst first. How to do the work is in
[CONTRIBUTING.md](CONTRIBUTING.md); decisions already taken are in
[CLAUDE.md](CLAUDE.md), not here. Nothing here is started.

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

## Interfaces, actions and events

9. **Calendar sync.** Google, Microsoft 365 and CalDAV are three
   implementations of one idea, so `CalendarProvider` with `pull()` and
   `push()` is the one interface that earns its place. Providers speak a plain
   readonly `CalendarEvent`; one mapper turns that into a `Task`. `TaskSource`
   gains a `calendar` case, and the rules that differ go on that enum —
   `ownsSchedule()`, `canBeEditedHere()`, `deletesRemotely()`.

   The schema is ready: `tasks.source`, `tasks.external_ref`, and a UNIQUE
   index across `(user_id, source, external_ref)` so a retried sync cannot
   import one event twice.

   **Design before code.** Where the OAuth tokens live, one way or two, how a
   change is detected without rewriting everything each run, what a remote
   deletion means here, and what happens when both sides changed the same
   event. That last one is the whole difficulty.

10. **The other two interfaces.** `TwoFactorService` → a `TwoFactorProvider`
    contract (TOTP now, passkeys later). `DefaultPortfolioContent` → a contract
    for where the seeded page comes from, so a fork ships its own. Nothing
    else: an interface with one class behind it is a file and an indirection.

11. **Split `PortfolioContentService`.** Four reasons to change in one class:
    shaping a read (`payload`), writing (`save`, `replaceOrdered`), history
    (`recordRevision`, `pruneRevisions`) and seeding (`seedDefaults`). An
    interface in front would preserve the problem rather than fix it. The one
    transaction and one write path must survive the split — that property is
    why the class exists.

12. **Put one-off jobs in `app/Actions/`.** Fortify and Jetstream set the
    precedent; prefer it to `lorisleiva/laravel-actions`. First candidates:
    restore a revision, reset to defaults, enrol a second factor, start and
    stop a timer.

13. **Raise events for what something else reacts to.** `PortfolioSaved` /
    `PortfolioRestored`, `InquiryReceived` to email you when the connect form
    is used, `TimerStarted` / `TimerStopped` so calendar sync can react without
    `TimerService` growing a branch. Move the inline `Login` / `Failed`
    listeners to `app/Listeners/` when a third appears.

14. **Update the "no bindings" note in `CLAUDE.md`** once 9 and 10 land.
