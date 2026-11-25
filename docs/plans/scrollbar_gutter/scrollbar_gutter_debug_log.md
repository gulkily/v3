# scrollbar_gutter debug log

## 2026-11-25T10:40Z — one-shot closeout

**Necessary/useful instructions**
- `docs/workflow/one_shot.md`, "Planning commit gate": committing the record before the CSS edit kept the diagnosis separate from the change. Retain.
- `docs/workflow/implementation_standards.md`, "replay each reported failure": pushed past a metric that showed nothing (`layout-shift` was empty) to a per-frame replay that reproduced the shift. Retain.
- `AGENTS.md`, "search `docs/feedback/items/`": found PF-20261030-B02, which looks related but is a vertical jump; avoided a wrong trailer. Retain.

**Redundant/unnecessary instructions**
- `docs/workflow/one_shot.md`, record template: about 20 fields for a three-line CSS change. Most were answerable in one line, but the record is still longer than the diff. Suggest a short form for single-file fixes with one obvious approach. Clarify.

**Inapplicable instructions**
- Extended estimates: no alternatives or work units, as the one-shot guide anticipates.
- Concept inventory: no entry covers page-level layout.

**Uncertain assessments**
- Effort intake was recorded after the diagnosis. The diagnosis happened before the maintainer asked for a PR, so a prospective intake was not possible; disclosed in the record rather than counted as missing.
