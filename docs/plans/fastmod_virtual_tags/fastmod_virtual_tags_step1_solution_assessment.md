> **Feature plan:** [Step 1](./fastmod_virtual_tags_step1_solution_assessment.md) · [Step 2](./fastmod_virtual_tags_step2_feature_description.md) · [Step 3](./fastmod_virtual_tags_step3_development_plan.md) · [Step 4](./fastmod_virtual_tags_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/fastmod_virtual_tags; [standing P2 ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
**Cycle estimate: 0.60**
Status: Steps 1–3 approved; implementation completed locally; see Step 4.

## Original Query

Please estimate the complexity of having a layer that automatically emulates these tags without all the overhead of actually writing them.

## Understood Intent

Estimate replacing durable automatic meta classifications with labels derived from existing Fastmod scores, retaining ordinary human labels. Existing code already overlays labels without canonical tag files, signatures or Git commits; the removable overhead is private classification state and its lifecycle.

Problem: Determine how much simpler automatic meta labels can become without promising unchanged persistence and correction semantics.

## Option A — Derive effective tags from scores

**Estimate: 0.60**

Reuse the current shared tag overlay, sourcing eligible root IDs from successful, content-matching meta scores at or above the threshold. Include tag browsing/counts, generated/offline snapshots, transition from existing classifications, verification and documentation. Assume labels track retained scores and current policy, historical scored roots can qualify, and generated copies update on ordinary refresh.

- Removes separate application receipts, durable classifications and retry/rollback lifecycle; no extra provider calls or canonical writes.
- Threshold changes, rescoring, disabling or pruning can change labels. Individual durable exceptions require retained state; existing suppressions need an explicit transition decision before implementation.
- Main work is shared-query integration, score selection and retirement of old behavior. Cache refresh and public/private separation still matter; exact immediate artifact consistency is outside this option.

## Option B — Preserve current behavior through a virtual layer

**Estimate: 0.90**

Preserve new-publication-only eligibility, labels surviving score pruning, manual corrections, stable classifications and immediate generated-output consistency while changing the internal representation.

- Minimizes user-visible behavior changes, but most durable state and lifecycle work remains necessary.
- The current implementation already provides the virtual presentation layer; this yields limited simplification. Migration and recovery across both contracts dominate the estimate.

## Recommendation and checkpoint

Recommendation: A is a coherent moderate simplification if dynamic labels and ordinary-refresh staleness are acceptable. A badge-only prototype would be smaller, but would not emulate tags in discovery/counts/offline output. Avoid adding another layer alongside the existing overlay.

Evidence: [read overlay](../../../src/ForumRewrite/Scoring/FastmodMetaRead.php), `FastmodMetaStore.php` at c5783108 (retired in Stage 7), [score pruning](../../../src/ForumRewrite/Scoring/SqliteFastScoreStore.php), [current contract](../../runbooks/fastmod_meta_tagging.md). Source review only; no performance measurement, live-state check or tests run. No new dependencies proposed.

Checkpoint: Step 1 handoff, 2026-10-29T02:16:33Z; scope v1/A; starting revision 60518d9e7ce80b34c93587d73a04d999b3b462b8; R1/P2. Codex single exposed rater, aware of predecessor E07 estimates. First projection 0.60; alternative B 0.90, independently judged complete-cycle estimates, not additive or hours. All three frozen R1 hashes verified. Pristine intake missing because source inspection preceded rating; no reconstructed earlier score. Includes inspection, planning, implementation, verification, documentation and ordinary correction. No measured labor or accepted outcome. Follow-up to E07 and its shared-flag cycle; same ID across worktrees.

Approval boundary: Estimate request only. Railspike requires Approved Step 1 before Step 2; no implementation or activation follows from this assessment.

*— Codex · 2026-10-29 UTC*

Approval update: User approved Step 1 after selecting the future-friendly score-derived direction. Future mappings should reuse the same layer; no mapping editor or additional tag is authorized. Historical estimate and original query above remain unchanged.

*— Codex · 2026-10-29 UTC*
