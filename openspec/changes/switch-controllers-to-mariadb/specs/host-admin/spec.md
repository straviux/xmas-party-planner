# host-admin — delta

## ADDED Requirements

## Requirement: Host actions run on the database
`host_status`, party settings, guest management, redraw,
Reset one, Unclaim all, Reset PIN, clear wishlists, invite
regen, and reset everything SHALL run against the database
tables, with the same effects and the same blocks as the
JSON version. Reset everything SHALL delete the party row
and rely on cascade deletes for guests, wishlist items,
polls, and votes.

#### Scenario: Reset everything from the database
- Given a party with guests, items, polls, and votes, when
  the host resets everything, then the party row is gone
  and no orphan guest, item, poll, or vote rows remain.
