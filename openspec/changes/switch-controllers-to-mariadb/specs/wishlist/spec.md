# wishlist — delta

## ADDED Requirements

## Requirement: Wishlist reads and writes use wishlist_items
Wishlist add, delete, and reserve SHALL write
`wishlist_items` rows (guest_id, text, link, notes, kind,
image, reserved_by_id) inside transactions, with row locks
on reserve. The board SHALL be built from the database,
with reserved / reserved_by_me computed from
reserved_by_id, never exposing the reserver id.

#### Scenario: Reserve from the database
- Given a wishlist item row, when another guest reserves
  it, then reserved_by_id is set inside a transaction, and
  every other guest sees only "Reserved".
