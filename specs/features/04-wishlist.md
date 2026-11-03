# 04 — Wishlist (SCAFFOLD — needs full review)

Status: scaffold only. Rules carried over from the current app.

## 1. Problem / user story

Guests post what they want, and other guests can anonymously
reserve an item so nobody buys the same thing twice — without
knowing who reserved what.

## 2. Scope

- In scope: my wishlist (add/edit/delete items), everyone's
  wishlists (browse, search by name/nickname/alias), anonymous
  reserve/unreserve, item images.
- Out of scope: buying, payments, price tracking.

## 3. State machine / data

- Item: text, optional link, optional notes, optional image.
  Kind is derived: image > link > text. Link and screenshot can
  coexist.
- Reserve: an item is either free or reserved. The reserver is
  recorded server-side but never shown to anyone except the
  reserver's own "reserved by me" state. Only the reserver can
  unreserve. Nobody can reserve their own items.
- Removing a guest also removes their wishlist (see `06`).

## 4. API contract

To be completed in review. Known actions: wishlist add/edit/
delete, `wishlist_list` (board entries carry aliases so the
board search matches nickname/alias, like Who does), reserve/
unreserve. Wishlist writes verify the guest PIN.

## 5. UI behaviour

- "Add to my wishlist" modal: full-width dashed file picker
  ("Choose a screenshot", shows the chosen file name), Cancel
  and Add buttons with icons. No native `alert()`/`confirm()`;
  one global toast for feedback.
- Board search matches name, nickname, and alias, and shows
  "(also: …)" aliases.
- Frontend upload limit 10 MB; server base64 cap and image
  formats (PNG/JPEG/GIF/WebP/AVIF, with AVIF conversion) to be
  specified in the review pass — do not guess them from memory
  when writing the final spec, check the current code.

## 6. Acceptance criteria

[TODO — review pass: add text/link/image item, reserve shows
only "Reserved" to others, reserver can unreserve, own items
cannot be reserved, delete item, search by alias.]

## 7. Edge cases

[TODO — review pass: item reserved then deleted, guest removed
while their items are reserved, image too large, link without
image, empty wishlist states.]

## 8. Open decisions

- `[DECISION]` Keep image uploads and AVIF conversion, or drop
  images to keep the rebuild simpler?
