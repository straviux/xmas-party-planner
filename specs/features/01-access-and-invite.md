# 01 — Access and invite (SCAFFOLD — needs full review)

Status: scaffold only. The rules below are carried over from the
current app. Before building this slice, fill in section 6
(acceptance criteria) and section 7 (edge cases) in full, using
the template.

## 1. Problem / user story

A guest with no account must reach the party with one code, find
their own name, and get into their profile — without a sign-in
form, and without being able to create a party.

## 2. Scope

- In scope: home page, invite code entry, QR entry point, guest
  entry modal, Who search entry point, navigation dock.
- Out of scope: PIN and reveal (see `03-pin-and-reveal.md`),
  host pages (see `06-host-admin.md`).

## 3. State machine / data

- Party record: `party` (name), `budget`, `date`, `invite_code`,
  `created_at`.
- No party yet is a normal empty state, never a 404, for all
  guest reads. Guest writes require a party.
- Guest identity persists in the session (guest id + PIN) across
  refresh. The recipient itself is never stored in the session.

## 4. API contract

To be completed in review. Known actions to specify here:
`invite_check`, `list` (public guest list: id, display, nickname,
aliases, claimed, needs_pin, revealed — never recipient mappings,
never PIN hashes).

## 5. UI behaviour

- Home: guest entry ("I'm a guest"), Scan QR area (design-only in
  the current app — a real camera scanner is out of scope unless
  a spec says otherwise), and a small muted "Host sign-in" link.
- No way to create a party from home. Host setup is only via a
  manually entered host URL.
- Who search: matches display name, nickname, and aliases,
  normalised (case, accents, spaces) and fuzzy (1-letter typos on
  tokens of 3+ characters). Results show aliases as "(also: …)".
- Guest dock (mobile): Home, Gifts, centre create-wishlist
  button, Wishlists, Vote. Scor sits just above the dock on
  guest home.

## 6. Acceptance criteria

[TODO — review pass: write Given/When/Then for: correct code,
wrong code, no party yet, QR link with code, search by nickname,
search by alias, fuzzy typo, no match.]

## 7. Edge cases

[TODO — review pass: code entered with spaces/case differences,
guest with the same display name, guest list empty, party deleted
mid-session.]

## 8. Open decisions

- `[DECISION]` Scan QR: keep design-only, or specify a real
  camera scanner for the rebuild?
- `[DECISION]` Keep the single-QR / single-code model, or allow
  per-party codes?
