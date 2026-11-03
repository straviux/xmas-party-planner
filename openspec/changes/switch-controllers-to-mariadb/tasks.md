# Tasks: switch-controllers-to-mariadb

Proposal stage only: nothing below is implemented yet. Each
slice ends with Arc's acceptance check on his machine, on a
fresh MariaDB database, before the next slice starts. A slice
is done only when its checklist passes, not on a build pass.

## Slice 1 — Party setup + guest list + draw
- [ ] `setup` creates a `Party` row, `Guest` rows, and a
  derangement in `recipient_id`, inside one transaction.
  `already_set_up` when a party row already exists.
- [ ] `list` reads guests from the database and returns the
  same public shape (id, display, nickname, aliases,
  claimed, needs_pin, revealed) — never recipient mappings,
  never pin_hash.
- [ ] Host guest views read from the database (blind list
  with pin_set), with no party data leaking when signed out.
- [ ] Acceptance (Arc): create a party, draw gives nobody
  themselves, host dashboard shows no recipient mappings.

## Slice 2 — Guest entry, PIN, and tear reveal
- [ ] `enter` locks the name + verifies/creates the PIN in
  the database, returns `{ok:true}` with NO recipient,
  using a row lock so two devices racing on the same name
  cannot both enter.
- [ ] `reveal` (tear) verifies the PIN, sets revealed=true,
  returns the recipient once; a second reveal returns
  `already_claimed` with no recipient.
- [ ] `guest_verify_pin` / `guest_set_pin` work against the
  database, including the stale-list fallback (not_claimed
  falls back to `enter`) and the revealed-vs-not-revealed
  routing (guest home vs reveal page).
- [ ] Verify the frontend treats numeric database ids as
  opaque (no string-only id assumptions break Who, board,
  votes, or session guest id).
- [ ] Acceptance (Arc): full pin-reveal spec checklist —
  enter with no name shown, refresh before tearing lands
  back on reveal, wrong PIN fails without revealing,
  correct PIN tears once, second tear blocked, return
  after revealing goes to guest home, host Reset PIN lets
  a locked guest create a new PIN.

## Slice 3 — Wishlist
- [ ] `wishlist_add` / `wishlist_delete` write
  `wishlist_items` rows, with the same PIN verification,
  kind derivation, image validation, and AVIF conversion
  as the JSON version.
- [ ] `wishlist_list` builds the board from the database,
  with aliases, and reserved / reserved_by_me computed
  from `reserved_by_id` (never exposing the reserver id).
- [ ] `wishlist_reserve` toggles `reserved_by_id` inside a
  transaction with a row lock; own items cannot be
  reserved; already-reserved by another guest returns 409.
- [ ] Removing a guest deletes their wishlist items
  (cascade, already in the schema).
- [ ] Acceptance (Arc): add text/link/image items, reserve
  as another guest, unreserve only as the reserver, own
  items refused, board search by alias works.

## Slice 4 — Polls
- [ ] Poll create/edit/close/reopen/delete (host only)
  write `polls` / `poll_options` rows.
- [ ] `poll_vote` writes/updates a `votes` row, enforced by
  the UNIQUE(poll_id, guest_id) constraint; closed polls
  refuse changes.
- [ ] `poll_get` builds counts/percentages and my_vote from
  the database, never voter names.
- [ ] Acceptance (Arc): vote, change vote while open,
  closed poll refuses changes, results show counts only.

## Slice 5 — Host admin
- [ ] `host_status`, party settings (`party_update`),
  guest add/edit/remove with the claimed-block rule and
  automatic re-draw while unclaimed.
- [ ] `host_redraw` (blocked while claimed), `reset_one`,
  `host_unclaim_all` (clears claimed + revealed, no redraw,
  no PIN clear), `host_reset_pin` (PIN only, allowed when
  claimed), clear wishlists, invite regen.
- [ ] `host_clear_wishlists` deletes wishlist rows only;
  reset everything deletes the party row and lets the
  cascades clean up guests, items, polls, and votes.
- [ ] Acceptance (Arc): each reset does exactly what its
  spec says and nothing more, including Unclaim all as
  the Redraw prerequisite.

## Slice 6 — Retire the JSON store
- [ ] Remove `PartyStore` JSON read/write paths and any
  reference to `storage/app/party.json` from controllers
  and pages. Pure helpers that are storage-independent
  (name normalisation, derangement, AVIF conversion) move
  to a shared helper; they are not deleted with the store.
- [ ] Verify with `party.json` deleted: setup, entry,
  reveal, wishlist, polls, and host admin all work.
- [ ] Archive `migrate-to-mariadb` and this change, and
  merge this change's spec deltas into `openspec/specs/`.
- [ ] Acceptance (Arc): fresh checkout → migrate → seed →
  setup → full guest journey on a phone, from the spec
  steps alone.

## Explicitly NOT in this change
- [ ] No behaviour changes, no UI changes, no new features.
- [ ] No old party data migration.
- [ ] No new tables, unless a slice proves one is missing —
  then it is proposed here first, not added silently.
