# guests-draw

## Requirement: Guest records carry the fields the PIN spec needs
A guest SHALL have id, display, nickname, aliases, pin_hash
(null until a PIN exists), recipient_id, claimed, claimed_at,
and revealed.

#### Scenario: New guest record
- Given the host adds a guest with no PIN, when the record is
  created, then pin_hash is null, claimed is false, and
  revealed is false.

## Requirement: Draw is a derangement, hidden from everyone
The draw SHALL assign every guest a recipient_id such that no
guest gets themselves, and recipient mappings SHALL never be
exposed in any API response or host view.

#### Scenario: Draw with three guests
- Given guests A, B, and C, when the draw runs, then no guest
  has themselves as recipient, and the host dashboard shows
  only names, nicknames, aliases, PIN set, and locked status.

## Requirement: Guest changes re-draw only while unclaimed
Adding, editing, or removing a guest SHALL re-draw
automatically while no name is claimed, and SHALL be blocked
with a 409 while any name is claimed.

#### Scenario: Change blocked while claimed
- Given one guest is claimed, when the host tries to add a
  guest, then the change is refused with "reset claims first"
  and the draw is unchanged.

## Requirement: Two guests swap, one guest cannot draw
With two guests the draw SHALL swap them. With fewer than two
guests there SHALL be no valid draw, and setup SHALL refuse
or warn in plain words rather than silently self-assigning.

#### Scenario: Two-guest party
- Given guests A and B only, when the draw runs, then A buys
  for B and B buys for A.
