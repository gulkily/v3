> **Feature plan:** [Step 1](./fastmod_ownership_step1_solution_assessment.md) · [Step 2](./fastmod_ownership_step2_feature_description.md) · [Step 3](./fastmod_ownership_step3_development_plan.md) · [Step 4](./fastmod_ownership_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/fastmod_ownership; [ledger](../effort_estimation_trial.md#cycle-v3fastmod_ownership), standing P2 enrollment
Extended estimates: enabled
Status: Step 1 Option A approved; Step 2 proposed; implementation unapproved
**Cycle estimate: 0.65**

## Problem and user stories

Fastmod needs an authorship category that returns both a claim probability and a likely claimed name; numeric-only results cannot represent both.

- As an operator, I want to select Ascertain ownership alongside existing rubrics so that ordinary Fastmod work assesses authorship claims.
- As a reader of Fastmod results, I want a probability and claimed name so that I can distinguish an assertion from verified identity.

## Core requirements

- Make `ascertain_ownership` visible as “Ascertain ownership” in the existing category selector. Leave existing selections/defaults intact; the operator explicitly enables it through the existing authorized Save flow. Reuse the current provider request and worker lifecycle.
- Return a probability from 0 to 1 that the target message claims authorship of itself or the work it presents. A bare first-person opinion, quoted byline, or third-person mention alone is not an authorship claim. Use supplied message/context evidence only; no external identity lookup or invented names. This is advisory inference, never proof of identity, ownership, or copyright.
- Return the most likely claimed author name when supported; otherwise unknown, including unnamed claims. Context may clarify a target claim but cannot substitute a parent/root author's identity. Unicode names must remain readable and safely rendered; ambiguity must not force a name.
- Retain both outputs together through storage, ordinary result display, and supported export/import. Preserve existing numeric rubrics, old results, revision/content matching and moderation/meta behavior. Earlier results without authorship data remain absent, not zero or unknown results manufactured by migration.
- Reject malformed probabilities or invalid name output through existing failure/retry feedback without fabricating success. Keep explicit unknown-name results valid. Bounded context/truncation remains in force; do not imply that unseen content was assessed.

## Delivery scope and completion boundary

Work type: application change. Deliver the selectable rubric, compatible shared result evolution, storage/transfer/display integration, focused regressions and rendered-flow verification. Include these cycle records, study-ledger bookkeeping, touched scoring concept and necessary contract documentation. No dependencies, automatic identity changes, new moderation/tagging action, historical backfill, provider activation or deployment.

Known environment: the user opens `http://127.0.0.1:8001/tools/fastmod/` and currently cannot see the proposed category because only planning was published. After implementation reaches that running checkout, verify that exact URL shows the category. An authorized operator can enable and save it, process a new eligible message through the ordinary worker, and inspect both outputs on the post result view. A disabled worker/provider must retain existing actionable setup feedback; malformed responses must expose failure and permit the existing retry path. Record the exact local flow as verified or explicitly unverified; another server's pass does not establish recovery here.

Simple flow: 1. Open Fastmod and enable the category. 2. Save with the existing authorized identity. 3. Process a new eligible message. 4. Read probability and claimed author/unknown; use existing retry after failure.

## Shared components and risks

Reuse/extend: `prompts/fastmod/`, `FastmodRubrics` and `templates/pages/fastmod.php` for category discovery/selection; `public/assets/fastmod.js` and the existing prepare/finalize rubric APIs for signed saves; `FastPostScorer`, `FastScoreResult`, `FastScoreContextFactory`, `FastScoreWorkflowService` and `SqliteFastScoreStore` for evaluation, bounded evidence and retry; `templates/pages/post.php` for results; `FastmodScoreSnapshot`, `FastmodScoreTransfer` and `Import/FastmodScoreImport` for transfer. Extend existing consumers rather than adding a parallel evaluator or selector. Inventory API/CLI result consumers and dynamic/static post rendering in Step 3.

- Numeric-contract compatibility: inspect selected/unselected, legacy and transfer consumers before the development plan; require round-trip and unchanged moderation/meta checks. Resolve any migration/rebuild requirement before dependent implementation.
- Misattribution: test no claim, named/unnamed claim, quoted attribution, ambiguity and conflicting reply context early; retain unknown rather than infer a real identity. Provider accuracy needs a labeled evaluation distinct from deterministic integration tests.
- Unsafe or excessive output: define bounded name validation and safe rendering before implementation; exercise instruction injection, Unicode, long names and malformed-output recovery. Verify phone/desktop, affected themes/roles and initial/empty/success/failure states.

Success criteria: the exact local selector contains the category; authorized Save persists selection; controlled provider fixtures demonstrate valid named and unknown results through worker/store/post/transfer; invalid output reaches failure and recovers on retry; old results and unaffected categories retain their behavior. Record live-provider and local-server verification limits honestly.

Concepts touched: [Fastmod scoring](../../concepts/fastmod_scoring.md), changed; cryptographic authorship used unchanged. Update the existing inventory at implementation, no separate concept proposed.

## Study checkpoint

2026-11-03T17:24:49Z; v1/A, original baseline `622bb4fa6fce203e0edf0416a6bc3d6824e9a736`, R1/P2. User explicitly approved Step 1; earlier plan merge/push authorized publication only. Whole-cycle 0.65 reaffirmed after checking bounded context, selector and ordinary post results; all three frozen R1 hashes reverified. Codex single exposed rater; pristine intake remains missing, earlier Step 1 0.65 retained. No measured labor, implementation, independent rating or accepted outcome. Local-URL report is now an explicit completion condition, not evidence of runtime failure recovery. User authorized merging the current tree on 2026-11-03; publishing Step 2 does not approve its requirements or implementation. Await Approved Step 2.

*— Codex · 2026-11-03 UTC*
