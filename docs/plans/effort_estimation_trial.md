# Effort estimation trial ledger

[Setup plans](./effort_estimation_trial/) · [Your checklist](./effort_estimation_trial_maintainer_checklist.md)

## Current state

Setup in progress. No candidate feature is authorized or active. Completed outcomes: **0 / 6**. The setup cycle itself is not a study outcome. Candidate records will be added after the protocol stage is committed.

## Frozen reference set R1

Recorded 2026-10-19T03:20:46.805149+00:00. Definition: **1.0 is the largest well-scoped, releasable slice we would comfortably approve for one Railspike cycle, including required verification and documentation.** Startup dependency recovery is the provisional 1.0 reference, conditional on its existing installer/test infrastructure. Maintainer acceptance is pending. Smaller comparisons include duplicate preview suppression (0.10), about-copy integration (0.20), numeric permalinks (0.25), score emphasis (0.30), toolbar identity (0.35) and composer decluttering (0.60).

| Source | SHA-256 |
| --- | --- |
| [Calibration report](../../../../railspike-dev/docs/research/effort_estimation/report.md) | `d3a30b6400b83bb25283b4234b41ba07515df9a696ea44de59c10186103771b0` |
| [Implementation diff review](../../../../railspike-dev/docs/research/effort_estimation/diff_review.md) | `068b3519326e08a939df40b5abe0106ddcbab80bd796616c76b5084a21462b84` |
| [User feedback](./zenmemes_meta_feedback_2026-10-18_summary.md) | `ef335604ecd5b51e5c04067d88e4d6f01cb4fdb21b8f4d7dba7b5b90e4012bfc` |

Reference documents live in an external checkout. If unavailable, restore these exact sources and verify hashes before rating; do not silently use changed anchors. Explicitly version any accepted replacement as R2, retaining R1 and affected score histories. The same frozen reference set applies across a batch. Local path relocation may repair links without changing content identity.

## Rating protocol

- Preserve inherited feedback scores independently of initial scoped estimates. One agent is the current rater, exposed to the feedback scores and calibration examples: results are neither blind nor independent of those sources. A second rater is optional, not assumed.
- Rate the **total scoped cycle from its starting point**, including inspection, planning, implementation, required verification, documentation and ordinary correction. Exclude approval waiting and unrelated cleanup. Freeform nonnegative decimals are judgments, not elapsed hours, priority, approval, additive forecasts or measured actual effort. Values above 1.0 prompt a split discussion without waiving recovery.
- Preserve the intake scope/estimate before detailed implementation inspection. Planning/status evidence may screen for prior work first; record prior exposure and any inspection already performed. Do not imply an initial score is uninformed by available plans.
- Record artifact snapshots at Step 1 handoff when used, Step 2 handoff, Step 3 handoff and Step 4 entry. Explicitly reaffirm unchanged values. Append new snapshots on revisions with a reason distinguishing scope change from new understanding; never erase prior values. Preserve approval context. Implementation discoveries can trigger a new total projection before dependent work; changed scope still requires normal approvals.
- At Step 4 closeout, record a **retrospective scope assessment** and the maintainer's comfortable-fit judgment separately from projections. Missing/skipped checkpoints stay missing/not applicable; do not reconstruct a prospective history. Skipped Step 1 has no zero score.
- Each snapshot carries candidate/cycle ID, checkpoint, UTC date, rater, scope version, reference version, exposure, score, reason and artifact link. Scope versions retain outcome, required recovery, evidence, dependencies and starting Git revision.
- Keep snapshots canonical in feature artifacts after those exist; ledger rows link to them. Intake snapshots live here until a feature exists. Updates during planning remain uncommitted under normal planning rules; include this ledger in each feature's approved documentation scope and commit its accumulated update with closeout. The optional trial does not widen any cycle's write authority.

## Selection and outcome accounting

Use useful unfinished feedback first, cross-checking backlog and feature records. Six proposed scopes are a target; size diversity is a preference, not a quota. Statuses: proposed, selected, active, completed, deferred, replaced, abandoned. Keep replacements with reasons and links. Selection is not approval. Required device/environment checks must be part of the candidate's acceptance evidence.

One completed outcome is the accepted original candidate scope with required evidence and a recorded maintainer judgment. If split, preserve the original estimate/scope and link every child cycle; the original counts once only after its full agreed outcome is accepted. A deliberately reduced/replaced scope is versioned and cannot be silently compared as the original; child cycles are not independent observations by default. Missing acceptance remains visible and does not count as a completed outcome. Six completions do not imply six commits or necessarily six implementation cycles.

## Outcome record template

For each candidate retain delivered scope/version; feature plan/summary links; starting and final revisions and stage commits/diffs; planned/completed stages; verification passed/failed/blocked/pending/inapplicable and omissions; corrective work versus new requests; splits/follow-ups; retrospective scope assessment; maintainer comfortable-fit judgment and acceptance date. Record active-work measurements only if reliably collected; never infer effort from lines, stage counts or timestamps.

## Next action and study review

Finish setup and propose candidates. Then the maintainer reviews selection and the provisional anchor and chooses the first cycle. Each feature gets its own approvals. The agent keeps active candidate, pending items and next action current for resumption. After six accepted outcomes, start a separately approved documentation cycle to assess size errors, scope changes and incomplete acceptance; qualify conclusions if ceiling examples are absent. No measured labor, absolute capacity ceiling or delivery forecast is claimed.
