# Nav quick wins debug log

## 2026-11-17T05:00Z — One-shot closeout

**Necessary/useful instructions**
- `one_shot.md` Planning commit gate: plan and checklist committed (`7050fdf7`) before the first code edit. Retain.
- `AGENTS.md` UI copy ("favor removing UI text"): led to dropping the progress bar and reusing `is-active`. Retain.
- `implementation_standards.md` recovery conditions: led to testing the offline fallback with the server stopped, which caught that `/recent/` has no offline fallback at all (unchanged behaviour, not this cycle's). Retain.

**Redundant/unnecessary instructions**
- Navigation icons section: no destination added or renamed.

**Inapplicable instructions**
- Read-model schema, retraction coordination, third-party code: none touched.

**Uncertain assessments**
- Default on: chosen because the visible change is only an earlier highlight; other instances get it too. Recorded for the maintainer.
- The duplicate preload request per early-fetched tap: accepted; Railspike has no guidance on server-cost trade-offs of client speedups.
- The maintainer delegated step approval; one-shot needs none, so the delegation was not exercised in this cycle.
