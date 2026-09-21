---
name: ship
description: Take a change in this repo from branch to merged main — branch naming, the verification gate (Pint, PHP tests, Vitest, build), the by-hand checks automated tests cannot cover, short commits, and integrating main before merging. Use when starting or finishing a change here, or when asked to commit, merge, push or ship work.
---

# Shipping a change

**Read `CONTRIBUTING.md` and follow it.** It is the single source for every rule
below — branching, what must travel with a change, the gate, commit style, and
the merge order. Nothing is restated here, deliberately: a rule written in two
places is a rule that will drift.

This skill exists only to give the sequence a name and an entry point.

## The order

| Step | `CONTRIBUTING.md` section |
|---|---|
| 1. Branch **before** the first commit | § 2 Branch first |
| 2. Make the change — check what must travel with it | § 3 Make the change |
| 3. Run all five gate commands | § 4 Run the gate |
| 4. Do the by-hand checks tests cannot make | § 5 Check what tests cannot |
| 5. Commit short, no trailers | § 6 Commit short |
| 6. Merge `main` **into the branch**, re-run the gate | § 7 Catch up with `main` |
| 7. Push the branch, then merge into `main` | § 8 Push the branch, then merge |

## Three that get skipped

Named here only so they are not missed — each section says why.

- **Step 3 is all five commands**, not the two that feel relevant.
- **Step 4 applies to every content and date change.** Green tests are not
  sufficient there.
- **Step 6 comes before step 7**, not after.

## Before pushing

Confirm first unless already told to go ahead. Pushing publishes work to a
remote others may already be reading.
