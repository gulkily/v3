> **Feature plan:** [Step 1](./project_debug_default_step1_solution_assessment.md) · [Step 2](./project_debug_default_step2_feature_description.md) · [Step 3](./project_debug_default_step3_development_plan.md) · [Step 4](./project_debug_default_step4_implementation_summary.md)

Debug mode: enabled

## Stage 1 - Project debug marker
- Changes: Extended the canonical debug contract in `RAILSPIKE.md` with consuming-root discovery, presence-only `.railspike_debug_on` semantics, precedence, snapshots, and clarification on uncertain lookup. Added README creation/removal/override examples and aligned `docs/failure-modes.md` recovery. Workflow phase files already delegate to the canonical contract and needed no edits.
- Verification: `bash scripts/check-railspike-git-workflow.sh` passed; `git diff --check` passed. `python scripts/check-markdown-links.py` reported only six pre-existing future-step links in unrelated `first_release` and `railspike_paper` Step 1 plans (Steps 2–4 for each); all affected local links and feature navigation resolve. Reviewed active debug/default references using `rg`, the implementation diff, and the scenario matrix below; no remaining contract contradictions found. Scoped path/link checks also verify allowed files and preservation of the approved plans/log history.
- Notes: Planning commit `96e6236` precedes this stage and summary. Plans and the existing log were moved byte-for-byte into this folder; the plan index was updated. No actual marker was created. Runtime, UI, database/migration, deployment, and external release checks are not applicable because this change defines an instruction contract without executable behavior. Application, tests, scripts, CI, and generated artifacts were not changed. Evidence verifies documentation consistency, not guaranteed agent adherence.

### Scenario review

Manual review against `RAILSPIKE.md` → **Project default**, **Durable state and recovery**, and **Completed-step report**, cross-checked with README examples and the failure guide:

| Scenario | Documented outcome | Result |
| --- | --- | --- |
| New workflow, marker absent, no request | Disabled snapshot | Pass |
| Marker present with empty, arbitrary, or “false” contents | Enabled snapshot in every case; no content read | Pass |
| Marker present, contents unreadable | Enabled when file presence is established | Pass |
| Consuming root or file presence uncertain; marker path is a directory | Clarify when lookup is needed; never assume absence | Pass |
| Explicit disable with marker; explicit enable without marker | Explicit request wins; marker unchanged | Pass |
| Explicit request with uncertain marker lookup | Request resolves mode without consulting marker | Pass |
| Existing enabled workflow after marker removal | Remains enabled | Pass |
| Existing disabled workflow after marker addition | Remains disabled | Pass |
| Marker contents edited | No mode/default change | Pass |
| Resumed state missing/conflicting and evidence insufficient | Clarify; marker does not repair state | Pass |
| Standalone work on Railspike | Marker belongs at this repository root | Pass |
| Submodule or vendored Railspike, including shell inside install | Marker belongs at host root; installed/ancestor defaults ignored | Pass |
| Enabled completed-step handoff | Full saved report; one conversational debug link | Pass |
| Disabled completed-step handoff | No diagnostics/link; existing log preserved | Pass |

### Completion

The approved documentation outcome is complete. No unresolved feature-specific verification failures remain; unrelated future-step link failures above are outside this feature's scope.
