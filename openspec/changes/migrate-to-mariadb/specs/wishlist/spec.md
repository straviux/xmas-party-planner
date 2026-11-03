# wishlist — delta

## ADDED Requirements

## Requirement: Wishlist items live in wishlist_items
Wishlist items SHALL be stored in `wishlist_items` with
guest_id, text, link, notes, kind, image (longText), and
reserved_by_id (nullable, the reserver's guest id).

#### Scenario: Reserved item row
- Given a wishlist item, when a guest reserves it, then the
  row's reserved_by_id is set to that guest, and no other
  guest's view may expose that id — only reserved /
  reserved_by_me, computed in the app layer.
