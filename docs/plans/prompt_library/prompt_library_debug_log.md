# prompt_library debug log

## 2026-11-14T22:45Z — one-shot closeout

**Necessary/useful instructions**
- `AGENTS.md` "Navigation icons": forced the icon work in all three sets; the visual check it asks for caught the glyph leaking onto the page's own `/agent/` links. Retain.
- `implementation_standards.md` "inspect existing implementations": led to reusing the copy partial, board toolbar and listed-roots SQL instead of new components. Retain.

**Redundant/unnecessary instructions**
- `one_shot.md` record template: the study fields duplicate the ledger row nearly verbatim for a single-checkpoint cycle. Suggest letting the ledger link to the record without restating scores. Consolidate.

**Inapplicable instructions**
- Staged step approvals, migration/deployment checks (no schema or deploy change).

**Uncertain assessments**
- Whether a Tools launcher that duplicates a new nav item counts as in-scope cleanup under "favor removing UI text"; recorded as a maintainer question rather than acted on.
