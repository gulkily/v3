# Agent instructions

## UI copy

Favor removing UI text over rewriting or relocating it. Rely on an intuitive, explorable interface; avoid repeating controls or explaining internals. Keep contextual help or Explain only where users need information the interface cannot communicate. Keep essential warnings and recovery actions visible. Follow the UI verification guidance in `CONTRIBUTING.md`. Track cleanup and verification in `docs/plans/ui_copy_audit_checklist.md`.

## Todo

Do not modify `todo.txt` unless the user explicitly requests an edit to that file. Record task progress and follow-up work in the relevant planning or implementation documents instead.

## Navigation icons

When adding or renaming a navigation destination, update its icon mapping in `public/assets/theme-nav-icons.css` for icon themes (currently Word97, Chicago, and Midnight). Cover both Tools launcher buttons and Tools subnavigation where applicable. Verify the icon in each affected theme before calling the UI complete; text-only theme checks do not cover this.

## Railspike

Use Railspike by default when a task would benefit from comparing approaches, staged planning, or work across sessions, unless the user opts out. Read `docs/railspike/RAILSPIKE.md` and begin the appropriate planning step; wait for each required `Approved Step N` before advancing. Small, straightforward changes can proceed directly. Honor a decline for the same task without repeated offers. This guidance does not approve implementation.

Railspike is already installed as a Git subtree at `docs/railspike/`. Keep project planning artifacts in the host project's `docs/plans/`, outside the vendored tree. See `docs/reference/railspike_workflow.md` for project-specific workflow and update instructions.

## Repository guidance

Follow `CONTRIBUTING.md`, including its requirements for read-model schema changes.

## Continuing Railspike effort study

Every future Railspike cycle participates in the same `railspike-effort` study under protocol P2. Follow [standing enrollment and checkpoint collection](docs/plans/effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2); do not ask to select each cycle again. Keep snapshots in that cycle's artifacts and index them in the host ledger. Preserve R1, stable IDs, score revisions and incomplete outcomes; deduplicate worktree copies. Existing cycles join at their next checkpoint without invented earlier scores. Collection does not approve implementation, bypass normal approvals, require debug mode or authorize uploads.
