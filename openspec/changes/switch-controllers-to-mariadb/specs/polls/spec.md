# polls — delta

## ADDED Requirements

## Requirement: Polls and votes use their tables
Poll management SHALL write `polls` / `poll_options` rows,
and voting SHALL write or update a `votes` row, enforced by
the UNIQUE(poll_id, guest_id) constraint. Counts,
percentages, and my_vote SHALL be computed from those
tables, never from voter names.

#### Scenario: Vote from the database
- Given a poll in the database, when a guest votes, then
  exactly one vote row exists for that guest and poll,
  and changing the vote updates that row.
