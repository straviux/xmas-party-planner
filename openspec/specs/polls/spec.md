# polls

## Requirement: Multiple polls, one vote per guest per poll
The system SHALL support multiple polls. Each guest SHALL
have one vote per poll, changeable until the poll is closed.

#### Scenario: Vote and change vote
- Given an open poll, when a guest votes, then their vote is
  recorded; when they vote for another option before closing,
  then their vote moves to the new option.

## Requirement: Results show counts only
Poll results SHALL show counts and percentages only, never
voter names. Closing a poll SHALL freeze its result;
reopening SHALL allow changes again.

#### Scenario: Closed poll
- Given a closed poll, when a guest tries to change their
  vote, then the change is refused and the result is unchanged.

## Requirement: Host manages polls, guests only vote
Poll create, edit, close, reopen, and delete SHALL require
host sign-in. Guest reads SHALL return empty data, not a 404,
when no party exists.

#### Scenario: Guest cannot create a poll
- Given a guest (not signed in as host), when they try to
  create a poll, then the request is refused.

## Requirement: Poll count is open
[DECISION] Keep multiple polls, or is one theme poll enough
for rebuild v1? The chosen answer SHALL be written here
before the polls slice is built.
