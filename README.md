# Christmas Party Planner — Laravel + Inertia version

A Laravel + Inertia (Vue 3) port of the Christmas party planner (Secret Santa,
wishlists, polls). **This folder is for review only. The plain-PHP app is still
the live app — do not treat this version as a replacement until it has been
tested on a real machine.**

## Run it locally

Requirements: PHP 8.3+, Composer, Node 24+ / npm 10+.

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run startlocal
```

Leave that window open. `startlocal` starts Vite on
`http://127.0.0.1:5139` and Laravel on `http://127.0.0.1:8005` at the same
time (via `concurrently`). Open **`http://localhost:8005`** in the browser —
not the Vite URL directly; the Laravel page loads the Vite assets. Stop with
Ctrl+C (`concurrently` kills both servers with `-k`).

Windows Command Prompt (cmd) — same thing, no PowerShell syntax:

```
cd /d C:\path\to\xmas-party-planner-laravel
npm run startlocal
```

If `php` or `npm` is not recognised in cmd, install PHP/Node first and reopen
cmd. For phone testing on the same Wi-Fi, run
`php artisan serve --host=0.0.0.0 --port=8005` instead of the default host and
use the computer's LAN IP from `ipconfig` — Vite HMR over LAN is untested and
may need `VITE_HOST` adjustments.

## What is ported

- Party setup (name, gift budget in ₱ with ₱250/₱500/₱1000 presets, date).
- Host login is **username/password — NOT a PIN, NOT email.** The host
  signs in on the host pages with the single admin/host user created by
  `php artisan db:seed` (see "Host login" below). There is no separate
  superadmin login: `/superadmin` redirects to `/host/dashboard`.
- `InputOtp` component (type=password, exactly 4 digits, auto-advance on
  digit typed, backspace/paste support) for **guest** PIN inputs only
  (per-guest wishlist/entry PINs). The host login uses plain text
  username + password inputs, not InputOtp.
- Guest flow: QR invite code (`?guest=1&code=CODE`, the QR still carries the
  code), Who search (normalised/fuzzy over display name + nickname +
  aliases), "Is this you?" confirm, entry lock (confirming locks the name
  server-side and sets/verifies the guest PIN, but does NOT reveal the
  recipient — the guest is sent to the reveal page and only the tear strip
  (`reveal`) shows the name, once; a wrong guest PIN does NOT lock; a
  second entry with a locked name gets `already_claimed`; only a host
  reset clears a lock, and it also clears `revealed` so the name can be
  revealed again).
- Guest PIN (created by the guest, not the host): on first entry, a guest
  with no code set creates a 4-digit PIN (entered twice, must match) and
  that PIN becomes their profile code from then on — returning guests
  must enter it to get back into their profile (claimed names are
  verified via `guest_verify_pin`, or create one via `guest_set_pin` if
  they claimed before PINs existed). A host-set PIN, if one was given at
  setup/add, is entered instead of created. Host-set PINs are optional:
  if left blank, the guest creates their own on first entry.
- Wishlist: text/link/image (10 MB frontend limit, browser canvas AVIF
  conversion uploaded only if smaller, server-side GD AVIF conversion at
  quality 80 when available), anonymous reserve (only the reserver can
  unreserve, cannot reserve own items), public lists by owner name.
- Polls: multi-poll, create/edit/close/reopen/delete, one vote per guest per
  poll, changeable until closed, counts/percentages only.
