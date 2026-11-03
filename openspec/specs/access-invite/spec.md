# access-invite

## Requirement: Invite code is the only guest gate
The system SHALL admit a guest with only the party invite code.
No guest account, email, or sign-in form SHALL be required.

#### Scenario: Guest enters with a code
- Given a party exists with an invite code, when a guest enters
  the correct code, then they reach the Who search.
- Given the same party, when a guest enters a wrong code, then
  they are refused with a plain-word message and no guest data
  is shown.

## Requirement: Home page is guest-only
The home page SHALL offer guest entry and a muted "Host
sign-in" link, and SHALL NOT offer any way to create a party.

#### Scenario: Guest opens home
- Given a guest opens the home page, when they look for party
  creation, then no such action exists; host setup is only
  reachable via a manually entered host URL.

## Requirement: Who search matches name, nickname, and alias
The Who search SHALL match a guest by display name, nickname,
or alias, normalised for case, accents, and spaces, and fuzzy
for 1-letter typos on tokens of 3 or more characters. Results
SHALL show aliases as "(also: …)".

#### Scenario: Search by nickname and alias
- Given a guest "Robert Smith", nickname "Bobby", alias
  "Bobster", when a visitor searches "bobster" or "bobby",
  then Robert Smith is found.
- Given the same guest, when a visitor searches "Bobstr"
  (one letter off), then Robert Smith is still found.

## Requirement: No party yet is an empty state
Guest reads SHALL return empty data, not a 404, when no party
exists. Guest writes SHALL require a party.

#### Scenario: No party set up
- Given no party exists, when a guest opens the app, then they
  see a "no party yet" prompt, not an error page.

## Requirement: Guest identity persists, recipient does not
The session SHALL persist guest id + PIN across refresh. The
recipient SHALL never be stored in the session.

#### Scenario: Refresh after entry
- Given a guest entered but has not torn yet, when they
  refresh, then they return to the reveal page (not guest
  home) with the recipient still hidden.
