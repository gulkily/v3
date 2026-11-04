> **Feature plan:** [Step 1](./feedback_work_restart_step1_solution_assessment.md) · [Step 2](./feedback_work_restart_step2_feature_description.md) · [Step 3](./feedback_work_restart_step3_development_plan.md) · [Step 4](./feedback_work_restart_step4_implementation_summary.md)

Effort study: `railspike-effort` / `v3/feedback_work_restart`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Approved Step 2: 2026-11-04, no scope added since.

**Cycle estimate: 0.60**

## Concepts touched

- Feedback item (new): add to the concept inventory in Stage 1.
- Feedback triage (changed), production feedback register (retired as authority): update their references in Stages 4 and 6.
- Agent instructions (new home): `docs/agents/`, Stage 6.
- Instance import: used, unchanged.

## Completion Contract

- **Entry:** `./v3 import-instance zenmemes.com`, then ask an agent to triage feedback since a date.
- **Outcome:** proposals shown; accepted changes written as item files under `docs/feedback/items/`; `./v3 feedback check` passes; `./v3 feedback index` regenerates `docs/feedback/README.md`; the maintainer commits.
- **Recovery:** proposals are written only after review; Git reverts a bad apply; files are written atomically; rerunning a window proposes nothing already applied.
- **External verification:** replay on the maintainer's WSL checkout (Stage 7).
- **Release:** seeded items, spec, commands and references merged to main.

## Key Risks

- **High risk:** duplicate matching. Wrong attaches hide requests. Validate in Stage 5 on the October 30 window. Mitigation: uncertain matches stay separate with a review note.
- **High risk:** seed loses register evidence. Validate with a two-entry pilot (Stage 3) before the rest. Mitigation: items keep current fields and link to existing history.
- Repeated headers. The `Key: value` parser rejects duplicate headers, so several sources can't each be a header. Resolve in Stage 1 by putting sources in the body under a fixed section.

## Stage 1

**Estimate: 0.12**

- Goal: item format and a check command.
- Dependencies: none.
- Expected changes: spec `docs/specs/feedback_item_record_v1.md` (ID, Title, Kind, Workflow, Evidence, Estimate, Created headers; body with request text, Sources list, Done-when, Notes); `FeedbackItemRepository::load(dir): list`; `FeedbackItemCheck::run(dir): list of problems`; `scripts/feedback.php check`; `./v3 feedback` dispatch; concept inventory entry.
- Verification approach: new `FeedbackItemCheckTest` (valid file, malformed header, duplicate ID, source claimed twice, unknown kind or state) plus smoke set.
- Estimate details: driver: format decisions; uncertainty: low.
- Risks or open questions: the duplicate-header limit, resolved by the body Sources list.
- Canonical components/API contracts touched: `GenericTextRecordParser` (reused); `./v3` dispatcher (extended).

## Stage 2

**Estimate: 0.06**

- Goal: generated index of open items.
- Dependencies: Stage 1.
- Expected changes: `scripts/feedback.php index` writes `docs/feedback/README.md` grouped by kind and workflow state, with a "generated; do not edit" line; atomic write.
- Verification approach: index test (grouping, stable order, unchanged output on rerun).
- Estimate details: driver: layout; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: Stage 1 repository.

## Stage 3

**Estimate: 0.06**

- Goal: pilot migration of two register entries (F01 with investigation history, one bug).
- Dependencies: Stage 1.
- Expected changes: two item files; register sections link to them.
- Verification approach: check passes; side-by-side review that every source, state and evidence link survives.
- Estimate details: driver: deciding what stays in the item vs linked history; uncertainty: medium.
- Risks or open questions:
  - Impact: lost delivery evidence.
  - Early warning / validation: the pilot review.
  - Mitigation: adjust the spec before Stage 4.
- Canonical components/API contracts touched: item spec.

## Stage 4

**Estimate: 0.08**

- Goal: migrate the remaining 15 register entries; retire the register as authority.
- Dependencies: Stage 3.
- Expected changes: 15 item files; register gets a superseded note pointing at the index; `production_feedback_management.md` points at items and keeps ID and state rules.
- Verification approach: check passes; script comparison of register IDs and source links against item files.
- Estimate details: driver: volume; uncertainty: low.
- Risks or open questions: none beyond Stage 3.
- Canonical components/API contracts touched: production feedback management reference.

## Stage 5

**Estimate: 0.15**

- Goal: seed from the October 30 summary.
- Dependencies: Stage 4.
- Expected changes: proposals for all 97 catalogued rows (create or attach to an existing item); item files written after maintainer review; 10-18 and 10-21 summaries listed as unreconciled in the index.
- Verification approach: script check that every source link in the summary appears in exactly one item; check passes; maintainer reviews the attach list.
- Estimate details: driver: matching judgment across 97 rows; uncertainty: medium.
- Risks or open questions:
  - Impact: wrong merges.
  - Early warning / validation: the attach list shown before writing.
  - Mitigation: separate items with a review note when unsure.
- Canonical components/API contracts touched: item spec; check command.

## Stage 6

**Estimate: 0.08**

- Goal: triage procedure for new imports, in a home for agent instructions.
- Dependencies: Stage 5.
- Expected changes: new `docs/agents/` folder for instructions agents follow when working on the project (separate from `prompts/`, which the application loads at runtime); `docs/agents/README.md` index; `docs/agents/feedback_triage.md`, the procedure (read imported posts in a window, match against items, present create/attach/skip proposals with skip reasons, then write and index); `zenmemes_feedback_automation_checklist.md` marked superseded; `minimal_feedback_triage.md` and `production_feedback_management.md` link to it; one `AGENTS.md` line pointing to the `docs/agents/` index.
- Verification approach: dry run on a short window already covered by Stage 5 proposes no new items; links resolve.
- Estimate details: driver: wording the matching rules; uncertainty: low.
- Risks or open questions: agents may still treat runtime `prompts/` as the place for instructions; the README states the difference in one line.
- Canonical components/API contracts touched: triage and management references; `AGENTS.md`.

## Stage 7

**Estimate: 0.05**

- Goal: acceptance on the maintainer's WSL checkout.
- Dependencies: Stage 6.
- Expected changes: none planned; fixes only if the replay fails.
- Verification approach: maintainer runs import, triage since 2026-11-01, check and index; the two 2026-11-03 "summary field" threads become one item with two sources; a rerun proposes nothing. Evidence recorded in Step 4.
- Estimate details: driver: real data on the real machine; uncertainty: medium (site reachability, as seen in the 2026-11-04 sweep).
- Risks or open questions: if the import fails, record it unverified rather than substituting another environment.
- Canonical components/API contracts touched: none new.

## Enhancement Requests

- A place for prompts and instructions (raised 2026-11-04). Disposition: `docs/agents/` is created in Stage 6 with the triage procedure. Moving existing development prompts (`docs/reference/ui_copy_cleanup_prompt.md`, `docs/plans/ui_redesign/plan_prompt.md`) there is a separate small item; runtime `prompts/` stays where the application expects it.

## Study Snapshots

2026-11-04; scope v1; starting revision `81837cf`; reference R1 not re-verified in this session. Claude, single exposed rater. Earlier snapshots: Step 1 0.55, Step 2 0.60 (preserved there).

- Step 3 handoff: **0.60** projected total. Revised 2026-11-04 for the `docs/agents/` location (Stage 6 0.07 → 0.08); cycle projection unchanged. Stage values are annotations, not summed. Not hours; no implementation or approval implied.

Status: Approved Step 3 (2026-11-04).

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
