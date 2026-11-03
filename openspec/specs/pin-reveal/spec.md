# pin-reveal

## Requirement: Entry locks the name and sets the PIN, but reveals nothing
`enter` SHALL verify a PIN if one is set, otherwise create one
from `new_pin` (exactly 4 digits, entered twice and matching
in the UI), set claimed=true and revealed=false, set the
session guest identity, and return `{ok:true}` with NO
recipient.

#### Scenario: First entry creates a PIN and hides the name
- Given a fresh guest with no PIN, when they confirm their
  name and create PIN 1234, then they land on the reveal page
  with no recipient visible, and the guest list shows
  claimed=true, revealed=false.
- Given the same guest, when they enter with a wrong PIN,
  then nothing is locked and nothing changes.

## Requirement: Tear reveal is the only way a recipient is shown
`reveal` SHALL be the only endpoint that returns a recipient.
It SHALL verify the PIN, set revealed=true, and return
`{recipient, recipient_nickname, budget, party}`. A second
`reveal` SHALL return `already_claimed` with no recipient.

#### Scenario: Tear once, then never again
- Given an entered guest, when they tear with the correct
  PIN, then the recipient is shown once.
- Given the same guest after tearing, when they tear again
  or re-enter, then they get `already_claimed` and no
  recipient.

## Requirement: Reveal before entry claims and reveals in one step
When `reveal` is called for an unclaimed guest, it SHALL run
the same PIN check as `enter`, then set claimed=true and
revealed=true and return the recipient.

#### Scenario: Direct reveal
- Given an unclaimed guest with a host-set PIN, when they
  reveal with the correct PIN, then the recipient is shown
  and the guest becomes claimed and revealed.

## Requirement: Returning guests go to guest home only if revealed
After `guest_verify_pin` (or `guest_set_pin` for a claimed
guest with no PIN), the UI SHALL go to guest home if the guest
is revealed, otherwise to the reveal page. The UI SHALL refresh
the guest list first and branch on the fresh status, never on
stale list data.

#### Scenario: Return before tearing
- Given a guest who entered but has not torn, when they
  return and enter their PIN, then they land on the reveal
  page, not guest home, with the tear strip shown.
- Given a revealed guest, when they return and enter their
  PIN, then they land on guest home, never back to the
  recipient.

## Requirement: Stale-list fallback, never a dead-end error
When `guest_set_pin` returns `not_claimed` because the name
turned out not to be locked, the UI SHALL fall back to the
`enter` flow with the PIN just typed, instead of showing a
dead-end error.

#### Scenario: Host unclaimed the name after the page loaded
- Given a guest list loaded before a host unclaim, when the
  guest tries to set a PIN and the server says `not_claimed`,
  then the guest proceeds as a first entry with that PIN,
  not an error screen.

## Requirement: Already-claimed entry must not bypass the PIN
When `enter` returns `already_claimed`, the UI SHALL re-prompt
with the fresh claimed status and run the PIN check. It SHALL
NOT go straight to guest home.

#### Scenario: Stale unclaimed list
- Given a guest list that said unclaimed, when entry fails
  with `already_claimed`, then the guest is asked for their
  PIN and routed by their fresh revealed status.

## Requirement: Tear strip UI
The reveal page SHALL show, before tearing, a dashed pill with
a lime-green (#a3d65c) circle containing a white right arrow
and the label "Tear". Tapping SHALL slide the knob across the
pill, then the recipient name SHALL pop in. On a failed reveal
the strip SHALL slide back and be retryable. Double-taps while
a reveal is in flight SHALL be ignored. With
`prefers-reduced-motion`, the slide and pop-in SHALL be
disabled.

#### Scenario: Tear interaction
- Given an entered guest on the reveal page, when they tap
  Tear with the correct PIN, then the knob slides across and
  the recipient name pops in.
- Given the same guest, when they tap Tear with a wrong PIN,
  then no name is shown and the strip slides back.

## Requirement: Host Reset PIN clears only the PIN
`host_reset_pin` (host sign-in required) SHALL set
pin_hash=null and NOTHING else: claimed, revealed, recipient,
wishlist, and votes are untouched. It SHALL be allowed even
when the guest is claimed. After a reset, a claimed guest
SHALL create a new PIN on return, and an unclaimed guest
SHALL create one on first entry.

#### Scenario: Forgotten PIN on a locked guest
- Given a claimed guest who forgot their PIN, when the host
  resets their PIN and the guest returns, then the guest
  creates a new PIN and reaches their profile, with their
  recipient, wishlist, and votes unchanged.

## Requirement: Host reset clears revealed too
Host Reset one and Unclaim all SHALL set claimed=false,
claimed_at=null, AND revealed=false, so a reset guest can
reveal again. They SHALL NOT clear PINs (that is Reset PIN)
and Unclaim all SHALL NOT redraw.

#### Scenario: Unclaim then re-reveal
- Given a revealed guest, when the host unclaims all and the
  guest re-enters, then they can tear and reveal again with
  their existing PIN.
