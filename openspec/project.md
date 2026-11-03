# Project: Christmas Party Planner (rebuild)

## Purpose
A general Christmas party app, not Secret Santa-only: Secret Santa
via a single QR/invite code, wishlist posting with anonymous
reserve, and Christmas theme voting polls. One party at a time.

## Tech stack (proposed, see changes for decisions)
- Laravel + Inertia + Vue (to confirm in the first rebuild change;
  the previous app used this stack).
- MariaDB / MySQL database — DECIDED 2026-11-03. Laravel `mysql`
  driver, MariaDB-compatible migrations, charset `utf8mb4`,
  collation `utf8mb4_unicode_ci`. Tables: parties, guests,
  wishlist_items, polls, poll_options, votes, users.
- Local run (previous app): Vite 5139 + `php artisan serve` on
  port 8005, opened at http://localhost:8005. A MariaDB/MySQL
  instance must be running locally first for the rebuild.

## Non-negotiable rules (constitution)
- NO per-guest sign-in, ever. The invite code is the only guest
  gate. Anyone with the code can search a name and add/edit that
  name's wishlist. This is deliberate, not a security hole.
- Home page is guest-only. Guests cannot create a party from
  home. Host setup/management only via manually entered host URLs.
- Host access is username + password only. Not a PIN, not email.
- The recipient is shown exactly once, via the tear reveal, and
  is never stored in the guest session and never shown to the
  host — not even in admin views. The draw is a derangement.
- Guest PINs are stored hashed only, never in plain text.
- No emojis in app UI. Lucide icons only, verified names only.
  No text shadows (tear strip and Scor bubble are the named
  exceptions). Titles inside cards, actions inside cards.
- Guest pages are mobile-first (guest dock). Host pages are
  desktop-first (fixed 260px admin sidebar, horizontal scroll
  on narrow windows, no mobile reflow).
- Arc deploys himself to his own host and does all visual /
  acceptance testing on his machine. Muse does code-level
  checks only, on throwaway data, never real party data.
- Secrets stay put: host credentials only on the server, no
  credential ever committed to the repo.
- Old party data does not migrate automatically.

## How OpenSpec is used here
- `openspec/specs/` is the canonical truth: what the rebuild
  SHALL do. Each capability has `spec.md` with requirements
  (SHALL statements) and at least one scenario per requirement.
- `openspec/changes/<change-id>/` is a proposal: what SHOULD
  change, with `proposal.md`, `tasks.md`, and spec deltas
  (`## ADDED / MODIFIED / REMOVED Requirements`) per affected
  capability. No code is written before a proposal exists.
- After a change is implemented and accepted, it is archived
  under `openspec/changes/archive/` and its deltas are merged
  into `openspec/specs/`.
- The previous `specs/` folder is superseded by this directory
  and is to be removed when this conversion is pushed, so there
  is exactly one source of truth.
- Open decisions are marked `[DECISION]` in the relevant spec
  or change proposal. The storage decision (MariaDB/MySQL) is
  already decided; the remaining open decisions are listed in
  `openspec/changes/` proposals as they arise, starting with:
  stack confirmation, host database support, QR scanner scope,
  host seeding method, wishlist images/AVIF, single vs
  multiple polls.
