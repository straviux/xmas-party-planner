# guests-draw — delta

## ADDED Requirements

## Requirement: Guests live in the guests table
Guest records SHALL be stored in the `guests` table with
party_id, display, nickname, aliases (JSON), pin_hash,
recipient_id (self-reference, nullable), claimed, claimed_at,
and revealed, as defined in the change design.

#### Scenario: Guest row
- Given a party, when a guest row is created, then it has
  claimed=false and revealed=false and no recipient is
  exposed by the row itself.
