# Design: controllers on MariaDB

## Keeping the API contract
`ApiController` keeps its dispatch (`/api/{action}`) and its
action names. Every action keeps its inputs, outputs, and
error codes from the specs (`already_claimed`,
`pin_incorrect`, `pin_required_create`, `not_claimed`,
`pin_already_set`, 409 for blocked guest changes, etc.).
The frontend is therefore unchanged, and every existing
acceptance test still applies.

The one representation change: ids. JSON guests had string
ids ('g…'); database guests have numeric ids. The API
returns whatever the database gives, and the frontend must
treat ids as opaque values. Slice 2 explicitly verifies
this (Who selection, session guest id, board `me`, vote
`guest_id`, reserve `owner_id` / `guest_id`). If any
frontend comparison assumes a string id, that is a frontend
bug to fix in slice 2, with its own spec note — not a
reason to keep string ids in the database.

## Replacing PartyStore
- Pure, storage-independent helpers move, they do not die
  with the store: name normalisation, invite-code
  generation, derangement, AVIF conversion, and the public
  guest/board/poll shaping (id, display, nickname, aliases,
  claimed, needs_pin, revealed; board reserved /
  reserved_by_me; poll counts / my_vote). Put them in a
  small shared helper/service used by the controllers, so
  the controllers stay thin and the shaping stays in one
  place.
- Everything else in `PartyStore` (load, save, mutate with
  flock, partyPath, deleteParty) is deleted in slice 6.
  Until then, slices must not mix stores: a slice switches
  its actions fully to the database, and its tests use a
  fresh database party, never a JSON party. Mixing (e.g.
  entry in the database, wishlist in JSON) would split one
  guest across two stores and is explicitly forbidden in
  this design.

## Transactions and locks (replacing flock)
JSON `mutate()` used one file lock around read-modify-write.
The database equivalent is a transaction plus a row lock:

- Entry / reveal: `DB::transaction` + `Guest::lockForUpdate`
  on the guest row, then verify/create PIN, set claimed /
  revealed, and commit. A second concurrent entry sees the
  locked row and then the claimed state, and gets
  `already_claimed` — same outcome as the JSON version.
- Reserve: transaction + lock on the wishlist item row.
- Vote: transaction; the UNIQUE(poll_id, guest_id)
  constraint is the backstop, and changing a vote updates
  the existing row.
- Setup / redraw / guest changes: one transaction covering
  all guest rows written, so a failed draw never leaves a
  half-drawn party.
- Host resets are single-row or whole-party updates inside
  transactions; reset everything deletes the party row and
  relies on the schema's cascade deletes.

## "No party yet" stays an empty state
The JSON version treated a missing `party.json` as "no
party yet" (empty reads, never 404). The database version
must do the same: no `Party` row means empty reads for
`list`, `wishlist_list`, and `poll_get`, and writes still
require a party. Do not turn "no party" into a 404 in the
switch — that regression already happened once in the JSON
version and was fixed there.

## Sessions and host gate — unchanged
- Guest session (guest id + PIN) and host session (host
  user id) work exactly as before. The recipient is still
  never stored in the session.
- Host gate stays server-side: signed-out host views return
  empty host props, regardless of storage.
- `requireHost` still checks the session host user against
  the `users` table (already database-backed; no change).
