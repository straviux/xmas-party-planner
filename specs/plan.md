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

## Storage — DECIDED: MariaDB / MySQL (2026-11-03, Arc's choice)

The rebuild uses a MariaDB / MySQL database, not the current
app's single JSON file store. This is Option B from the earlier
draft, now decided — do not reopen it without a deliberate spec
change.

What this means in practice:

- Proper tables, one per concept: `parties`, `guests`,
  `wishlist_items` (owned by a guest), `polls`, `poll_options`,
  `votes`, `users` (host accounts). The feature specs' field
  lists are the logical contract; these tables are their
  physical form. Field names in the specs map to table columns
  of the same name unless a spec says otherwise.
- Laravel's `mysql` driver, with MariaDB-compatible migrations:
  standard column types only, no MySQL-only features, charset
  `utf8mb4`, collation `utf8mb4_unicode_ci`, so the same
  migrations run on MariaDB and on MySQL.
- PINs are still stored hashed only (`pin_hash`), never plain
  text. Recipient mappings live in `guests.recipient_id` and
  are still never exposed in any API response except the
  `reveal` response, and never to the host.
- Concurrency is handled by database transactions and row
  locks for draw, reserve, and vote — the JSON file's flock
  goes away with the JSON file.
- Consequences Arc should know about before building starts:
  local development now needs a MariaDB/MySQL instance running
  (not just `php artisan serve`), and the deployed host must
  provide a MariaDB/MySQL database. Both are covered in
  `07-deployment.md`, which must be re-checked against Arc's
  actual host before Phase F.
- The old JSON party data does NOT migrate automatically
  (constitution rule 20). If a migration is ever wanted, it is
  a separate spec.

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

1. Storage: DECIDED — MariaDB / MySQL (see above).
2. `[DECISION]` Stack: keep Laravel + Inertia, or re-evaluate?
3. `[DECISION]` Host deployment: does Arc's host support the
   Laravel deployment in `07-deployment.md` (now including a
   MariaDB/MySQL database), or is it drop-files-only?
4. `[DECISION]` Scan QR: design-only, or real camera scanner?
5. `[DECISION]` Initial host account: env-set credentials, or
   seeder default that must be changed on first sign-in?
6. `[DECISION]` Wishlist images + AVIF: keep, or drop for v1?
7. `[DECISION]` Multiple polls, or one theme poll for v1?
