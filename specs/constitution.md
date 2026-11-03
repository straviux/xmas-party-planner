# Constitution — non-negotiable rules

These rules come from Arc's explicit design decisions for the current
app. They are carried into the rebuild unchanged. A feature spec that
contradicts this file is wrong; change this file deliberately or
change the feature spec.

## Product

1. This is a general Christmas party app, not Secret Santa-only. It
   includes Secret Santa via a single QR, wishlist posting, and a
   Christmas theme voting poll.
2. It is for one party at a time, set up by a host. There is no
   multi-party / multi-tenant model in v1.

## Guest access — the rule that must never regress

3. NO per-guest sign-in, ever. Some guests (especially elders) find
   sign-in too hard. The invite code is the only gate for guests.
4. Anyone with the invite code can search a name and add/edit that
   name's wishlist. This is deliberate, not a security hole to fix:
   the party is a trusted, festive context.
5. A guest searches and picks a name. There is no guest account, no
   guest email, no guest password beyond the per-name 4-digit PIN
   defined in `03-pin-and-reveal.md` (which is a profile code, not a
   sign-in system).
6. The home page is guest-only. Guests cannot create a party from
   home. Host setup and management are reached only via manually
   entered host URLs.

## Secret Santa

7. One QR / one invite code for all guests.
8. A guest finds their own name (by display name, nickname, or alias)
   and confirms it once. Confirming locks that name to them.
9. The recipient is shown exactly once, via the tear reveal
   (see `03-pin-and-reveal.md`). The recipient is never stored in
   the guest's session, and never shown to the host — not even in
   host or admin views. The draw itself is a derangement (nobody
   buys for themselves; with two people, they swap).

## Host access

10. Host access is username + password only. Not a PIN, not email.
11. Host controls live on host pages only: party settings, guest
    management, polls, redraw, resets, danger zone. Guest pages
    never expose host controls.

## UI

12. No emojis in app UI. Lucide icons only, and only Lucide names
    that actually exist in the installed icon package.
13. No text shadows in app UI (the tear strip and Scor bubble are
    the explicit, named exceptions only where a spec says so).
14. Titles go inside cards, not above them. Action buttons go inside
    cards, compactly sized.
15. Guest pages are mobile-first (guest dock navigation). Host pages
    are desktop-first (fixed admin sidebar, horizontal scroll on
    narrow windows, no mobile reflow).
16. Wording must be readable by non-technical users. Anything
    ambiguous gets a plain-word label.

## Deployment and testing

17. Arc deploys the app himself, to his own host. Muse does not
    deploy and does not need his hosting login.
18. All visual / acceptance testing is on Arc's end. Muse does
    code-level checks only (build, lint, targeted API smoke tests
    on throwaway data). The real party data is never used for tests.
19. Secrets stay put: host credentials live only on the server,
    guest PINs are stored hashed only, never in plain text, and no
    credential is ever committed to the repo.
20. Party data does not migrate automatically from an old version.
    If a migration is ever wanted, it is a separate, explicit spec.
