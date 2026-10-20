> **Feature plan:** [Step 1](./fastmod_rubric_actions_step1_solution_assessment.md) · [Step 2](./fastmod_rubric_actions_step2_feature_description.md) · [Step 3](./fastmod_rubric_actions_step3_development_plan.md) · [Step 4](./fastmod_rubric_actions_step4_implementation_summary.md)

Debug mode: enabled

Effort study: E07 — [trial ledger](../effort_estimation_trial.md#e07--fastmod-rubric-actions)

## Original Query

Let's include this cycle, please. Let's start a new Step 1 for the current state of things and how to build on top of existing Fastmod. Right now we only have two rubrics. One of them is for moderation hold content (abuse) and one of them is for meta content (probably just need to add a tag to the item). We may add more rubrics later, but they probably can be reduced to rubric → add tag → tag leads to action or scoring change, right? What do you think?

## Understood Intent

Assess reusable rubric-driven outcomes on top of delivered Fastmod, rather than resume the blocked [prepublication-hold cycle](../fastmod_prepublication_hold/fastmod_prepublication_hold_step4_implementation_summary.md). Its records remain intact; its approvals do not authorize this cycle.

## Current state and problem

[Fastmod](../../reference/fast_post_scoring.md) already supports extensible rubrics, independent probabilities, bounded reply context, signed rubric selection, private revision-bound results and retries; it currently takes no tag, ranking or moderation action. `meta` means application support; `moderation` means need for human review and includes more than abuse. We need a reliable boundary between uncertain classification and consequential action.

## Option A — Every outcome through tags

Threshold each rubric into a tag, then let tag rules control discovery, ranking and moderation.

- Pros: Simple common vocabulary; tags offer visible, reusable classification.
- Cons: Binary tags lose confidence; ordinary user tags could accidentally acquire enforcement authority; removal, conflicting sources and stale evidence need explicit semantics.

## Option B — Evidence-backed policy with optional tags

Keep rubric scores as evidence; an explicit policy determines whether to add a derived tag or invoke an authorized action. Tags support discovery, while consequential actions retain their own eligibility and authority checks.

- Pros: Supports the proposed chain without making every tag authoritative; preserves confidence, overlapping classifications, provenance and human overrides.
- Cons: Requires explicit thresholds, ownership and recovery rules beyond a rubric-to-tag lookup.

## Recommendation and releasable slice

Choose **B**: rubric → score → policy → tag and/or action. A rubric need not have a one-to-one tag, and a continuous ranking adjustment need not become binary first. Meta and moderation can both apply to the same post.

- Start with `meta` alone. Defer `bug`, `feature_request` and `help` subcategories until review demonstrates a discovery/routing need and reliable, potentially overlapping definitions. The existing meta probability cannot identify a subtype; finer classification would need additional evidence, not arbitrary tags.
- First proposed release: a newly scored local thread or reply can acquire a discoverable Fastmod-derived `meta` tag under an explicit threshold; authorized users can correct it, inspect its origin and recover failed application. Reuse existing tagging where its target/identity semantics fit; do not silently impersonate a human tagger or rewrite signed post bytes.
- Preserve model/content/rubric/policy provenance and separate human tags from generated tags. Reprocessing must be idempotent; stale, failed or missing scores cannot create classifications. Define rescoring, correction persistence and withdrawal of derived tags; disabling automation stops new changes and offers deliberate rollback of machine-owned tags without deleting human tags.
- Defer automated moderation holds, author standing, ranking changes, bulk historical retagging and a general rule-builder UI. These remain separate requirements, not completed by tagging. Moderation needs human-labeled calibration, protected intake/distribution, audited overrides and explicit rollback; the earlier privacy blockers remain.
- Before Step 2 approval, settle item versus thread tag scope, threshold validation, who may correct generated tags, correction persistence, and whether this first meta-only outcome meets the intended release. Do not add future rubrics or invent ranking formulas in this cycle.

## Study snapshots

E07; rater Codex (single, exposed); reference R1; UTC 2026-10-20; scope v1; starting revision `4c521b743575979b8fefc66b38271551a57a08ef`. Outcome/recovery: the meta-tag journey above, including correction, stale-score handling, retry and machine-owned rollback. Existing scorer, tagging and browser-signing infrastructure are dependencies.

- Intake: **0.85 Railspike units**, recorded after prior exposure to historical Fastmod plans and read-only screening of scoring/tagging source; not a pristine pre-inspection estimate. No inherited numeric feedback score. Earlier hour estimates concern the predecessor's different scope and are not converted.
- Step 1 handoff: **0.85 reaffirmed**, total projected cycle including planning, implementation, verification, documentation and ordinary correction. Broader than toolbar identity (0.35) or composer decluttering (0.60), below provisional startup recovery (1.0), conditional on reusable tag contracts; unresolved tag authority/correction semantics could change it. This does not rate the deferred moderation/ranking system or imply independent measurement.

## Request disposition

Include this cycle in the trial and start a fresh assessment: incorporated, user-authorized planning only. Generalize rubric outcomes: assessed with Option B recommended; awaiting Step 1 approval. Prepublication holds remain deferred with predecessor evidence preserved.

- Refinement: user agrees automated meta tagging is useful and asks about finer categories. Recommend meta alone for this slice; defer subtypes until demonstrated demand and validation. In-scope planning refinement; not `Approved Step 1`. Scope v1 and projected total 0.85 remain unchanged.
