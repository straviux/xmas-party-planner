# Technical plan (DRAFT — decisions marked, specs come first)

This plan is written after the feature specs, and it references
them instead of restating them. Do not build from this plan
alone; the feature specs are the contract.

## Stack — proposed, to confirm

Keep the current stack for the rebuild, unless a `[DECISION]`
below changes it: Laravel + Inertia + Vue, single-page guest
experience with the guest dock, desktop admin layout, Scor
mascot (pure CSS component). The reason to keep it is not
loyalty to Laravel — it is that the feature specs were written
against this shape, and changing stack mid-rebuild means
rewriting the plan and re-checking every spec.

## Architecture (current app shape, to confirm)

- One Inertia page for the guest/host experience, with routes
  for home, who, reveal, gifts, wishlists, guest-home, vote,
  and host dashboard/setup/guests/poll.
- One API dispatch route (`/api/{action}`) with action names
  kept stable across the rebuild, so the specs' API contracts
  stay readable. A rebuild may replace this with resource
  routes, but then every spec's section 4 must be updated first.
- Server-side host gate: host views compute sign-in from the
  session independently of party data, and return empty host
  props when signed out.
- Guest identity in the session (guest id + PIN). Recipient
  never in the session.
- File locking around every read-modify-write that changes
  guests, wishlists, or polls (draw, reserve, vote).

## Storage — the biggest open decision

`[DECISION]` The current app stores the whole party as one JSON
file with file locking. That is simple and worked, but it is
also why host views, guest views, and the draw all touch one
blob. For the rebuild, choose explicitly before building:

- Option A: keep the JSON file store. Pros: no migrations, no
  database to configure on Arc's host, same deployment as now.
  Cons: every feature shares one file, concurrent writes need
  care, querying (e.g. "all wishlists by owner") is manual.
- Option B: proper database tables (parties, guests,
  wishlists, wishlist_items, polls, poll_options, votes, users).
  Pros: each feature owns its tables, host/guest queries are
  normal queries, scaling and reporting are easier. Cons:
  migrations + database setup on Arc's host, and the deployment
  spec (`07-deployment.md`) must be rewritten to match.

Recommendation: decide this in the spec review, not during
coding. If Arc's host makes database setup painful, Option A
wins by default. Do not start with Option B and fall back
mid-build — the specs' data sections change with the choice.

## Routes and sessions (to be pinned in review)

- Guest session: guest id + PIN, set on `enter` / `reveal` /
  `guest_verify_pin` / `guest_set_pin`, cleared on guest logout.
- Host session: host user id, set on `host_login`, cleared on
  `host_logout`. Host password change must invalidate the old
  password immediately.
- Invite code: generated at party setup, regenerable from the
  host dashboard. Regenerating must not affect already-entered
  guests' sessions (to be confirmed in the `01` review).

## Frontend notes (carried over)

- Guest pages mobile-first, host pages desktop-first (see
  constitution rules 15–16).
- No emojis, Lucide only, verified icon names.
- One global toast for feedback, no native `alert()`/`confirm()`
  except where a spec explicitly calls for a confirm dialog
  (host danger actions, Reset PIN).
- Scor: pure-CSS component with a size prop, restored from the
  old app's CSS. The original Santa image and the disabled
  WebM mascot are out of scope unless a spec adds them.

## Open decisions (must be answered before tasks 1–3)

1. `[DECISION]` Storage: JSON file (A) or database (B)?
2. `[DECISION]` Stack: keep Laravel + Inertia, or re-evaluate?
3. `[DECISION]` Host deployment: does Arc's host support the
   Laravel deployment in `07-deployment.md`, or is it
   drop-files-only?
4. `[DECISION]` Scan QR: design-only, or real camera scanner?
5. `[DECISION]` Initial host account: env-set credentials, or
   seeder default that must be changed on first sign-in?
6. `[DECISION]` Wishlist images + AVIF: keep, or drop for v1?
7. `[DECISION]` Multiple polls, or one theme poll for v1?
