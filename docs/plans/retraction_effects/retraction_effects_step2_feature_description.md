> **Feature plan:** [Step 1](./retraction_effects_step1_solution_assessment.md) · [Step 2](./retraction_effects_step2_feature_description.md) · [Step 3](./retraction_effects_step3_development_plan.md) · [Step 4](./retraction_effects_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, cycle `v3/retraction_effects`; standing P2 enrollment; [ledger](../effort_estimation_trial.md#cycle-v3retraction_effects). Scope v5: first slice of the user-approved split; [approved v4 scope preserved](./retraction_effects_step2_v4_historical.md).

## Problem and user stories

A deleted or retracted post can leave separately stored thread labels active. Fix that reported behavior through both removal paths while preserving independently authored replies.

- As an author, I want retracting a post to withdraw its generated thread labels so that its effects stop applying.
- As an operator, I want deleting a source or repairing an old deletion to remove its label records so that rebuilding does not restore them.
- As a participant, I want my replies and independent label actions preserved so that removing another post does not remove my contributions.

## Core requirements

- **Label ownership:** reliably link newly generated thread-label records to their source post; recursively withdraw proven source-owned label dependencies. Recompute effective labels and label-derived scores, vote counts, activity and board ordering from surviving contributions. A shared label remains while another independent source supplies it; preserve standalone button/operator labels and unrelated records.
- **Both removal paths:** reuse browser Retract and operator `delete-record`, retaining existing authority and protected-record rules. Retraction makes owned labels inactive while preserving canonical evidence; physical deletion removes owned generated label records and retains durable removal/ownership evidence. Preserve independently authored human/agent replies, missing-root/parent placeholders, reply navigation and new replies to surviving posts. Parentage alone never implies ownership.
- **Historical repair:** inspect legacy labels and already deleted sources using reliable history/evidence. Timestamp/author equality or a bulk import commit alone cannot establish ownership. Provide operator inspection and repeat-safe repair, with a way to record an explicit, auditable ownership resolution when historical evidence is insufficient. Unresolved associations preserve unrelated data and visibly remain incomplete; never claim automatic repair of every legacy case.
- **Durability/recovery:** reads, incremental updates, rebuilds, imports and published pages agree. Old archives cannot silently restore removed sources or their owned label effects. Prevent concurrent label writes or stale publishers from restoring withdrawn labels; retain accepted removal through partial failure with actionable retry and truthful status. Reuse browser retry and CLI diagnostics; do not require a new signature for an already accepted retraction.
- **Feedback/preservation:** show material removal/recovery consequences through existing UI/CLI flows. Preserve audit attribution, existing privacy/local cleanup and independently authored content; no new navigation or blanket reply deletion. Required copy follows repository UI guidance.

## Delivery scope and completion boundary

Application changes, focused tests, label-removal/retraction/deletion/repair documentation, this cycle's plans and study bookkeeping. Completion is an end-to-end label-cleanup release through both existing entry points, including missing-root preservation, legacy resolution and retry/import/rebuild/publication verification. First-cycle completion does **not** complete the parent all-effects request.

Remaining generated subjects/event metadata, post reactions, machine scoring/classification, generated expansions and pending non-label automated work belong to follow-on cycle(s), not this release. Preserve their existing behavior/privacy guarantees; do not claim their cleanup gaps are fixed. General label editing and standalone Unpin remain excluded. The same study observation retains the original goal; no independent success is counted for this reduced slice.

## Known environment and flow

On ZenMemes, `delete-record <reply-id>` left `pin`/`pinned` records on `thread-20260526032027-014695b3`. Replay this relationship on a ZenMemes-configured fixture; verify phone/desktop thread and board views plus API labels. Historical ambiguity must be resolvable through documented operator inspection/explicit resolution, without guessing. Actual zenmemes.com deployment verification remains separate and unperformed.

1. Retract or delete a source through the existing authorized action.
2. Withdraw proven owned label effects; preserve replies and independent contributions.
3. Check labels/order or use the visible retry; operators inspect and resolve historical orphans.
4. Reload, rebuild and import an old archive: owned effects stay withdrawn and replies remain usable.

## Shared components and risks

Extend canonical label ownership/removal, post write/retraction, CLI deletion/attributes/repair, shared read-model aggregation and publication invalidation. Reuse action menus and prepared-retraction APIs. Consumers: standard/Forte boards/activity, thread/post/profile/tag listings, QDB cards/search, text APIs/feeds, generated pages, offline snapshots and downloadable SQLite. Source/archive endpoints preserve evidence/privacy rules; no per-view ownership logic or new UI destination.

- **False historical ownership:** validate the reported imported records before Step 3; preserve ambiguity and require auditable resolution.
- **Lost replies/shared labels:** validate physical-root placeholders and multi-source contributions before finalizing stages; avoid promoting a reply to the removed author's content.
- **Resurrection/partial deletion:** review write locks, durable evidence, import and publication before dependent stages; verify interrupted, repeated and concurrent actions.

Success: both paths clear solely source-owned labels; independent contributions survive; physical root/reply removal preserves conversation; the reported legacy case has a verified repair or explicit unresolved recovery; retries/rebuilds/imports cannot resurrect effects. Focused integration and phone/desktop affected-theme checks pass with omissions recorded.

## Study checkpoint and approval

Scope **v5**, first slice of the explicitly approved split; original approved v4 and its estimates/limitations remain historical. 2026-10-23 UTC; Codex, single exposed rater; baseline `e960f666e1146d26b2f9435d98a447c2594b9883`; R1/P2. Projected total remains unavailable pending exact frozen calibration report/diff recovery, not zero or measured effort. Parent all-effects outcome remains incomplete; follow-on cycle(s) are pending, not started observations. User explicitly approved Step 2 v5 with “Approved Step 2.” Executable Step 3 drafting authorized; no implementation or commits authorized.
