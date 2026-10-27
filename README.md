# Christmas Party — JavaScript only + SQLite (Vue 3, DaisyUI, Lucide, Material 3 Expressive)

You deploy this, not Muse. No PHP, no database server, no build step, no
npm dependencies. Just Node.js (>= 22, for `node:sqlite`) and this folder.

## What it is

A general Christmas party app:
- **Secret Santa** — one QR for everyone, find your name, reveal once.
  Searchable name pick (display name + nickname + aliases, normalised and
  fuzzy) so spelling can't mismatch. A name is marked claimed in SQLite
  the moment it reveals; rescanning with the same name only says
  "already revealed", with no recipient.
- **Wishlist posting** — guests post what they'd like as plain text, a
  link, or a screenshot (image upload, under about 2 MB, stored in
  SQLite with the item); givers check their recipient's list after
  revealing, and can anonymously reserve an item so gifts don't
  duplicate (reserve shows "Reserved", never who).
- **Christmas theme voting poll** — host sets question/options, guests
  vote once each (changeable until closed), results are counts only.

## Stack

- Frontend: **Vue 3** (CDN, no build), **DaisyUI** components, **Lucide**
  icons, styled with a **Material Design 3 Expressive** concept: big
  pill buttons and 28px rounded cards, bold festive colour
  (red primary, green secondary, gold tertiary), large expressive type,
  and springy hover/press motion.
- Backend: **JavaScript only** — `server.js` on Node, using the built-in
  `node:sqlite` (`DatabaseSync`). Zero npm dependencies.
- Database: **SQLite** file `data.db`, created on first setup. It lives
  at the repo root, OUTSIDE `public/`, so the static server can never
  serve it for download. The API is the only thing that reads it.

## Run it

```bash
node server.js
# Christmas Party app running at http://localhost:3000
```

Open that URL, tap **Host**, set a party name, optional budget, and a
host PIN (4+ characters), add guests (name, nickname, aliases,
optional guest PIN), then **Draw names & lock the draw**.

In the Host dashboard (host PIN) you get the **single QR code** — print
it, everyone scans the same one. It points at the root URL of wherever
you deploy this.

## Deploy it yourself

1. Upload this folder to a host that runs Node.js (a VPS, a Node host,
   or your own server — not a static-only host, because the API and
   SQLite need a running Node process).
2. Run `node server.js` (or set `PORT` and `DB_PATH` env vars if your
   host provides them, e.g. `PORT=8080 node server.js`).
3. Use HTTPS in front of it (reverse proxy / your host's TLS), so PINs
   and reveals aren't sent in plain text.
4. Make sure the folder is writable so Node can create `data.db`.
   `data.db` is gitignored — never commit it, it contains the draw.

## Host controls

- Host dashboard (blind): who has / hasn't claimed, never who got whom.
- Reset this name: in person only, if someone closed the page before
  noting their recipient.
- Reset everything: deletes the draw, wishlists, poll, and all claims.
- Theme poll setup: question + options (one per line), reopen / close /
  delete. Closing highlights the winner.

## Security notes (honest limits)

- This is a party game, not banking. Host and guest PINs (scrypt-hashed
  in SQLite) protect against casual snooping, not a determined attacker
  with server file access.
- A screenshot cannot be prevented by any website. The reveal screen
  tells guests to note it down.
- After the party, stop the server and delete `data.db` to clear the draw.
