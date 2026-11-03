# polls — delta

## ADDED Requirements

## Requirement: Polls, options, and votes live in their own tables
Polls SHALL be stored in `polls` (party_id, question, open),
options in `poll_options` (poll_id, text, sort_order), and
votes in `votes` (poll_id, guest_id, poll_option_id) with a
UNIQUE constraint on (poll_id, guest_id).

#### Scenario: One vote per guest
- Given a guest who already voted in a poll, when a second
  vote row for the same guest and poll is inserted, then the
  database refuses it, and changing a vote updates the
  existing row instead.
