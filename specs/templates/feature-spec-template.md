# Feature spec template

Copy this file for a new feature. Do not skip a section: a spec
missing Acceptance criteria or Edge cases is a draft, not a spec.

## 1. Problem / user story

Who wants this, and what do they get out of it, in one paragraph?

## 2. Scope

- In scope:
- Out of scope (explicitly, so it does not creep back in):

## 3. State machine / data

States, transitions, and the fields that record them. If there is no
state machine, say so and explain why. Include the data shape for
every record this feature creates or changes.

## 4. API contract

Every endpoint/action: name, inputs, outputs, and every error code
it can return, with what the UI does for each error. An error the
UI has not been told how to handle will be handled badly.

## 5. UI behaviour

What the user sees and taps, step by step, including loading,
success, and failure states. Name the exact labels where they
matter (e.g. "Tear", not "Reveal").

## 6. Acceptance criteria

Testable Given / When / Then statements. Each one must be checkable
by Arc on his machine without reading the code.

- Given … When … Then …

## 7. Edge cases

The awkward cases: refresh mid-flow, double-tap, stale page, host
reset mid-flow, two devices, empty lists, one guest, two guests.

## 8. Open decisions

Anything marked `[DECISION]` that must be answered before this
spec's slice is built.
