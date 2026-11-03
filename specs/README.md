# Specs — Christmas Party Planner (rebuild)

This folder is the source of truth for the rebuild. Code follows specs.
Specs follow code only when a spec is deliberately changed first, and
that change is recorded below.

## How to use these specs

1. Read `constitution.md` first. Nothing in a feature spec may
   contradict it. If a feature seems to need a contradiction, change
   the constitution deliberately, not silently.
2. Read the feature spec for the slice you are building. Build only
   what that spec says, in the order in `tasks.md`.
3. A slice is done only when every acceptance criterion in its spec
   passes. "Looks right" is not done.
4. If behaviour needs to change, update the spec first, then the code,
   then note the change in the Change log below. Never let code drift
   from the spec without a spec change.

## Layout

- `constitution.md` — non-negotiable product rules.
- `templates/feature-spec-template.md` — the template every feature
  spec follows. A spec missing a section is a draft, not a spec.
- `features/` — one file per feature, numbered in build order:
  - `01-access-and-invite.md` — invite code, guest entry gate.
  - `02-guests-and-draw.md` — guest list, Secret Santa draw.
  - `03-pin-and-reveal.md` — guest PIN, entry lock, tear reveal.
    This is the most detailed spec, because it is where the old app's
    state machine was ambiguous. Write this one first, fully.
  - `04-wishlist.md` — wishlist posting, anonymous reserve.
  - `05-polls.md` — Christmas theme voting polls.
  - `06-host-admin.md` — host sign-in, dashboard, guest management,
    redraw, resets, danger zone.
  - `07-deployment.md` — hosting, build, data, migration.
- `plan.md` — technical plan: stack, storage, routes, sessions.
  Written after the feature specs are agreed, not before.
- `tasks.md` — ordered implementation slices, one per spec section,
  each with its acceptance check.

## Status

- `constitution.md`: agreed (carried over from the current app).
- `03-pin-and-reveal.md`: detailed draft, ready for review.
- All other feature specs: scaffold only — headings, scope, and the
  known rules carried over from the current app. They need a full
  review pass (acceptance criteria, edge cases, API contracts)
  before their slice is built.
- `plan.md`: draft, with open decisions marked `[DECISION]`.
- `tasks.md`: draft order, to be confirmed once the specs are reviewed.

## Change log

- 2026-11-03: Scaffold created from the current Laravel app's
  behaviour (main at 0b9a8d49). No code changed. Nothing pushed yet.
  Open decisions are marked `[DECISION]` in the specs and plan.
