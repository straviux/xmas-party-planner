# guests-draw — delta

## ADDED Requirements

## Requirement: Setup and draw are database transactions
Party setup SHALL create a `Party` row, `Guest` rows, and a
derangement in `recipient_id` inside one transaction.
`already_set_up` SHALL be returned when a party row already
exists. Guest add/edit/remove SHALL re-draw inside a
transaction while unclaimed, and SHALL be blocked with a
409 while any guest is claimed.

#### Scenario: Setup in the database
- Given no party row, when the host sets up a party, then
  one party row and one guest row per guest exist, with a
  derangement in recipient_id.
- Given an existing party row, when the host tries to set
  up again, then they get `already_set_up` and nothing
  changes.
