# access-invite — delta

## ADDED Requirements

## Requirement: Guest reads come from the database
`list` SHALL read guests from the `guests` table and return
the same public shape as before (id, display, nickname,
aliases, claimed, needs_pin, revealed), never recipient
mappings and never pin_hash. No party row SHALL mean empty
reads, never a 404.

#### Scenario: List from the database
- Given a party in the database, when a guest opens Who,
  then the list shows the database guests with no
  recipient information.
- Given no party row, when a guest opens the app, then
  they see a "no party yet" prompt, not an error page.

## MODIFIED Requirements

## Requirement: Guest identity persists, recipient does not
The session SHALL persist guest id + PIN across refresh.
The guest id is now a numeric database id, and the frontend
SHALL treat it as opaque. The recipient SHALL never be
stored in the session.

#### Scenario: Session with a numeric id
- Given a guest entered in the database version, when they
  refresh, then their session still identifies them, and
  Who, board, and vote actions all resolve to the same
  guest.
