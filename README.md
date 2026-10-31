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
npm run startlocal
```

Leave that window open. `startlocal` starts Vite on
`http://127.0.0.1:5173` and Laravel on `http://127.0.0.1:8000` at the same
time (via `concurrently`). Open **`http://localhost:8000`** in the browser —
not the Vite URL directly; the Laravel page loads the Vite assets. Stop with
Ctrl+C (`concurrently` kills both servers with `-k`).

Windows Command Prompt (cmd) — same thing, no PowerShell syntax:

```
cd /d C:\path\to\xmas-party-planner-laravel
npm run startlocal
```

If `php` or `npm` is not recognised in cmd, install PHP/Node first and reopen
cmd. For phone testing on the same Wi-Fi, run
`php artisan serve --host=0.0.0.0 --port=8000` instead of the default host and
use the computer's LAN IP from `ipconfig` — Vite HMR over LAN is untested and
may need `VITE_HOST` adjustments.

## What is ported

- Party setup (name, gift budget in ₱ with ₱250/₱500/₱1000 presets, date).
- Host PIN only (Google login was removed from the old app and is not here).
  Default host PIN is **1234**: setup with a blank host PIN stores a 1234
  hash, and while the default is active every host action except PIN reset
  returns `default_pin_change_required` and the app shows a non-dismissable
  "Set a new host PIN" modal. A new host PIN is exactly 4 digits, never 1234.
- `InputOtp` component (type=password, exactly 4 digits, auto-advance on
  digit typed, backspace/paste support) for ALL PIN inputs.
- Guest flow: QR invite code (`?guest=1&code=CODE`, the QR still carries the
  code), Who search (normalised/fuzzy over display name + nickname +
  aliases), "Is this you?" confirm, entry lock (confirming locks the name
  server-side and returns the recipient; a wrong guest PIN does NOT lock; a
  second entry with a locked name gets `already_claimed` and goes to guest
  home with no recipient; only a host reset clears a lock).
- Wishlist: text/link/image (10 MB frontend limit, browser canvas AVIF
  conversion uploaded only if smaller, server-side GD AVIF conversion at
  quality 80 when available), anonymous reserve (only the reserver can
  unreserve, cannot reserve own items), public lists by owner name.
- Polls: multi-poll, create/edit/close/reopen/delete, one vote per guest per
  poll, changeable until closed, counts/percentages only.
- Host dashboard, desktop layout (1100 px wide, top pill nav — NOT the old
  mobile bottom dock): stats, blind claimed status (never recipient
  mappings), invite code with copy/regenerate, single QR, reset one name,
  reset everything. Host pages are reached by direct URL; the home page is
  guest-only (no host link, no "I'm a new host").
- Superadmin at `/superadmin` (no visible link in the app): first-visit
  username/password stored only as a hash on the server, party update, invite
  regenerate, host PIN set (4 digits, never 1234), reset one claim, unclaim
  all, redraw blocked while any name is claimed, poll close/reopen/delete,
  clear wishlists, reset everything. **Recipient mappings are never exposed,
  even to superadmin.**
- Guest pages stay mobile-friendly; host pages are desktop-optimised.
- Scan QR is design-only (placeholder viewfinder, camera never starts) and
  the temporary "Continue to guest page" bypass are carried over from the old
  app as-is; remove both when real scanning returns.

## Data storage — JSON files, not a database

Party data lives in **`storage/app/party.json`** and the superadmin account in
**`storage/app/superadmin.json`**, with file locking (`flock`) around
read-modify-write for race-sensitive actions (entry lock, reserve, vote) and
`chmod 0600` on the files. This is deliberate: it matches the old app's
JSON-file store model exactly, so draw/claim logic behaves the same. The
Laravel scaffold's default sqlite file is **not** used for party data.
`storage/app/` is outside `public/`, so the JSON files are never
web-accessible. The party data does NOT migrate from the old app
automatically: the old `data.php` JSON (after its PHP guard line) and the new
`storage/app/party.json` share the same JSON shape (party, budget, date,
invite_code, host_pin_hash, guests with `recipient_id`/`claimed`, wishlists,
polls), but copying it across is a manual step — do not claim it migrates by
itself, and do not copy a live `data.php` without explicit instruction.

## How Inertia changes behaviour vs the old app

- **Real paths, not hash routes.** Old URLs were `site/#/host/dashboard`,
  `site/#/superadmin`, etc. New URLs are `/host/dashboard`, `/superadmin`,
  etc. Old hash URLs redirect to the equivalent real path on load.
- **Guest/host identity persists across refresh in the Laravel session**
  (file driver), not in-memory only. The old app lost `guestMe` /
  `guestRecipient` / host sign-in on refresh. Here `guest_id` + `guest_pin`
  and `host_pin` live in the Laravel session. The recipient itself is
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
- **Superadmin helpers are implemented, not missing.** The old plain-PHP
  `api.php` called superadmin helper functions that were never defined in
  that file, so its superadmin actions would fatal when called. The Laravel
  port implements them correctly; this is a fix relative to the old app,
  not a behaviour regression.
- **Poll host-check bypass is preserved as-is.**
  `TEMP_POLL_HOST_CHECK_DISABLED = true` in `PartyStore`, so poll
  create/edit/close/reopen/delete do not require the host PIN in either
  version, until explicitly agreed otherwise.

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
  Everything else, including `storage/app/party.json` and
  `storage/app/superadmin.json`, must stay outside the web root.
- On the server: `composer install --no-dev --optimize-autoloader`, copy
  `.env.example` to `.env` and set a real `APP_KEY`
  (`php artisan key:generate`), `APP_URL` to the deployed origin,
  `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DRIVER=file` (do not
  leave it as `database` unless the database tables exist), and ensure
  `storage/` (especially `storage/app/` and `storage/framework/`) and
  `bootstrap/cache/` are writable by the web server user.
- Run `npm run build` (or `npm install` + `npm run build` on a build
  machine) so `public/build/` assets exist; `php artisan serve` and Vite dev
  are for local development only, not for Apache production.
- PHP requirements on the host: PHP 8.3 (or the Laravel version's minimum),
  `php-mbstring`, `php-xml`, `php-curl`, `php-sqlite3` (only if the scaffold
  database is used — it is not used for party data), `php-zip`, `php-gd`
  (for server-side AVIF conversion of wishlist screenshots; without GD,
  original uploads are stored as-is, same as the old app).
