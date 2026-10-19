# Agent instructions

## UI copy

Keep UI text minimal; avoid repeating controls or explaining internals. Reveal edited explanations through contextual help or Explain. Keep essential warnings and recovery actions visible. Track cleanup in docs/plans/ui_copy_audit_checklist.md.

## Todo

Append new entries to todo.txt.


## Railspike

Use Railspike by default when a task would benefit from comparing approaches, staged planning, or work across sessions, unless the user opts out. Read `docs/railspike/RAILSPIKE.md` and begin the appropriate planning step; wait for each required `Approved Step N` before advancing. Small, straightforward changes can proceed directly. Honor a decline for the same task without repeated offers. This guidance does not approve implementation.

Railspike is already installed as a Git subtree at `docs/railspike/`. Keep project planning artifacts in the host project's `docs/plans/`, outside the vendored tree. See `docs/reference/railspike_workflow.md` for project-specific workflow and update instructions.

## Repository guidance

Follow `CONTRIBUTING.md`, including its requirements for read-model schema changes.
