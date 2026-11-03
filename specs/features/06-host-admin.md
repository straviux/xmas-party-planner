# 06 — Host admin (SCAFFOLD — needs full review)

Status: scaffold only. Rules carried over from the current app.

## 1. Problem / user story

The host sets up and runs the party from a desktop, with full
control over guests, polls, and resets — without ever seeing
who got whom.

## 2. Scope

- In scope: host sign-in/out, party settings, guest management,
  dashboard stats, invite code and QR, redraw, Reset one,
  Unclaim all, Reset PIN, Clear all wishlists, Reset everything,
  Change password.
- Out of scope: guest pages, deployment (see `07`).

## 3. State machine / data

- Host user: username + password (users table), never email,
  never PIN. Initial host account is seeded; the password must
  be changeable from the dashboard and should be changed
  immediately after first sign-in. `[DECISION]` in `plan.md`:
  seeding method for the rebuild (env-set vs seeder default).
- Host views are gated server-side: signed out, a host view
  returns empty host props only (no guests, wishlists, polls,
  party name, budget, or date). Client-side, a signed-out host
  view shows only a sign-in screen, no sidebar, no host nav.
- Every host write action requires host sign-in server-side.

## 4. API contract

To be completed in review. Known actions: `host_login`,
`host_logout`, `host_change_password`, `host_status`,
`party_update`, `host_guest_add/update/remove`,
`host_reset_pin` (see `03`), `reset_one`, `host_unclaim_all`,
`host_redraw`, clear wishlists, reset everything.

Rules to specify precisely in the review pass:

- Guest add/edit/remove: blocked with 409 while any name is
  claimed ("reset claims first"). Every successful change
  re-draws automatically (see `02`).
- Reset one: one guest only, clears claimed + revealed.
- Unclaim all: all guests, clears claimed + revealed, does NOT
  redraw, does NOT clear PINs. It is the prerequisite for
  Redraw, which is blocked while any name is claimed.
- Reset PIN: see `03` — clears the PIN only, allowed even when
  claimed.
- Clear all wishlists: wishlists only, guests and draw untouched.
- Reset everything: deletes the whole party (danger zone, with
  a confirm dialog). This is the only action that deletes the
  party itself.
- Party settings edit: details only, via `party_update`. Editing
  settings never clears or touches the guest list.

## 5. UI behaviour

- Admin layout (desktop): fixed 260px left sidebar (Host +
  party name, Dashboard / Guests / Polls / Party settings,
  "Signed in as [username]", Sign out, "Back to guest home"),
  main content min-width 960px, admin wrapper min-width
  1220px, horizontal scroll on narrow windows, no mobile
  reflow. Per-page header: title + subtitle + primary action
  on the right.
- Dashboard: stat cards (Guests, Locked, Awaiting, Polls),
  locked/awaiting progress, Party panel (name/date/budget,
  invite Copy/Regen, QR Print), Claimed table (Name, Nickname,
  Status, Reset per locked name, Refresh), Quick actions,
  Admin controls, Danger zone.
- Guests: real table (Name, Nickname, Aliases, PIN set, Status,
  Actions) with a search box matching name/nickname/alias.
  Reset PIN button per guest (key icon), disabled when no PIN
  is set, with a confirm dialog.
- Polls: real table (Question, Status, Votes, Winner, Actions).
- Party settings: panel form, two-column fields; create flow
  shows the guest list as a table in the same panel. A draft
  is discarded after a successful create.
- No host content flashes before sign-in. Sign-out goes to
  guest home.

## 6. Acceptance criteria

[TODO — review pass: signed-out host views show no data,
sign-in stays on the intended page, add/edit/remove blocked
while claimed, Reset one, Unclaim all then Redraw, Reset PIN
for a claimed guest, Clear wishlists keeps guests, Reset
everything deletes the party, Change password works and the
old password stops working.]

## 7. Edge cases

[TODO — review pass: host session expiry mid-edit, two host
sessions, redraw with 1 guest, reset a guest who has a
wishlist and votes, regen invite code after guests entered.]

## 8. Open decisions

- `[DECISION]` Keep the fixed 260px sidebar admin layout, or
  redesign the host layout as part of the rebuild?
