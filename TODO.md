# TODO

What is left to do, worst first. How to do the work is in
[CONTRIBUTING.md](CONTRIBUTING.md); decisions already taken are in
[CLAUDE.md](CLAUDE.md), not here. Nothing here is started.

## Never built

1. **Tasks cannot be dragged between days** in Week and Month view.
2. **`TaskSource::AiChat` is unused.** Build it or remove the case.

## Interfaces, actions and events

3. **Calendar sync.** Google, Microsoft 365 and CalDAV are three
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

4. **Put one-off jobs in `app/Actions/`.** Fortify and Jetstream set the
    precedent; prefer it to `lorisleiva/laravel-actions`. First candidates:
    restore a revision, reset to defaults, enrol a second factor, start and
    stop a timer.

