# 03 — Guest PIN and tear reveal (DETAILED — review first)

This is the spec to review first. It is detailed on purpose: the old
app's PIN and reveal rules were discovered through bug reports, not
written down, and that is what this spec exists to prevent.

## 1. Problem / user story

A guest has no account (constitution rule 3), yet needs a way to get
back into their own profile, and a way to learn who they are buying
for exactly once, without the name leaking through a refresh, a
second scan, or a host view.

## 2. Scope

- In scope: creating/verifying a guest PIN, locking a name on
  entry, the tear reveal, returning to a profile, host Reset PIN.
- Out of scope: host login (see `06-host-admin.md`), wishlist and
  polls (they only reuse the PIN for write access, see their specs).

## 3. State machine / data

Guest record fields (names are the contract; a rebuild may rename
the storage, but not the meanings):

- `id`, `display`, `nickname`, `aliases[]`
- `pin_hash` (null until a PIN exists; never a plain-text PIN)
- `recipient_id` (set at draw time, never exposed except on reveal)
- `claimed` (bool) — the name is locked to someone.
- `claimed_at` (timestamp or null)
- `revealed` (bool) — the recipient has actually been shown.

States:

1. **Unclaimed**: `claimed=false, revealed=false`.
2. **Entered**: `claimed=true, revealed=false`. The guest has set
   or entered their PIN and locked the name, but has NOT seen the
   recipient yet.
3. **Revealed**: `claimed=true, revealed=true`. The recipient has
   been shown once. This state is terminal for reveal purposes.

Transitions:

- Unclaimed → Entered: `enter` (section 4). Verifies a PIN if one
  is set, otherwise creates one. Returns NO recipient.
- Entered → Revealed: `reveal` (the tear, section 4). Verifies the
  PIN, returns the recipient once, sets `revealed=true`.
- Unclaimed → Revealed: `reveal` called before any `enter` (e.g. a
  guest who lands directly on the reveal page). Same PIN check as
  `enter`, then claim + reveal in one step.
- Revealed → anything: no transition returns the recipient again.
  `reveal` returns `already_claimed`.
- Host Reset one / Unclaim all: `claimed=false, claimed_at=null,
  revealed=false` for the affected guest(s). PINs are NOT cleared
  by these actions (that is Reset PIN, below).
- Host Reset PIN: `pin_hash=null` only. `claimed`, `revealed`,
  recipient, wishlist, and votes are untouched. Allowed even when
  the guest is claimed — that is its main use.

Legacy rule: a record with no `revealed` field is treated as
`revealed = claimed` (old data had no split, and a claimed old
guest had already seen their recipient).

## 4. API contract

### `enter` — PIN setup + entry lock, NO reveal

- Inputs: `id`, plus `pin` (if a PIN is already set) or `new_pin`
  (if not).
- On success: sets the session guest identity, returns `{ok:true}`
  and nothing else. No recipient, no nickname of the recipient.
- Errors:
  - `already_claimed` (409): name is already locked. UI re-prompts
    with the fresh claimed status and runs the PIN check; it must
    NOT go straight to guest home (that would bypass the PIN).
  - `pin_incorrect` (403): PIN wrong. Nothing is locked, nothing
    changes. UI stays on the confirm modal.
  - `pin_required_create` (403): no PIN is set and no valid
    `new_pin` was supplied. UI asks the guest to create a PIN.

### `reveal` — the ONLY endpoint that returns a recipient

- Inputs: `id`, `pin` (and `new_pin` only on the Unclaimed →
  Revealed path, where no PIN exists yet).
- On success: sets `revealed=true`, sets the session guest
  identity, returns `{recipient, recipient_nickname, budget,
  party}`.
- Errors: same `already_claimed` / `pin_incorrect` /
  `pin_required_create` meanings as `enter`. On `already_claimed`
  the UI says the name is already locked and offers no recipient.

### `guest_verify_pin` — returning guest, profile access

- Inputs: `id`, `pin`.
- Errors: `not_claimed` (400) if the name is not locked;
  `pin_required_create` (403) if no PIN exists;
  `pin_incorrect` (403) if wrong. On success the UI goes to
  guest home IF the guest is revealed, otherwise to the reveal
  page (tear). It must never decide that from stale list data —
  it refreshes the guest list first.

