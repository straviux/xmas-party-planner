# Changes (OpenSpec proposals)

Active rebuild work goes here, one folder per change, with a
unique kebab-case, verb-led change-id:

```
openspec/changes/<change-id>/
  proposal.md   # why, what, impact
  tasks.md      # ordered, checkable tasks
  design.md     # only if the change needs architecture notes
  specs/
    <capability>/spec.md   # ADDED / MODIFIED / REMOVED
```

No change folder exists yet. The first rebuild change should
be the foundation slice (project skeleton, MariaDB setup,
host gate, party setup — see the old `specs/tasks.md` Phase B
in git history, now superseded by this directory).

After a change is implemented and accepted by Arc, archive it
under `openspec/changes/archive/<date>-<change-id>/` and merge
its spec deltas into `openspec/specs/`.
