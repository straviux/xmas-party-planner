# 02 — Guests and draw (SCAFFOLD — needs full review)

Status: scaffold only. Rules carried over from the current app.
Fill in acceptance criteria and edge cases before building.

## 1. Problem / user story

The host enters the guest list, and the app fairly assigns every
guest a recipient nobody can see in advance — not even the host.

## 2. Scope

- In scope: host guest entry (display, nickname, aliases,
  optional PIN), the derangement draw, redraw rules.
- Out of scope: guest entry flow (see `01` and `03`), guest
  management UI after setup (see `06-host-admin.md`).

## 3. State machine / data

- Guest fields: see `03-pin-and-reveal.md` section 3.
- Draw: a derangement of the guest ids. With two guests, they
  swap. With fewer than two, there is no valid draw.
- Recipient mappings are never exposed to anyone, not even the
  host, in any API response or admin view. Host views show only
  blind data: name, nickname, aliases, PIN set, locked/not locked.
- Adding, editing, or removing guests re-draws automatically —
  but only while no name is claimed. Once any name is claimed,
  guest list changes are blocked until claims are reset.

## 4. API contract

To be completed in review. Known actions: `setup` (party +
guests together), `host_redraw`, `host_guest_add/update/remove`
(see `06` for the management rules).

## 5. UI behaviour

- Setup is one flow: party details + guest list together.
  Guest add/edit is a modal (name required, nickname/aliases
  optional, PIN optional — blank means the guest creates their
  own on first entry).
- Redraw is blocked while any name is claimed, and says so in
  plain words. Unclaim all is the prerequisite step.

## 6. Acceptance criteria

[TODO — review pass: draw is a derangement, nobody gets
themselves, redraw changes mappings, redraw blocked while
claimed, guest add re-draws while unclaimed.]

## 7. Edge cases

[TODO — review pass: 1 guest, 2 guests, duplicate names after
normalisation, guest removed after draw, aliases matching another
guest's name.]

## 8. Open decisions

- `[DECISION]` Keep automatic re-draw on every guest change, or
  make redraw an explicit host action only?
