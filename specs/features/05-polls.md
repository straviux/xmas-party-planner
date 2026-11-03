# 05 — Polls (SCAFFOLD — needs full review)

Status: scaffold only. Rules carried over from the current app.

## 1. Problem / user story

Guests vote on the Christmas theme (and any other party
questions the host adds), and everyone sees only the counts —
never who voted for what.

## 2. Scope

- In scope: multiple polls, host create/edit/close/reopen/
  delete, guest vote and change vote, results display.
- Out of scope: poll creation by guests, voter identity in
  results.

## 3. State machine / data

- Poll: question, options, open/closed, votes per option.
- One vote per guest per poll, changeable until the poll is
  closed. Closing freezes the result; reopening allows changes
  again.
- Results show counts and percentages only, never voter names.

## 4. API contract

To be completed in review. Known actions: `poll_get`,
poll create/edit/close/reopen/delete (host only), `poll_vote`
(guest PIN verified). No-party-yet is an empty state for reads,
never a 404.

## 5. UI behaviour

- Guest Vote page lists open polls with options and current
  counts; a guest's own choice is marked so they can change it
  while the poll is open.
- Host Polls page is a real table (question, status, votes,
  winner, actions) in the admin layout.

## 6. Acceptance criteria

[TODO — review pass: vote, change vote, closed poll refuses
changes, host closes/reopens, results show percentages, no
voter names anywhere.]

## 7. Edge cases

[TODO — review pass: poll with no votes, poll deleted after
votes, guest votes then is removed, tie for winner.]

## 8. Open decisions

- `[DECISION]` Keep multiple polls, or is one theme poll enough
  for the rebuild v1?
