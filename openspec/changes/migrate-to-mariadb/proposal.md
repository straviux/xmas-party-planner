# Change: Migrate party data layer to MariaDB/MySQL — schema slice

## Why
The rebuild decided (2026-11-03, Arc) to use MariaDB/MySQL
instead of the current app's single JSON file
(`storage/app/party.json` with flock). Without a schema, no
controller work can start, and every later slice would be
blocked on table design.

## What
This change is the SCHEMA slice only:

- Laravel migrations creating `parties`, `guests`,
  `wishlist_items`, `polls`, `poll_options`, and `votes`.
- Eloquent models for those tables, with the relationships
  and casts the specs need.
- `.env.example` switched to `DB_CONNECTION=mysql` with the
  MariaDB/MySQL settings documented.

## What this change deliberately does NOT do
- It does NOT switch any controller, page, or frontend code
  from `PartyStore` (JSON) to the database. The current app
  keeps working exactly as before on its JSON store. Switching
  controllers is a separate, later change
  (`switch-controllers-to-mariadb`, not yet proposed), done
  one feature at a time (entry/PIN first, then wishlist,
  then polls), so a half-switched app never ships.
- It does NOT migrate old party data. There is no live party
  data to migrate in this environment, and constitution rule
  20 says old data does not migrate automatically. If a data
  migration is ever wanted, it is its own proposal.
- It does NOT change the host `users` table, which already
  exists (plus the username migration).

## Impact
- New tables only; no existing table is altered, no existing
  behaviour changes. Running the migrations on a fresh
  database creates empty party tables alongside the existing
  users/cache/jobs tables.
- After this change, the next change can start building the
  entry/PIN flow against real tables instead of the JSON file.

## Acceptance
- `php artisan migrate` succeeds on a fresh database and
  creates all six tables with the columns in `design.md`.
- `php artisan migrate:rollback` drops them cleanly, in
  reverse order, without touching `users`.
- Every column in the specs' field lists exists in the schema
  (see the spec deltas in this change).
