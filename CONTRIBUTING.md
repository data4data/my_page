# Contributing

**This file is the single source for how work happens in this repository.**
`CLAUDE.md`, `.claude/skills/ship/` and the PR template all point here rather
than repeating any of it — a rule written twice is a rule that will drift.

`CLAUDE.md` covers something different: what the project *is*. Architecture, the
two aggregates, time handling, the design system. Read it before changing code;
read this before committing it.

Nothing here names a particular install — no remote URL, no admin path, no
persona. `git push origin main` works whatever `origin` points at; run
`git remote -v` for yours. Keep it that way when editing: this project is meant
to be forked and made someone else's, which is the same reason `ADMIN_PATH` is
never hardcoded.

---

## 1. Set up

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed        # placeholder content, categories, and a local-only demo login
php artisan app:install           # a real admin account and the profile — asks for them
composer run dev                  # serve + queue + logs + vite
```

On a development machine `migrate --seed` leaves a demo login behind —
`DEMO_ADMIN_EMAIL` / `DEMO_ADMIN_PASSWORD` in `.env`, defaulting to
`demo@my-page.test` / `demo-workspace` — so `migrate:fresh --seed` does not lock
you out. It is `local`-only and refuses to replace an account that already
exists; see the seeding section of `CLAUDE.md`. Run `app:install` instead for
anything real.

MySQL everywhere, including tests — the same engine as production, so strict
mode, foreign-key indexing and JSON handling behave the same in all three.
Create the test database once: `CREATE DATABASE my_page_testing;`.

`ADMIN_PATH` sets the URL prefix the private workspace lives behind —
per-install, never hardcoded. Changing it needs `php artisan route:clear`.

## 2. Branch first

Create the branch **before** the first commit. Nothing is committed onto `main`.

```bash
git checkout -b feat/short-name      # feat/ | refactor/ | bugfix/
```

Work already started on `main`? Move it rather than committing:
`git stash && git checkout -b feat/x && git stash pop`.

## 3. Make the change

Business logic goes in `app/Services`, `app/Policies`, `app/Http/Requests` or on
the model — not in a controller. If you cannot test it without an HTTP request,
it is in the wrong place. `tests/Feature/TimerServiceTest.php` is the pattern.

### Changes that must travel together

Each of these fails **silently**, not loudly:

| Change | Also update |
|---|---|
| A new UI string | **both** `en` and `nl`, in whichever of `i18n.js`, `i18n-public.js` or `i18n-admin.js` its half belongs to |
| A message from one of our own validation rules | `lang/en/rules.php` **and** `lang/nl/rules.php` — a literal `$fail('...')` is English whatever language was asked for |
| A new `icon` value in DB or seed data | `iconMap` in `resources/js/shared/icons.js`, or it renders nothing |
| A new `TaskStatus` case | `TASK_STATUSES` in `resources/js/shared/planning.js` |
| A profile/child field | migration → `$fillable`/`$casts` → the matching list in `App\Support\PortfolioFields` → rule in `UpdatePortfolioRequest` → `DefaultPortfolioContent::content()` |
| A translated profile/child field | `PortfolioFields::TRANSLATED_*` **and** `translatableProfile`/`translatableItemFields` in `resources/js/shared/portfolio.js` |
| A new layer, endpoint or architectural decision | `CLAUDE.md` |
| A workflow rule | **this file only** |

Two standing prohibitions live in `CLAUDE.md`, with the reasoning next to the
code they constrain: **never hardcode the admin URL prefix** (see its Auth
section) and **never write a raw `z-index`** (see its Design system section).

Check EN/NL parity:

```bash
npx vitest run resources/tests/shared/i18n.test.js
```

It checks each dictionary file on its own, so a Dutch key missing from the
workspace half fails even though the visit card's half is complete.

## 4. Run the gate

All five, every time.

```bash
vendor/bin/pint       # rewrites files — run before committing, not after
composer analyse      # PHPStan/Larastan, level 5 — finds type errors without running the code
composer test
npm test
npm run build         # catches template and import errors the tests never reach
```

`composer analyse` must come out clean. If it reports something, fix the cause
rather than adding an ignore or a baseline entry — the config already turns off
the two checks that gave wrong advice here, with the reasons written down in
`phpstan.neon`.

## 5. Check what tests cannot

Green is not enough for these two. Both have produced real bugs here.

**Public-page content changed?** Start the app and save *every* tab in the admin
editor — Profile, Metrics, Expertise, Process, Projects, Social links, Shared,
and Settings' Language tab.
`UpdatePortfolioRequest` can reject a payload the editor legitimately produces,
and nothing automated will tell you.

**`--env=testing` does not mean the test database.** There is no
`.env.testing`, so `--env=testing` falls back to `.env` — which points at your
development database. The test database name lives in `phpunit.xml`, which the
artisan CLI never reads. So `php artisan migrate:fresh --env=testing` drops
your development data while looking like it is being careful. Let
`php artisan test` manage the test database; it uses `RefreshDatabase` and
reads `phpunit.xml`.

If a dev server is already running, reuse it rather than starting a second one on
the same port. Touching real local content? Back the database up first:

```bash
mysqldump -u root -p portfolio > /tmp/portfolio-backup-$(date +%H%M%S).sql
```

**Dates changed?** Re-read the time-handling section of `CLAUDE.md`, then look at
a real calendar in the browser. `start_datetime` is wall-clock, `TimeLog.started_at`
is a true instant; they serialise identically and differ by your UTC offset.

## 6. Commit short

A subject line saying what changed, plus at most two or three body lines — only
for something a reader of the diff would find surprising.

```
Rename Reset content tab to Content versions

Defaults become the first row of the version list, and now confirm
before restoring like the saved versions already did.
```

*Why* belongs in a code comment or in `CLAUDE.md`, next to the code, rather than
in a log nobody searches.

Do not add `Co-Authored-By` or "Generated with" trailers — this is a personal
portfolio repository and a trailer makes GitHub list a second contributor. If one
slips in, amend **before** pushing; once pushed, say so rather than force-pushing.

If the working tree holds unrelated work, stage paths explicitly instead of
`git add -A`. Stage both halves of a move together so git records a rename and
keeps the file's history.

## 7. Catch up with `main` — on the branch

`main` may have moved. Bring it into your branch first, so conflicts are resolved
**here** and never on `main`.

```bash
git fetch origin
git log --oneline HEAD..origin/main      # empty = nothing new, skip to step 8
git merge origin/main                    # main INTO the branch, never the reverse
# resolve conflicts, then commit
```

**Re-run the gate afterwards** — a merge can break code that passed ten minutes
ago. That is the whole reason for doing this here. Re-check step 5 too if the
incoming changes touched content, dates or i18n.

Merging a stale branch straight into `main` either conflicts on `main` itself or
quietly reverts someone else's commits.

## 8. Push the branch, then merge

```bash
git push -u origin feat/short-name

git checkout main
git merge --ff-only origin/main          # sync; fails loudly if local main drifted
git merge --no-ff feat/short-name
```

`--no-ff` is not optional: the branch already contains `main` by now, so git
would fast-forward and erase any sign the branch existed.

`--ff-only` on the sync is deliberate — if local `main` has drifted from the
remote it stops rather than quietly creating a merge nobody asked for.

Re-run `composer test` on `main`, then push it.

## Running tests

```bash
php artisan test --filter=test_name       # one test
php artisan test tests/Feature/Foo.php    # one file
npm run test:watch                        # frontend, watch mode
```
