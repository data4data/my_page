# TODO

What is left to do, worst first. How to do the work is in
[CONTRIBUTING.md](CONTRIBUTING.md).

## Security and the API

1. **The public endpoint sends whole database rows.**
   `GET /portfolio` needs no login and returns full models, so `id`, `slug`,
   `is_active` and the timestamps are public. Any new column joins them with
   no code change. Add Resource classes built from `PROFILE_KEYS` and
   `CHILD_KEYS`. Leave tasks and categories as they are.

2. **No test checks the shape of a response.**
   There is no `assertJsonStructure` or `assertExactJson` in `tests/`, which is
   why item 1 goes unnoticed. Add one over `GET /portfolio` first.

3. **Errors are handled three different ways.**
   `ApiError.validationMessage` (`api.js:22`) is never called, and seven
   `catch {}` blocks in `CalendarView`, `CategoriesView` and `ReportView` drop
   the real message for a fixed string. Add one shared handler next to
   `apiFetch` and call it from all of them.

## Code with no tests

4. **`showsIn()` is copied in two places and tested in one.** PHP is covered,
   `shared/portfolio.js` is not, and both files say to keep them matching.

5. **`planning.js` has no tests.** 251 lines holding every planner request.

6. **`theme.js` has no test.** The holder counting in `holdTheme()` decides
    whether the public page stays out of dark mode.

7. **`TaskModal` and `ReportView` have no tests.** 242 and 270 lines.

## Rebuild the public page

8. **The page text is drawn by JavaScript, so the HTML comes back empty.**
    Render the six sections in Blade from the same data `payload()` returns,
    and keep small Vue components for the carousel, the scroll-spy, the
    language toggle and the connect modal. This splits `PublicPage.vue` as a
    side effect.

9. **The public page has no light or dark mode.** That was deliberate while
    the two halves looked different. Decide again.

10. **There is no skip link.** A keyboard user tabs through the whole nav and
    side rail before reaching the content.

## Never built

11. **Tasks cannot be dragged between days** in Week and Month view.

12. **`TaskSource::AiChat` is unused.** Build it or remove the case.