### `guest_set_pin` — returning claimed guest with no PIN

- Inputs: `id`, `pin` (exactly 4 digits).
- Errors: `not_claimed` (400) if the name turns out not to be
  locked; `pin_already_set` (409) if a PIN already exists.
- On `not_claimed`, the UI falls back to the `enter` flow with
  the PIN just typed, instead of showing a dead-end error. This
  is the fix for the old "Could not set your PIN: not_claimed"
  bug, which was caused by a stale guest list.

### `host_reset_pin` — host only

- Inputs: `id`. Requires host sign-in.
- Effect: `pin_hash=null`, nothing else. Returns the refreshed
  blind guest list (with `pin_set` booleans, never PIN values).
- After a reset, a claimed guest creates a new PIN on return
  (`guest_set_pin`); an unclaimed guest creates one on first
  entry (`enter` with `new_pin`).

## 5. UI behaviour

- Who → search → select → "Is this you?" modal:
  - Unclaimed, no PIN: "Create your 4-digit PIN" + "Confirm your
    PIN" (both must be exactly 4 digits and match).
  - Unclaimed, PIN set (host-set): single "Your 4-digit PIN" field.
  - Claimed + revealed: warning that the name is already locked,
    no recipient will be shown again, wishlist/vote still available.
  - Claimed + not revealed: warning that the name is already
    locked to you, enter your PIN and you will go to your reveal.
- After `enter` succeeds: go to the reveal page. The recipient
  name is NOT shown. Only the tear strip is shown (section 5b).
- 5b. Tear strip (from Arc's screenshot): a dashed pill, a
  lime-green (#a3d65c) circle with a white right arrow, and the
  label "Tear". Tapping slides the knob across the pill, then the
  recipient name pops in. On a failed reveal the strip slides back
  and can be tried again. The strip is a button, labelled for
  accessibility as "Tear to reveal your recipient".
- After a successful tear: "You are buying for: [name]" plus
  gift budget if set, plus a warning to write it down now, plus
  "I've noted it down — show their wishlist".
- The top guest bar shows "Buying for: [name]" only after a
  successful tear, never before.

## 6. Acceptance criteria

- Given a fresh guest with no PIN, when they confirm their name
  and create PIN 1234, then they land on the reveal page with NO
  recipient visible, and the guest list shows claimed=true,
  revealed=false.
- Given that entered guest, when they refresh or reopen the app
  before tearing, then they land on the reveal page again, not
  guest home, and the name is still hidden.
- Given that entered guest, when they tear with the wrong PIN,
  then no recipient is shown, the strip slides back, and the
  guest is still entered-but-not-revealed.
- Given that entered guest, when they tear with the correct PIN,
  then the recipient is shown once, and a second tear (or a fresh
  entry) returns `already_claimed` with no recipient.
- Given a revealed guest, when they return and enter their PIN,
  then they go to guest home, never back to the recipient.
- Given a claimed guest who forgot their PIN, when the host uses
  Reset PIN and the guest returns, then the guest creates a new
  PIN and reaches their profile, with their recipient, wishlist,
  and votes unchanged (revealed guests stay revealed).
- Given a host Reset one / Unclaim all, when the guest re-enters,
  then they can tear and reveal again (claimed and revealed both
  reset, PIN unchanged).

## 7. Edge cases

- Double-tap on Tear: the second tap is ignored while a reveal
  is in flight.
- Stale Who list (host reset the name after the page loaded):
  the PIN flow refreshes the list and branches on the fresh
  status; a `not_claimed` from `guest_set_pin` falls back to
  `enter`, it is never a dead-end error.
- Two devices, same name: the first `enter` locks it; the second
  device must pass the PIN check and then goes to reveal (if not
  yet revealed) or guest home (if revealed) — it never gets a
  second recipient.
- One guest in the party: the draw cannot derange; setup must
  refuse or warn (see `02-guests-and-draw.md`), it must not
  silently assign the guest to themselves.
- `prefers-reduced-motion`: the knob slide and the name pop-in
  are disabled.

## 8. Open decisions

- None for this spec. It is ready for review as written.
