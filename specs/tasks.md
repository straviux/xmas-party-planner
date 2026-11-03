# Tasks — ordered implementation slices (DRAFT)

Order is dependency order, not feature-number order. Do not
start a slice until its spec's acceptance criteria are filled
in and reviewed. Each slice ends with Arc's acceptance check
on his machine, against that spec — not against the code.

## Phase A — Spec review (no code)

- [ ] A1. Review `constitution.md` — confirm every rule, or
  change it deliberately.
- [ ] A2. Review `03-pin-and-reveal.md` in full — it is the
  detailed spec and the template for the others.
- [ ] A3. Fill in acceptance criteria + edge cases for
  `01`, `02`, `04`, `05`, `06`, `07` (currently scaffolds).
- [ ] A4. Answer every `[DECISION]` in `plan.md`. Storage
  (JSON vs database) first — it blocks everything after it.

## Phase B — Foundation

- [ ] B1. Project skeleton per `plan.md` (stack, routes,
  sessions, build, local run). Acceptance: local run opens
  guest home with "no party yet" empty state, no errors.
- [ ] B2. Host sign-in/out + host gate (server + client).
  Acceptance: signed-out host views show no party data at all.
- [ ] B3. Party setup (details + guest list, draw).
  Acceptance: setup creates a party, draw is a derangement,
  nobody gets themselves, host sees only blind guest data.

## Phase C — Guest core (the heart of the app)

- [ ] C1. Invite code + Who search (name/nickname/alias,
  normalised + fuzzy). Acceptance: spec `01` criteria.
- [ ] C2. PIN setup + entry lock (`enter`, no recipient).
  Acceptance: spec `03` criteria for Unclaimed → Entered.
- [ ] C3. Tear reveal (`reveal`, once). Acceptance: spec `03`
  criteria for Entered → Revealed, wrong PIN, second tear,
  refresh-before-tear, return-to-guest-home.
- [ ] C4. Returning guest (verify PIN / create PIN if legacy).
  Acceptance: spec `03` criteria for revealed vs not-revealed
  return, stale-list fallback, host Reset PIN.

## Phase D — Party features

- [ ] D1. Wishlist: my list, board, anonymous reserve.
  Acceptance: spec `04` criteria.
- [ ] D2. Polls: host create/manage, guest vote/change vote.
  Acceptance: spec `05` criteria.
- [ ] D3. Guest home + dock + Scor placement.
  Acceptance: dock navigation, Scor clearance above the dock,
  guest bar shows recipient only after a tear.

## Phase E — Host admin

- [ ] E1. Dashboard + Guests table + search + Reset PIN.
  Acceptance: spec `06` criteria for those views.
- [ ] E2. Guest management rules (blocked while claimed,
  auto-redraw while unclaimed). Acceptance: spec `02` + `06`.
- [ ] E3. Redraw, Reset one, Unclaim all, Clear wishlists,
  Change password. Acceptance: spec `06` criteria, including
  Unclaim all as the Redraw prerequisite.
- [ ] E4. Danger zone: Reset everything (with confirm).
  Acceptance: party deleted, fresh setup possible, no leftover
  guest sessions showing old data.

## Phase F — Deployment

- [ ] F1. Deployment spec check on Arc's host (see `07`).
  Acceptance: fresh checkout → build → sign-in → setup →
  guest entry on a phone, from the spec steps alone.
- [ ] F2. Old party data: explicitly NOT migrated, unless a
  separate migration spec is written and agreed.

## Rules for every slice

- Update the spec before changing behaviour. No silent drift.
- No slice is "done" on a build pass alone. It is done when
  Arc's acceptance check for that spec passes.
- If a slice uncovers a spec gap, stop, fill the gap in the
  spec, then continue. Do not paper over it in code.