- Host area, desktop admin layout (1100 px wide, top pill nav + prominent
  Host header with party name, signed-in username and Sign out — NOT the old
  mobile bottom dock): host pages are AUTH-GATED — unauthenticated visits to
  /host/* get no party data from the server and see only a "Host sign-in
  required" screen (username/password form); host nav buttons are hidden
  until signed in, and sign-out goes to the guest home page. Dashboard order:
  party card (name/date/budget/invite/QR), locked/awaiting progress, claimed
  list with reset, quick actions, Admin controls, Danger zone — blind claimed
  status only, never recipient mappings. Host pages are reached by direct URL
  or the discreet "Host sign-in" link on the home page (no "I'm a new host",
  no party creation from home).
- Party settings (route /host/setup): "Create party" when no party exists
  (party details + guest list in one flow, saved together) vs "Party
  settings" when a party exists (party details only — guests are NOT changed
  or cleared here).
- Guests page: REAL saved-party guest management (display, nickname,
  aliases, PIN set, Locked/Not yet, Reset one) via host_guest_add/update/
  remove — every change re-draws the Secret Santa automatically, and changes
  are blocked (409) once any name is locked until claims are reset. The old
  setup-draft guest list and its localStorage persistence are gone.
- Host dashboard Admin controls (host only, formerly superadmin-only):
  redraw (blocked while any name is claimed), clear all wishlists, unclaim
  all, change password, plus the existing reset one name / reset everything,
  party update, invite regenerate, and poll create/edit/close/delete.
  **Recipient mappings are never exposed anywhere in the UI.**
- Guest pages stay mobile-friendly; host pages are desktop-optimised.
- Scan QR is design-only (placeholder viewfinder, camera never starts) and
  the temporary "Continue to guest page" bypass are carried over from the old
  app as-is; remove both when real scanning returns.

## Host login

Host login is username/password only — not a PIN, not email.

```sh
php artisan migrate --seed
```

This creates ONE host/admin user via `DatabaseSeeder` (`firstOrCreate`, so
re-seeding does NOT reset a password you changed):

- **Username:** from `ADMIN_USERNAME` in `.env`, default `admin`
- **Password:** from `ADMIN_PASSWORD` in `.env`, default `admin1234`
- **Name:** `Admin` — **Email:** `admin@localhost` (email is never used
  for login, it only fills the `users` table)

**Change the password immediately after first sign-in** via the host
dashboard Admin controls → Change password (current / new / confirm).
Change `ADMIN_USERNAME` / `ADMIN_PASSWORD` in `.env` before deploying so a
fresh seed does not recreate the default account. The password is stored
only as a hash (the User model's `hashed` cast). Setup (creating a party)
requires the host to be signed in first. Unauthenticated host-page visits
return empty host props from the server, and the client renders only the
sign-in screen — no dashboard/setup/poll/guests content, no host data.

## Data storage — JSON files, not a database

Party data lives in **`storage/app/party.json`** only (there is no
superadmin file any more), with file locking (`flock`) around
read-modify-write for race-sensitive actions (entry lock, reserve, vote) and
`chmod 0600` on the files. This is deliberate: it matches the old app's
JSON-file store model exactly, so draw/claim logic behaves the same. The
Laravel scaffold's default sqlite file is **not** used for party data.
`storage/app/` is outside `public/`, so the JSON file is never
web-accessible. The host login account lives in the Laravel `users` table
(see "Host login" below), not in a JSON file. The party data does NOT
migrate from the old app automatically: the old `data.php` JSON (after its
PHP guard line) and the new `storage/app/party.json` share the same JSON
shape (party, budget, date, invite_code, guests with
`recipient_id`/`claimed`, wishlists, polls), but copying it across is a
manual step — do not claim it migrates by itself, and do not copy a live
`data.php` without explicit instruction.

## How Inertia changes behaviour vs the old app

- **Real paths, not hash routes.** Old URLs were `site/#/host/dashboard`,
  `site/#/superadmin`, etc. New URLs are `/host/dashboard`, `/host/setup`,
  `/host/guests`, `/host/poll`, etc. `/superadmin` redirects to
  `/host/dashboard`. Old hash URLs redirect to the equivalent real path
  on load.
- **Guest/host identity persists across refresh in the Laravel session**
  (file driver), not in-memory only. The old app lost `guestMe` /
  `guestRecipient` / host sign-in on refresh. Here `guest_id` + `guest_pin`
  and `host_user_id` / `host_username` live in the Laravel session. The recipient itself is
  deliberately NOT stored in session — after a refresh, a locked guest
  restores to guest home (claimed path) without the recipient, matching the
  old claimed-guest behaviour. Guest logout and host sign-out clear the
  server session (`guest_logout` / `host_logout`).
- **CSRF + session.** API calls are web routes with Laravel CSRF protection
  (axios sends the XSRF cookie token), unlike the old stateless `api.php`.
  This is a security improvement, but it means the API cannot be called from
  a plain static host without the Laravel session/CSRF flow.
- **Entry-lock race handling is stronger.** `PartyStore::mutate()` holds one
  flock across read-modify-write for `enter`/`reveal`, `wishlist_reserve`
  and `poll_vote`, whereas the old app locked only during the write.
  Externally the same (first writer locks, second gets `already_claimed` /
  `already_reserved` / vote overwrite), but concurrent entries are less
  likely to interleave.
- **Superadmin is removed.** There is no `/superadmin` page, no
  `superadmin_*` API actions, and no `superadmin.json` file. Its controls
  moved to the host dashboard (see above) and require the host login.
- **Poll actions now require the host.**
  `TEMP_POLL_HOST_CHECK_DISABLED = false` in `PartyStore`, so poll
  create/edit/close/reopen/delete require the host login. Guest
  `poll_vote` does NOT require the host.

## Not ported faithfully

- **Scor / Santa mascot.** The old app's full Scor CSS component (a temporary
  swap) was not ported — the port uses a simplified hero (Lucide Gift icon +
  chat bubble) instead. This is a visible difference, reported honestly
  rather than claimed as pixel parity. The disabled animated WebM mascot was
  also not ported (it was disabled in the old app too).
- Everything else above is ported; anything found missing during testing
  should be added to this list.

## Deploying to an Apache host (later, not done here)

- Document root must be the Laravel `public/` folder (not the project root).
  Everything else, including `storage/app/party.json`, must stay outside
  the web root.
- On the server: `composer install --no-dev --optimize-autoloader`, copy
  `.env.example` to `.env` and set a real `APP_KEY`
  (`php artisan key:generate`), `APP_URL` to the deployed origin,
  `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DRIVER=file` (do not
  leave it as `database` unless the database tables exist),
  `ADMIN_USERNAME` / `ADMIN_PASSWORD` (used by the seeder), and run
  `php artisan migrate --seed` to create the host user, and ensure
  `storage/` (especially `storage/app/` and `storage/framework/`) and
  `bootstrap/cache/` are writable by the web server user.
- Run `npm run build` (or `npm install` + `npm run build` on a build
  machine) so `public/build/` assets exist; `php artisan serve` and Vite dev
  are for local development only, not for Apache production.
- PHP requirements on the host: PHP 8.3 (or the Laravel version's minimum),
  `php-mbstring`, `php-xml`, `php-curl`, `php-sqlite3` (the `users` table
  for host login lives in sqlite), `php-zip`, `php-gd`
  (for server-side AVIF conversion of wishlist screenshots; without GD,
  original uploads are stored as-is, same as the old app).
