# pin-reveal — delta

## ADDED Requirements

## Requirement: Entry and reveal use row locks, not file locks
`enter` and `reveal` SHALL run inside a database transaction
with a row lock on the guest row, replacing the JSON file
lock. All state transitions (claimed, revealed, pin_hash)
and all error codes SHALL be identical to the JSON version.

#### Scenario: Two devices race on one name
- Given an unclaimed guest, when two devices enter the
  same name at the same time, then exactly one succeeds
  and the other gets `already_claimed` after the PIN check,
  never a second recipient.
