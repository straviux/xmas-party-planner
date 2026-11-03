# OpenSpec instructions for this project

- Read `openspec/project.md` before any change. It carries the
  non-negotiable rules; a proposal that contradicts it is wrong.
- Before proposing a change: check `openspec/specs/` for the
  affected capabilities and `openspec/changes/` for active
  proposals. Pick a unique kebab-case, verb-led change-id
  (e.g. `add-tear-reveal`, `update-guest-pin`).
- A proposal MUST contain: `proposal.md` (why, what, impact),
  `tasks.md` (ordered, checkable tasks), and spec deltas for
  every affected capability under `specs/<capability>/spec.md`,
  using `## ADDED / MODIFIED / REMOVED Requirements`, with at
  least one `#### Scenario:` per requirement.
- Requirements use SHALL. Scenarios use Given / When / Then
  and must be checkable by Arc on his machine without reading
  code. Vague requirements ("guest can reveal") are not specs.
- Never write code before the proposal exists, and never let
  code drift from the specs without a spec delta. If a build
  uncovers a spec gap, stop, fill the gap in the proposal,
  then continue.
- Validate a proposal with `openspec validate <change-id>
  --strict` when the OpenSpec CLI is installed. The CLI is
  optional; the filesystem layout and the SHALL/scenario
  discipline are what matter. If the CLI is not installed,
  say so and check the layout by hand instead of claiming a
  validation ran.
- This file is OpenSpec's agent instructions. It is NOT the
  Laravel scaffold `AGENTS.md` in the project root, which
  Muse must not follow (no Boost install, no php.new
  installer) per Arc's standing instruction.
- Arc deploys and tests. Never deploy, never ask for hosting
  credentials, never use real party data for tests.
