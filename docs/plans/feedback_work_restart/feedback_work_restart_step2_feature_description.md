> **Feature plan:** [Step 1](./feedback_work_restart_step1_solution_assessment.md) · [Step 2](./feedback_work_restart_step2_feature_description.md) · [Step 3](./feedback_work_restart_step3_development_plan.md) · [Step 4](./feedback_work_restart_step4_implementation_summary.md)

Effort study: `railspike-effort` / `v3/feedback_work_restart`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Approved Step 1: Option B, item register in the repository (2026-11-04).

**Cycle estimate: 0.60**

## Problem

Feedback triaged after each import lands in a new dated table, so items have no stable identity, status changes have nowhere to go, and the [production feedback register](../production_feedback_register.md) duplicates part of it by hand.

## Concepts touched

- **Feedback item** (proposed): one file per requested outcome, with a permanent ID, kind, status, sources and estimate. Replaces the register's per-request sections and the dated summary tables as the current authority.
- **Feedback triage** (changed): the [summary checklist](../zenmemes_feedback_automation_checklist.md) produces create, attach and skip proposals against existing items instead of a new table.
- **Production feedback register** (retired as authority): its entries become items; the document and the dated summaries remain history.
- **Instance import** (used, unchanged).

## User stories

- As the maintainer, I want new zenmemes.com feedback turned into proposed item changes after an import so that I review one diff instead of a new document.
- As the maintainer, I want a repeat request attached to the item it repeats so that open work isn't split across duplicates.
- As the maintainer or an agent, I want to change an item's status by editing its file so that commits and plans can reference it by ID.
- As anyone reading the repository, I want one generated index of open items by kind so that I can see what is open at a glance.

## Core requirements

- Each item is a `Key: value` file with a permanent `PF-` ID that is never renumbered or reused; existing `PF-20261021-*` IDs are kept.
- Triage proposes before it writes: every in-window post ends up in exactly one create, attach or skip, with a reason for each skip.
- A merge keeps every source link; a mistaken attach can be undone by restoring a separate item.
- A check command reports malformed files, duplicate IDs and any source post claimed by two items; an index command regenerates the open-items list.
- Seeding from the October 30 summary and the register loses no source link and no recorded delivery evidence.

## Delivery scope

- Work type: application change (format spec in `docs/specs/`, item folder, two `./v3` commands, updated triage and management references, seed migration).
- Not in scope: a site page, forum posting, status from site members, the parked work page, editing existing `#work` threads.

## Completion boundary

- **Entry:** after `./v3 import-instance zenmemes.com`, ask an agent to triage feedback since a date.
- **Outcome:** the agent shows proposals, then writes item changes; `./v3 feedback check` passes; the index lists the new and updated items; you commit.
- **Recovery:** a rejected or wrong proposal is reverted with Git before commit; an interrupted run leaves no half-written file; rerunning on the same window proposes nothing already applied.
- **Release:** seeded items, spec, commands and references merged to main.

## Known user or environment

- The maintainer's WSL checkout on the Lenovo machine, where imports and agents run. A failed check names the file and line; the user fixes or reverts it and reruns the check; a clean check plus a regenerated index on that checkout is the evidence.

## Risks

- **Duplicate matching.** Wrong attaches hide distinct requests; missed ones create duplicates. Validate early on the October 30 window, which already has merged repeats. Mitigation: uncertain matches become separate items with a review note.
- **Seed nuance loss.** Register entries carry investigation history and delivery evidence. Validate by migrating two register entries first. Mitigation: items keep current fields and link to the existing history.
- **Older summaries.** The 10-18 and 10-21 inventories are partly covered by 10-30. Mitigation: seed from 10-30 and the register only; list the older files as unreconciled sources.
- **Format drift.** Hand edits may break files. Mitigation: the check command, run before commit.

## Shared component inventory

- `Key: value` record format and the parsers in `src/ForumRewrite/Canonical/` (`GenericTextRecordParser` and the per-record parsers): reuse the format and parsing approach; item files get their own spec rather than a new syntax.
- [Production feedback management](../../reference/production_feedback_management.md) and [minimal triage](../../reference/minimal_feedback_triage.md): update in place to point at items; keep ID rules and workflow states.
- `./v3` dispatcher: extend with a `feedback` command.
- No existing page renders feedback; no new UI.

## User flow

1. Run `./v3 import-instance zenmemes.com`.
2. Ask the agent to triage feedback since the last run.
3. Review the proposed creates, attaches and skips.
4. The agent writes the accepted changes and regenerates the index.
5. Run `./v3 feedback check`, review the diff, commit.

## Success criteria

- The October 30 window, re-triaged, produces items covering all 97 catalogued requests and bugs with no lost source link.
- All 17 register entries exist as items with their IDs, states and evidence links.
- A second run on the same window proposes no new items.
- The two "summary field" request threads from 2026-11-03 become one item with two sources.

## Study Snapshots

2026-11-04; scope v1; starting revision `81837cf`; reference R1 not re-verified in this session. Claude, single exposed rater; Step 1 snapshot (0.55) preserved there.

- Step 2 handoff: **0.60** projected total. Up from 0.55: seed migration now covers delivery evidence from the register, and the reference docs are updated in place. Not hours; no implementation or approval implied.

Status: Approved Step 2 (2026-11-04).

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
