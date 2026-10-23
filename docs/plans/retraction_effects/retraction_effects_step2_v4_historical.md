> Historical approved all-effects scope v4; retained after the user-approved split. Current scope: [Step 2](./retraction_effects_step2_feature_description.md).

> **Feature plan:** [Step 1](./retraction_effects_step1_solution_assessment.md) · [Step 2](./retraction_effects_step2_feature_description.md) · [Step 3](./retraction_effects_step3_development_plan.md) · [Step 4](./retraction_effects_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, cycle `v3/retraction_effects`; standing P2 enrollment; [ledger](../effort_estimation_trial.md#cycle-v3retraction_effects).

## Problem and user stories

Removing a post leaves separately stored effects active; the reported deleted pinning reply still supplied `pin` and `pinned` after rebuilding. Cleanup must remove generated effects without removing independently authored replies.

- As an author, I want retracting my post to withdraw its generated effects so that its labels and other contributions stop applying.
- As an operator, I want `delete-record` and historical repair to clean up source-owned effects so that deletion and rebuilding agree.
- As a participant, I want my replies and independent contributions preserved when another post is removed so that conversation remains usable.

## Core requirements

- **Ownership:** trace source-generated labels/actions and their generated dependents recursively. Inventory thread labels/board tags, post reactions, scores/votes, counts/activity/order, generated subjects/event metadata, automated outputs and pending work. Classify each as withdrawn, recomputed, already covered, independent or irreversible; no unexamined family at release. Preserve independent button/operator actions and other sources' contributions; shared labels disappear only when no surviving source supplies them.
- **Content boundary:** preserve authored replies, including agent replies treated as conversation. Parentage or automated authorship alone never authorizes cascading content removal. Remove proven source-owned generated metadata, cached representations and expansions that cannot remain valid without their source. Preserve placeholders/thread structure for physically deleted roots or parents, including reply access and new replies to surviving items. Keep existing permissions, protected identity/approval/invitation rules, truthful attribution and audit history.
- **Both entry points and history:** browser Retract and operator `delete-record` apply the same ownership policy while retaining their distinct signed-suppression versus physical-file-removal behavior. Retraction leaves canonical evidence but makes owned effects inactive; deletion removes owned generated records while retaining the evidence needed to prevent resurrection. Record reliable provenance for new effects; recover old links from trustworthy history. Provide operator inspection and repeat-safe repair for already missing sources. Ambiguity preserves unrelated data and reports unresolved work rather than guessing or declaring completion.
- **Durability and recovery:** normal reads, imports, incremental updates, full rebuilds and generated releases agree. Reimporting an old source or generated record cannot silently restore an accepted removal. Queued/in-flight jobs and stale publishers cannot restore effects. Partial cleanup exposes incomplete status and a retry path without duplicate actions, extra signatures for accepted retractions or loss of preserved replies.
- **Feedback and limits:** reuse existing controls and confirmation/recovery surfaces; expose material consequences and unresolved work without routine explanatory copy or additional approval steps. Preserve existing privacy/local-cleanup guarantees. Previously delivered notifications, provider calls and external copies cannot be recalled; cancel pending source-dependent work and withdraw attributable controlled outputs.

## Delivery scope and completion boundary

Application change plus focused tests, retraction/deletion operator documentation, feature plans and study-ledger bookkeeping. No general label editor, standalone Unpin, new retraction permissions or recursive deletion of authored conversations. Normal entry is **… → Retract post** or `delete-record <source-id>`; completion means owned effects are gone, independent replies remain usable, and reload/rebuild cannot restore effects. Release requires the inventory, both end-to-end paths, historical repair and failure/concurrency verification; unknown legacy attribution remains explicitly unresolved with an operator action.

## Known environment and flow

On ZenMemes, deleting reply IDs left separate pin-label records for `thread-20260526032027-014695b3`. Future removal should clean them automatically; for already deleted sources the operator inspects and repairs proven orphans, then checks the thread API and board. Browser users retry partial retractions through the existing recovery action; CLI users receive actionable inspection/retry instructions. Verify on a ZenMemes-configured fixture with the reported source/label relationship, phone and desktop; actual deployment acceptance on zenmemes.com remains distinct.

1. Remove a source through the existing authorized action.
2. Withdraw its proven generated dependency chain; preserve conversation and independent contributions.
3. See the resulting labels/order, or actionable incomplete feedback; retry or repair as indicated.
4. Reload and rebuild/import: effects stay withdrawn and preserved replies remain accessible.

## Shared surfaces

Extend canonical effect/retraction ownership and existing write/read-model services, not per-view cleanup. Reuse thread-root/reply/individual-post action menus and prepared-retraction APIs; extend CLI deletion, thread-attributes diagnostics and repair support. Consumers include standard/Forte boards and activity, thread/post/profile/tag listings and counts, QDB search/cards, text APIs, feeds, generated static pages, offline snapshots and downloadable SQLite. Source/download endpoints retain existing evidence/privacy boundaries. Reuse publication invalidation, task workers, scoring and LLM-exchange cleanup; no new navigation destination is proposed.

## Risks and success criteria

- **Legacy misattribution:** inspect reported archive/history before Step 3; require positive ownership evidence and explicit unresolved outcomes.
- **Lost replies/shared contributions:** validate removed-root reconstruction and multi-source labels before committing the development plan; preserve independent records and truthful totals.
- **Resurrection/partial completion:** review import, worker and publication boundaries before Step 3; plan interrupted/repeated/concurrent operations and retained removal evidence.

Success: both entry points remove proven generated effects; surviving contributions retain shared labels; root/reply removal preserves other replies; historical repair handles the reported pin case or identifies missing evidence; retries, rebuilds and imports do not restore withdrawn effects. Required phone/desktop/theme and focused integration checks pass with limitations recorded.

## Study checkpoint and approval

Step 1 v4 approved; Step 2 clarifies v4 without restoring the superseded reply cascade. 2026-10-23 UTC; Codex, single exposed rater; baseline `e960f666e1146d26b2f9435d98a447c2594b9883`; R1/P2. Added exposure: hashtag write flow and deletion/read-model entry points. Projected total remains unavailable pending exact frozen calibration report/diff recovery; prior unavailable snapshots remain in Step 1. No measured effort or completed outcome. Step 2 v4 approved by the user with “Approved Step 2.” Step 3 planning authorized; implementation and commits remain unapproved.
