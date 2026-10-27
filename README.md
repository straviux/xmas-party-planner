# Christmas Party — Secret Santa, Wishlists & Theme Poll (deploy it yourself)

A general Christmas party app, not just Secret Santa:
- **Secret Santa** — one QR for everyone, find your name, reveal once.
- **Wishlist posting** — guests post what they'd like; givers check their
  recipient's list after revealing, and can anonymously reserve an item
  so gifts don't duplicate.
- **Christmas theme voting poll** — host sets the question/options,
  guests vote once each (changeable until closed), results are counts only.

You deploy this, not Muse. No login, account, or access to your website is needed
from Muse. Upload these files, set it up, print one QR code.

## Files in this folder

- `index.html` — Secret Santa, Wishlist, Theme Poll, and Host tabs. No build step.
- `api.php` — server side: draw, one-time claims, wishlists, poll, host dashboard.
  No database. Everything is stored in `data.php` (created on setup). That file
  starts with a PHP exit guard, so opening it in a browser shows nothing, and
  `.htaccess` denies it as a second layer.
- `.htaccess` — Apache protection for `data.php` (ignored harmlessly on Nginx).

## Deploy (2 minutes)

1. Upload the contents of this folder to your website, e.g.
   `https://yoursite.com/secret-santa/` — keep `index.html` and `api.php`
   in the same folder.
2. Your hosting needs PHP for the server version (most cPanel / WordPress
   hosts have it). The folder must be writable by PHP so it can create
   `data.php` on setup.
3. Open that URL in your browser, tap **Host**.
4. Set a party name, optional gift budget, and a host PIN (4+ characters).
5. Add guests, one row each:
   - **Name** — the display name guests will see.
   - **Nickname** — shown next to the name, e.g. Alex.
   - **Aliases** — comma-separated extra searchable spellings, e.g.
     `Al, Xander, Alex Smith`. A guest can search any of these.
   - **Guest PIN** — optional. If set, the guest must enter this private
     PIN after picking their name. Recommended if guests will reveal
     remotely, before the party, where someone else could try their name.
6. Tap **Draw names & lock the draw**. Nobody gets themselves.
7. In the Host dashboard (host PIN), you get the **single QR code**.
   Print it, or display it at the party. Everyone scans the same QR.

## How guests use it (and why names don't mismatch)

1. Scan the one QR code — it just opens this page.
2. Search any part of their name or nickname. Search is normalised
   (case, accents, spaces ignored) and fuzzy for 1-letter typos, and it
   matches display name, nickname, and all aliases.
3. Tap their **exact entry** from the list. They never type a name to
   reveal, so a spelling difference cannot create a mismatch or a
   second, wrong entry.
4. Confirm screen shows: full name + nickname + aliases, so they can
   check "is this really me?" *before* anything is burned. If it's the
   wrong person, they go back — nothing is claimed yet.
5. Confirm → recipient is shown once, and the name is marked claimed
   on the server immediately.
6. Rescan + same name → "Already revealed", no recipient shown.

Claimed names stay visible in the list as "Already revealed" (disabled),
so a guest can tell they already did it, but cannot see the recipient again.

## Wishlist posting

In the **Wishlist** tab, a guest searches and taps their own exact name,
then adds items (item, optional link, optional size/colour notes).
Only the owner can add/delete their own items (guest PIN checked if set).

Everyone can browse all wishlists by owner name. After a Secret Santa
reveal, the giver opens their recipient's list here.

**Reserve (anonymous):** tap Reserve on someone else's item. It shows
as "Reserved" to everyone, but never says who reserved it. Only the
person who reserved it can unreserve it. This stops two people buying
the same gift, without revealing who the giver is. You cannot reserve
your own items.

## Christmas theme voting poll

In the **Host** tab, under Theme poll setup, the host sets a question
(default: "What should our Christmas theme be?") and options, one per
line (e.g. Ugly Sweater, Winter Wonderland, Pajama Party, Red & Gold).

In the **Theme Poll** tab, a guest searches/taps their name, then votes.
- One vote per guest, changeable until the host closes the poll.
- Results are live counts and percentages only — never who voted for what.
- Host can reopen, close (final winner highlighted), or delete the poll.

## Host dashboard (blind)

With the host PIN you see only: who has / hasn't claimed, and when.
You never see who got whom in the dashboard — the draw is stored
server-side and only a single reveal returns a recipient.

- **Reset this name**: use only in person, if someone closed the page
  before noting their recipient. It makes that one name revealable again.
- **Reset everything**: deletes the draw and all claims, so you can start over.
  Only do this before anyone has bought gifts.

## If your hosting is static-only (no PHP)

If `api.php` is not available, the page falls back to **local mode**
and shows a banner saying so. In local mode the draw and claims live
in that one browser/device's storage — perfect for a shared tablet at
the party, but it will NOT remember claims across guests' own phones.
For guests on their own phones, use the PHP version above.

## After the party

Delete the folder from your website, or use Reset everything.
That removes the draw and all claims. Also delete any printed QR sheets.

## Security notes (honest limits)

- This is a party game, not banking. The host PIN and optional guest
  PINs protect against casual snooping, not a determined attacker with
  server file access. Anyone who can read files on your server could
  read the draw — that's the host's trust model, same as any
  Secret Santa site.
- Always use HTTPS on your site, so PINs and reveals aren't sent
  in plain text.
- A screenshot cannot be prevented by any website. The reveal screen
  tells guests to note it down and tap "I've noted it down".
