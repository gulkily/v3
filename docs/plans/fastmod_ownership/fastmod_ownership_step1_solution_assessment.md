> **Feature plan:** [Step 1](./fastmod_ownership_step1_solution_assessment.md) · [Step 2](./fastmod_ownership_step2_feature_description.md) · [Step 3](./fastmod_ownership_step3_development_plan.md) · [Step 4](./fastmod_ownership_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/fastmod_ownership; [ledger](../effort_estimation_trial.md#cycle-v3fastmod_ownership), standing P2 enrollment
Extended estimates: enabled
Cycle estimate: 0.65
Status: Step 1 proposed; implementation unapproved

## Original Query

Please add a Fastmod category called something like "ascertain ownership" that tries to figure out if the message claims authorship and outputs a probability and most probable author name.

## Understood Intent

Offer “Ascertain ownership” as a selectable category. Its probability estimates whether the message claims authorship; its author name identifies the most likely claimed author from supplied evidence, or unknown. Neither output verifies identity, signature ownership, copyright, or the truth of a claim. Quoted attribution and third-person mentions must be distinguished from the message's own claim.

## Problem

Fastmod's numeric-only category results cannot currently carry an author name.

## Option A — Extend category results

**Estimate: 0.65**

Add the category through the existing rubric selector and extend the shared result contract to retain its probability and companion name through scoring, storage, display, and score transfer.

- Reuses the ordinary Fastmod flow and one provider request; existing moderation/meta probabilities retain their meaning.
- Requires compatible result-format evolution and clear unknown-name handling; transfer and legacy consumers are the main uncertainty.

## Option B — Separate authorship evaluator

**Estimate: 0.85**

Expose the category in Fastmod but evaluate authorship through a separate request and independently stored result, leaving numeric scoring intact.

- Isolates the new output format and allows independent retries.
- Adds request cost, orchestration and partial-result states for one category; duplicates parts of the scoring lifecycle.

## Recommendation and completion boundary

Choose A as one vertical slice: select the category through Fastmod, score a message through the existing worker, and see both outputs in the ordinary result view. Include no-claim, unnamed claim, quoted author, ambiguous names, hostile instructions and malformed-response/retry cases. Preserve old numeric results and compatible export/import; decide any required schema migration before implementation. Verify affected shared views, roles, themes and phone/desktop rendering. No automatic identity assignment, moderation action, existing-data backfill, deployment, or new dependency is proposed. Activation/default policy is to be fixed in Step 2.

Concepts touched: [Fastmod scoring](../../concepts/fastmod_scoring.md), changed under either option; cryptographic authorship used unchanged, no new inventory entry proposed.

Reuse decisions: `prompts/fastmod/*.txt` and `FastmodRubrics.php` supply categories; `templates/pages/fastmod.php` supplies selection; `FastPostScorer.php`, `FastScoreResult.php`, `SqliteFastScoreStore.php` and `FastmodScoreTransfer.php` own the existing result lifecycle. Extend these contracts under A; no new selector or parallel scoring service is needed. The introduction currently requires numeric CSV, so a prompt-only addition would not fulfill the request.

Study checkpoint: 2026-11-03 UTC; scope v1/A; baseline `622bb4fa6fce203e0edf0416a6bc3d6824e9a736`; R1/P2, all three frozen host-local hashes verified. Pristine intake missing: guidance and initial scoring-source inspection preceded rating. Step 1 whole-cycle projection 0.65; alternative B 0.85; single exposed Codex rater. Includes inspection, planning, implementation, verification and documentation; scores are neither hours nor additive forecasts. No measured labor, independent rating, accepted outcome or earlier estimate invented. Ledger bookkeeping and touched concept documentation belong to this cycle; User explicitly authorized merging and pushing this plan on 2026-11-03; publication does not approve Step 1 or implementation. Await Approved Step 1.

*— Codex · 2026-11-03 UTC*
