# host-admin

## Requirement: Host sign-in is username and password only
Host sign-in SHALL use username + password. Not a PIN, not
email. The initial host account SHALL be seeded, and its
password SHALL be changeable from the dashboard and changed
immediately after first sign-in. [DECISION] Seeding method
(env-set vs seeder default) to be written here before the
host slice is built.

#### Scenario: First sign-in
- Given a fresh install, when the host signs in with the
  seeded account and changes the password, then the old
  password stops working.

## Requirement: Signed-out host views show no data
Host views SHALL compute sign-in from the session
independently of party data. When signed out, a host view
SHALL return empty host props only — no guests, wishlists,
polls, party name, budget, or date — and SHALL render only a
sign-in screen, with no sidebar and no host nav. Every host
write SHALL require host sign-in server-side.

#### Scenario: Signed-out host page
- Given a signed-out visitor, when they open a host URL,
  then they see only the sign-in screen and no party data.

## Requirement: Admin layout is desktop-first
Host pages SHALL use a fixed 260px left sidebar (Host +
party name, Dashboard / Guests / Polls / Party settings,
"Signed in as [username]", Sign out, "Back to guest home"),
main content min-width 960px, admin wrapper min-width
1220px, horizontal scroll on narrow windows, and no mobile
reflow. Each page SHALL have a header with title, subtitle,
and its primary action on the right.

#### Scenario: Narrow host window
- Given a host page on a narrow window, when it renders,
  then it scrolls sideways instead of reflowing to mobile.

## Requirement: Guest management is blocked while claimed
Guest add, edit, and remove SHALL be blocked with a 409
while any name is claimed, and SHALL re-draw automatically
on success while unclaimed. Party settings edits SHALL touch
details only and SHALL never clear the guest list.

#### Scenario: Edit blocked while claimed
- Given a claimed guest, when the host edits another guest,
  then the edit is refused until claims are reset.

## Requirement: Dashboard, guests, and polls are real tables
The dashboard SHALL show stat cards (Guests, Locked,
Awaiting, Polls), a locked/awaiting progress bar, a Party
panel (name/date/budget, invite Copy/Regen, QR Print), and a
Claimed table with Reset per locked name. The Guests table
SHALL show Name, Nickname, Aliases, PIN set, Status, and
Actions, with a search box matching name/nickname/alias.
The Polls table SHALL show Question, Status, Votes, Winner,
and Actions.

#### Scenario: Guests table search
- Given guests with nicknames and aliases, when the host
  searches the Guests table by alias, then the matching guest
  is found.

## Requirement: Reset actions have distinct, non-overlapping effects
- Reset one SHALL clear claimed + revealed for one guest.
- Unclaim all SHALL clear claimed + revealed for all guests,
  SHALL NOT redraw, and SHALL NOT clear PINs. It is the
  prerequisite for Redraw, which is blocked while any name
  is claimed.
- Reset PIN SHALL clear only the PIN (see pin-reveal).
- Clear all wishlists SHALL clear wishlists only.
- Reset everything SHALL delete the whole party, behind a
  confirm dialog in the Danger zone.

#### Scenario: Unclaim then redraw
- Given claimed guests, when the host redraws, then it is
  blocked; when the host unclaims all first, then redraw
  succeeds.
