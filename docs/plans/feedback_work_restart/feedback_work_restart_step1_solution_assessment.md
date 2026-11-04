> **Feature plan:** [Step 1](./feedback_work_restart_step1_solution_assessment.md) · [Step 2](./feedback_work_restart_step2_feature_description.md) · [Step 3](./feedback_work_restart_step3_development_plan.md) · [Step 4](./feedback_work_restart_step4_implementation_summary.md)

Effort study: `railspike-effort` / `v3/feedback_work_restart`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.55

## Original Query

So here is what I'm missing right now. I added a bunch of requests on zenmemes.com. After this, I would normally do import-instance and then ask an agent to generate something similar to zenmemes_meta_feedback_2026-10-30_summary.md. But now the issues are in an md document among other md documents. It's this process that I'm looking to improve on.

Honestly, can we get rid of what we've done for work and start over using this data as a starting point?

## Understood Intent

- Removal is done in the same change as this draft: the work-tracking code, `/work.txt`, `/work.md`, the `./v3 work` CLI, its tests and reference docs are gone; the old plans stay as superseded history. Existing `#work` threads on zenmemes.com are left as they are.
- Keep the part that works: after `import-instance`, an agent reads every post in a window, recovers context, merges repeats, splits combined requests and classifies them ([checklist](../zenmemes_feedback_automation_checklist.md)).
- Change where the result lands: tracked items with stable identity and status, instead of a dated table among other plans.
- Starting data: the [October 30 summary](../zenmemes_meta_feedback_2026-10-30_summary.md) (64 feature requests, 26 bug reports, 7 other) and the [production feedback register](../production_feedback_register.md) (17 PF items).

## Problem Statement

Triaged feedback ends up in dated Markdown tables whose item numbers mean nothing outside that file, so nobody can see or update what is open.

## Concepts touched

- All options: feedback catalog (the summary format), production feedback register (absorbed or retired), `import-instance` (used, unchanged).
- Option A: forum threads as items; status replies (rebuilt).
- Option B: proposed *item register*, one `Key: value` file per item.

## Solution Options

### Option A: Triage creates forum threads

**Estimate: 0.70**

Triage proposes create, attach and skip actions; on apply it posts one signed thread per item listing all its sources, and status changes are replies. This is the removed design with a better intake.

- Pros: visible and discussable on the site; members can comment where the feedback came from.
- Cons: rebuilds most of what was just removed; item text can't be edited once posted; every apply depends on the site being reachable (see today's timed-out sweep); estimates and kind live in post text.
- Driver: signed posting, retry recovery and status derivation again. Uncertainty: medium.

### Option B: Triage writes an item register in the repository

**Estimate: 0.55**

Each item is a plain `Key: value` file (title, kind, status, sources, estimate, notes) in one folder. Triage reads the imported posts, proposes creates, attaches and skips against the existing items, and on apply writes or updates files; you review the diff and commit. Status changes are file edits, so commits and Railspike plans can reference an item ID. A generated index (and later a page) shows what is open.

- Pros: editable, diffable, works offline; agents already work this way; one place replaces dated summaries and the PF register; follows the project's `Key: value` rule.
- Cons: site members can't change status; visibility on zenmemes.com needs a later page; edits from several people mean ordinary merge conflicts.
- Driver: item format, matching new feedback against existing items, seed migration. Uncertainty: how well matching avoids duplicates.

### Option C: One running register document

**Estimate: 0.25**

Triage updates a single persistent register (the PF register, extended) instead of writing a new dated summary each time.

- Pros: smallest change; reuses the existing format.
- Cons: one large table; status edits and merges get hard to review; same "item in a document" problem at a bigger size.

## Recommendation

**Option B.** It keeps the agent judgment that works, gives every item a stable ID and editable status, and needs no site round trip to apply. A site page or forum mirror can come later from the same files.

**Vertical-slice viability:** after an import, the agent proposes actions for the October 30 window; you review, apply and commit; the seed items (summary plus PF register, merged) exist as files; a later run on new posts attaches a repeat to its existing item instead of creating a duplicate; the generated index shows open items by kind.

**Open questions for Step 2:** folder location; ID scheme (keep `PF-` IDs or renumber); whether the 10-18 and 10-21 summaries are seeded or treated as already covered by 10-30; whether existing `#work` threads are listed as sources.

## Enhancement Requests

- `./v3 work sweep` failures from 2026-11-04 (timeout handling, duplicate done posts, duplicate request threads): moot after removal.
- Work page and its mockup: parked; revisit as a view over the register.

## Study Snapshots

2026-11-04T14:15:00Z; scope v1; starting revision `48ab290`; reference R1 not re-verified in this session. Claude, single exposed rater; the removed code and the October 30 summary were read before rating, so no pristine pre-inspection estimate is claimed.

- Step 1 handoff: **0.55** for Option B (item format, triage apply, seed migration, index). Alternatives A 0.70, C 0.25, non-additive. Not hours; no implementation or approval implied.

Status: Approved Step 1 (2026-11-04), Option B.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
