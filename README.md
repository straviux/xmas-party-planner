# Christmas Party — PHP for Apache (Vue 3, DaisyUI, Lucide, Material 3 Expressive)

You deploy this, not Muse. No Node, no database server, no build step.
Just PHP on Apache (or any PHP host) and this folder.

## What it is

A general Christmas party app:
- **Secret Santa** — one QR for everyone, find your name, reveal once.
  Searchable name pick (display name + nickname + aliases, normalised and
  fuzzy) so spelling can't mismatch. A name is marked claimed in the
  store the moment it reveals; rescanning with the same name only says
  "already revealed", with no recipient.
- **Wishlist posting** — guests post what they'd like as plain text, a
  link, or a screenshot (image upload, under about 2 MB, stored with
  the item); givers check their recipient's list after revealing, and
  can anonymously reserve an item so gifts don't duplicate (reserve
  shows "Reserved", never who).
- **Christmas theme voting poll** — host sets question/options, guests
  vote once each (changeable until closed), results are counts only.

## Files in this folder

- `index.html` — Guided flow (not tabs): Home with Santa, then a
  guest journey (Who → Reveal → Gifts → Vote → Done) and a separate
  host flow (Setup → Dashboard → Poll setup). Vue 3 (CDN, no build),
  DaisyUI, Lucide, Material 3 Expressive styling, layout optimized
  for mobile view.
- Santa mascot — Santa, your party guide, embedded directly in
  `index.html` as a data URL (the GitHub snapshot push rejects
  binary PNG files, so there is no separate `santa-mascot.png`
  in the repo — the image still shows everywhere, with no extra
  file to upload). He greets guests on Home, hints at each guest
  step, and celebrates on the Done screen.
- `api.php` — server side: draw, one-time claims, wishlists, poll,
  host dashboard. No database. Everything is stored in `data.php`
  (created on setup). That file starts with a PHP exit guard, so
  opening it in a browser shows nothing, and `.htaccess` denies it
  as a second layer.
- `.htaccess` — Apache protection for `data.php` (ignored harmlessly
  on Nginx). `api.php` must still be reachable.
- `.gitignore` — keeps `data.php` out of the repo; it contains the draw.

## Guest flow (what guests actually do)

1. **Home** — Santa greets them. Tap *I'm a guest*.
2. **Who** — search once, tap your exact entry, confirm “Is this
   you?”, PIN once if you have one. Name and PIN are remembered
   for this session, so you don't pick your name again for
   wishlists or the poll.
3. **Reveal** — see your recipient once, note it down. A “Buying
   for” card stays pinned while this page is open, so you don't
   lose the name if you tap away. A fresh scan on another device
   still only says “already revealed”.
4. **Gifts** — your recipient's wishlist first (Reserve in one tap),
   then your own wishlist (add text, link, or screenshot), with
   everyone's wishlists available to browse if you want them.
5. **Vote** — theme poll, then a Done screen with Santa and a
   summary (buying for, reserved, your wishlist, your vote).

If a name was already revealed on another device, the flow still
lets that guest do wishlist and poll steps, and they can pick
their recipient manually in the Gifts step to see that list.

## Deploy (Apache / PHP host)

1. Upload the contents of this folder to your website, e.g.
   `https://yoursite.com/christmas/` — keep `index.html` and
   `api.php` in the same folder.
2. Your hosting needs PHP (most cPanel / WordPress hosts have it).
   No SQLite extension, no Node, no database needed.
3. The folder must be writable by PHP so it can create `data.php`
   on setup. If setup says it could not save, check folder write
   permissions.
4. Open that URL in your browser, tap **Host**.
5. Set a party name, optional gift budget, and a host PIN (4+
   characters), add guests (name, nickname, aliases, optional
   guest PIN), then **Draw names & lock the draw**.
6. In the Host dashboard (host PIN) you get the **single QR code** —
   print it, everyone scans the same one. It points at the root URL
   of wherever you deploy this.

Use HTTPS in front of it (your host's TLS), so PINs and reveals
aren't sent in plain text.

## Important: this is a fresh start, not a migration

The previous Node version stored its draw in `data.db`. That file
does NOT carry over to this PHP version — the stores are different
formats and the PIN hashes are different. After uploading the PHP
version, redo setup in the Host tab (party, guests, draw) before
guests scan the QR. If anyone already revealed in the Node version,
they will need to reveal again in the PHP version.

## Host controls

- Host dashboard (blind): who has / hasn't claimed, never who got whom.
- Reset this name: in person only, if someone closed the page before
  noting their recipient.
- Reset everything: deletes the draw, wishlists, poll, and all claims.
- Theme poll setup: question + options (one per line), reopen / close /
  delete. Closing highlights the winner.

## Security notes (honest limits)

- This is a party game, not banking. Host and guest PINs
  (password-hashed in `data.php`) protect against casual snooping,
  not a determined attacker with server file access.
- A screenshot cannot be prevented by any website. The reveal screen
  tells guests to note it down.
- After the party, delete the folder from your website, or use Reset
  everything. That removes the draw and all claims. Also delete any
  printed QR sheets.
