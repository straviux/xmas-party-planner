# deployment — delta

## ADDED Requirements

## Requirement: Migrations create the party schema
Running `php artisan migrate` on a fresh database SHALL
create `parties`, `guests`, `wishlist_items`, `polls`,
`poll_options`, and `votes` with the columns in the change
design, and SHALL NOT alter the existing `users` table.

#### Scenario: Fresh migrate
- Given a fresh MariaDB/MySQL database, when migrations run,
  then all six tables exist alongside the existing tables.
- Given a database where earlier migrations already ran in
  their own batch, when `migrate:rollback` runs for the new
  batch, then the six tables drop cleanly and `users` remains.
  (On a completely fresh database all migrations share one
  batch, so a rollback there drops them all — that is normal
  Laravel behaviour, not a bug in these migrations.)

## Requirement: .env.example documents the MariaDB connection
`.env.example` SHALL set `DB_CONNECTION=mysql` and document
`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and
`DB_PASSWORD`, and SHALL NOT contain real credentials.

#### Scenario: Fresh checkout setup
- Given a fresh checkout, when a developer copies
  `.env.example` to `.env` and fills in their own database
  settings, then migrations run without editing code.
