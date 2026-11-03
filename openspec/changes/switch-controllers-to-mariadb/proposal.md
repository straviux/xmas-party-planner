# Change: Switch controllers from JSON store to MariaDB

## Why
The schema slice (`migrate-to-mariadb`, already pushed) created
the tables and models, but the app still runs entirely on the
JSON file (`storage/app/party.json` via `PartyStore`). Until
the controllers switch, the MariaDB tables are dead weight:
nothing reads or writes them, and the rebuild is not a rebuild
yet. This change makes the database the real store.

## What
Switch every controller and page data path from `PartyStore`
(JSON) to Eloquent models on MariaDB, in slices, with the API
contract unchanged:

1. Party setup + guest list + draw.
2. Guest entry, PIN, and tear reveal.
3. Wishlist (add/edit/delete, board, reserve).
4. Polls (manage, vote, results).
5. Host admin (status, guest management, redraw, resets,
   invite regen, change password stays on `users`).
6. Retire the JSON store.

The frontend (`Party.vue`) is NOT changed in this proposal.
It keeps calling the same `/api/{action}` actions and
receiving the same response shapes (see design.md for the
one exception: ids become numeric database ids, which the
frontend must already treat as opaque — verified in slice 2).

## What this change deliberately does NOT do
- It does NOT change any behaviour in the specs. Every
  acceptance criterion in `openspec/specs/` still applies
  verbatim; only the storage behind it changes. If a slice
  uncovers a behaviour difference, that is a spec gap to fix
  in this proposal first, not a silent behaviour change.
- It does NOT migrate old party data. A party created in
  the JSON version does not appear in the database version,
  and vice versa. Test parties only until slice 6 is done;
  do not run a real party on the switched code until the
  JSON store is retired. Back up `storage/app/party.json`
  before starting slice 1 (manual, host-side, not code).
- It does NOT change the host login, which already uses the
  `users` table, and does NOT change the guest UI, Scor, the
  dock, or the admin layout.
- It does NOT archive `migrate-to-mariadb` on its own. Both
  changes are archived together when this change is accepted,
  because only then is the MariaDB version actually in use.

## Impact
- `PartyStore` (JSON) stops being the source of truth and is
  removed in slice 6. `ApiController` and `PageController`
  read/write Eloquent models instead, inside database
  transactions (see design.md).
- The `.env` database settings become load-bearing: the app
  no longer works at all with `DB_CONNECTION=sqlite` pointing
  at the old file, unless that sqlite database was itself
  migrated — which is out of scope. Local and deployed
  `.env` must point at MariaDB/MySQL before slice 1 is
  tested.
- No new tables are created in this change. If a slice needs
  a column the schema slice missed, that is a new migration
  proposed inside this change, not a silent schema edit.

## Acceptance
- Every slice's acceptance checklist in `tasks.md` passes on
  Arc's machine, against a fresh MariaDB database, with the
  same guest journeys as the JSON version (setup, entry +
  PIN, tear, wishlist, polls, host admin).
- After slice 6, the app works with `storage/app/party.json`
  deleted, and no code path reads it.
- `openspec/specs/` deltas in this change are merged, and
  both `migrate-to-mariadb` and this change are archived.
