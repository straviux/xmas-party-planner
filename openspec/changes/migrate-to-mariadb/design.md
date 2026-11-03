# Design: MariaDB schema

## Table mapping (spec field -> column)
Spec field lists stay as written; they map 1:1 to columns of
the same name, with these deliberate choices:

- `parties`: id, name (the spec's `party`), budget (string,
  nullable — the old JSON budget is a formatted string, not a
  number, so it stays a string), date (date, nullable),
  invite_code (string, unique, 6 chars), timestamps.
- `guests`: id, party_id (FK, cascade delete — deleting a
  party deletes its guests), display, nickname (nullable),
  aliases (json, nullable), pin_hash (nullable string, hashed
  only), recipient_id (nullable self-FK to guests,
  nullOnDelete), claimed (bool, default false), claimed_at
  (nullable timestamp), revealed (bool, default false),
  timestamps. Index on party_id.
- `wishlist_items`: id, guest_id (FK, cascade delete —
  removing a guest removes their wishlist, per spec),
  text (text, nullable), link (string, nullable), notes
  (text, nullable), kind (string, default 'text'; derived
  image > link > text in the app, stored for convenience),
  image (longText, nullable — base64 data URLs up to the
  spec's 14M-char server cap), reserved_by_id (nullable
  FK to guests, nullOnDelete — the reserver's id, never
  exposed except as reserved/reserved_by_me), timestamps.
- `polls`: id, party_id (FK, cascade delete), question
  (string), open (bool, default true), timestamps.
- `poll_options`: id, poll_id (FK, cascade delete), text
  (string), sort_order (unsigned int, default 0), timestamps.
- `votes`: id, poll_id (FK, cascade delete), guest_id (FK,
  cascade delete), poll_option_id (FK, cascade delete),
  timestamps, UNIQUE (poll_id, guest_id) — one vote per
  guest per poll, changeable by updating the row.

## Decisions
- Numeric auto-increment ids, not the old JSON string ids
  ('g…', 'w…'). The old ids were file-store artefacts; in
  the rebuild, ids are internal database ids and the frontend
  uses whatever the API returns. No frontend code in this
  change depends on id format, because no controller is
  switched yet.
- Foreign keys everywhere a spec implies ownership, with
  cascade delete only where a spec says removal propagates
  (party -> guests, guest -> wishlist_items, guest -> votes,
  poll -> options/votes). Recipient links null out instead
  of cascading, so deleting a guest never deletes their
  recipient.
- No MySQL-only features, no triggers, no stored procedures.
  Migrations must run unchanged on MariaDB and on MySQL.
- `users` is untouched: the host login already uses it.
