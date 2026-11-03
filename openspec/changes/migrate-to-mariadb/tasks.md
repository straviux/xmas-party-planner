# Tasks: migrate-to-mariadb (schema slice)

- [x] Write migrations for parties, guests, wishlist_items,
  polls, poll_options, votes (MariaDB-compatible, standard
  column types only, utf8mb4 via Laravel defaults).
- [x] Write Eloquent models: Party, Guest, WishlistItem, Poll,
  PollOption, Vote, with relationships and casts.
- [x] Switch `.env.example` to DB_CONNECTION=mysql with
  documented MariaDB/MySQL settings.
- [x] Write spec deltas for deployment, guests-draw, wishlist,
  and polls in this change.
- [x] Run `php artisan migrate` on a fresh MariaDB database
  and confirm all six tables exist — verified 2026-11-03 on
  real MariaDB in this environment, plus model checks:
  recipient/reserved-by relations, duplicate vote blocked by
  the unique constraint, deleting a guest cascades its
  wishlist items and nulls recipient_id.
- [x] Run `php artisan migrate:rollback` and confirm the six
  tables drop cleanly — verified 2026-11-03 (fresh-database
  caveat in the deployment spec delta applies).
- [ ] NOT in this change: switching controllers/pages from
  PartyStore (JSON) to the database. That is a later change.
