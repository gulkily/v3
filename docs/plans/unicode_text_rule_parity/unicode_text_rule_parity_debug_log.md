# Unicode text rule parity — Railspike debug log

## 2026-11-17T06:30Z — one-shot closeout

**Necessary/useful instructions**

- `one_shot.md`, "Planning commit gate": committing the plan before code meant the parity test could be written against the base code and shown to fail there. Retain.
- `effort-estimation.md`, "One-shot checkpoints", intake row ("before detailed implementation inspection"): writing 0.25 before opening either file kept the intake honest; the scan later changed the picture (one small fix, one decision). Retain.
- `one_shot.md`, "Execution and evidence", stop when work exceeds the boundary: the Unicode drift fix would have changed which characters the server accepts, which the authorization excluded. The rule made recording it as a decision the obvious path. Retain.

**Redundant/unnecessary instructions**

- `one_shot.md` record template, "Concepts touched": for a review cycle that changes one helper, the field only ever says "None". Suggest: allow omitting it when no inventory exists for the area. Clarify.

**Inapplicable instructions**

- Extended estimates: no real alternatives or work units, so no unit estimates, as the guide allows.
- Documentation-only rules: this cycle changed code.

**Uncertain assessments**

- `RAILSPIKE.md`, "Writing style", 25-word limit: the findings section has a few long sentences that carry code names; splitting them further may hurt more than help. No change suggested yet.
