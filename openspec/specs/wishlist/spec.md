# wishlist

## Requirement: Items are text, link, and/or image
A wishlist item SHALL have text, an optional link, optional
notes, and an optional image. Its kind SHALL be derived as
image > link > text. A link and a screenshot SHALL be allowed
to coexist.

#### Scenario: Add an item
- Given a guest on their wishlist, when they add a link item
  with a screenshot, then both the link and the image are kept.

## Requirement: Reserve is anonymous
Reserve SHALL show only "Reserved" to everyone except the
reserver, never who reserved. Only the reserver SHALL be able
to unreserve. Nobody SHALL be able to reserve their own items.

#### Scenario: Anonymous reserve
- Given guest B's item, when guest A reserves it, then every
  other guest sees only "Reserved", A sees "reserved by me",
  and only A can unreserve it.
- Given a guest's own item, when they try to reserve it, then
  the reserve is refused.

## Requirement: Board search matches nickname and alias
Board entries SHALL carry aliases, so the wishlists search
SHALL match name, nickname, and alias like Who does, and show
"(also: …)" aliases.

#### Scenario: Board search by alias
- Given a board owner with alias "Bobster", when a guest
  searches "bobster" on Wishlists, then that owner's list is
  found.

## Requirement: Wishlist writes verify the PIN
Wishlist add, edit, delete, and reserve SHALL verify the
guest PIN. Removing a guest SHALL also remove their wishlist.

#### Scenario: Wishlist after guest removal
- Given a guest with wishlist items, when the host removes
  that guest, then their wishlist is gone from the board.

## Requirement: Image limits are specified, not guessed
[DECISION] Keep image uploads and AVIF conversion, or drop
images for v1? The chosen answer SHALL be written here with
its exact limits (frontend size, server cap, formats) before
the wishlist slice is built. Do not copy limits from memory.
