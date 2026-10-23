> **Feature plan:** [Step 1](./retraction_effects_step1_solution_assessment.md) · [Step 2](./retraction_effects_step2_feature_description.md) · [Step 3](./retraction_effects_step3_development_plan.md) · [Step 4](./retraction_effects_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, cycle `v3/retraction_effects`; standing P2 enrollment; [ledger](../effort_estimation_trial.md#cycle-v3retraction_effects).

## Original Query

Can you write Step 1 for just making sure that when something is retracted all its effects are undone?

## Understood Intent

Retracting an item should withdraw the effects it introduced, including labels created through a reply. The motivating example is retracting a pinning reply and finding that the thread remains pinned after rebuilding. The user subsequently confirmed deleting the source reply with `delete-record <reply-id>`; its separate `pin` and `pinned` label records survived. Both signed retraction and physical deletion through `delete-record` are now in scope. This is a separate assessment from the earlier explicit web-unpin proposal (its separate artifact is unavailable in this checkout).

## Problem

Retraction and physical source deletion can leave separate dependent records active, so effects survive their source and reappear after rebuilding.

## Options

**Option A — Source-aware withdrawal.** Treat a retracted or deleted item's contributions as inactive and recompute current results from surviving contributions.

- Pros: preserves other people's independent actions; works after retries, imports and rebuilds; handles overlapping labels without blindly reversing totals.
- Cons: needs a complete effect inventory and trustworthy source attribution; legacy ambiguity needs explicit handling.

**Option B — Compensating actions.** On retraction or deletion, issue opposite actions for each effect the item caused.

- Pros: explicit history of reversals; can reuse existing action workflows.
- Cons: inverse actions may override newer legitimate changes; duplicate delivery and partial failures complicate recovery; not every effect has an inverse.

**Option C — Remove dependent items recursively.** Retraction or physical deletion initiates a recursive cascade through everything generated from or responding to the original item, withdrawing all affected effects.

- Pros: broad removal of dependent content and effects.
- Cons: can erase independent replies and later decisions; expands retraction authority and consequences far beyond the reported problem.

## Recommendation

Choose **a narrowed C: recursively clean up generated effects**, following the user's clarification that independently authored replies must survive. A source's generated label/action records can have further generated dependents; reply parentage alone does not make authored content an effect to remove. This supersedes the earlier broad cascade recommendation.

- Cover both browser Retract and operator `delete-record`. Removing a root or reply preserves independently authored replies and their own valid contributions; retain a placeholder or equivalent thread structure when the original source is absent. Do not delete a conversation merely because its root was removed.
- Inventory labels/tags, scores/votes, counts/activity/order, generated metadata/content and pending automated work. Recursively withdraw effects attributable to the removed source and recompute shared results from surviving contributions. A label remains if an independent surviving source still supplies it.
- Keep existing permissions and protected identity/approval/invitation records. Retain truthful action attribution and audit evidence. Step 2 must classify generated content versus independently authored content; neither a parent link nor automated authorship alone justifies deleting an entire reply branch.
- Cover existing and future posts, including already deleted sources. Preserve or recover reliable source-to-effect links for separate label records; discover dependencies before deleting source evidence. Ambiguous legacy attribution requires visible operator recovery rather than guessed ownership or an unqualified success result.
- Prevent queued/in-flight work and stale publication from restoring withdrawn effects. Accepted removals remain effective through partial failure, with visible incomplete status and repeat-safe retry. Preserved replies remain usable.
- Previously delivered notifications, provider calls, human knowledge and externally held copies cannot be recalled. Cancel pending source-dependent work and withdraw controlled generated effects while retaining canonical history and existing cleanup guarantees.

The vertical slice covers both removal entry points and durable effect cleanup across normal reads, generated views, imports and full rebuilds. Verify the reported reply-deletion/pin-record case, shared labels, already orphaned effects, source root/reply removal with surviving replies, generated dependency chains, permissions, retries, concurrency and phone/desktop use. Step 2 must bound the effect inventory and legacy recovery; split explicitly if needed without dropping the preservation requirement. General label editing, standalone Unpin and recursive deletion of authored conversations are outside scope.

## Effort snapshots and approval

2026-10-23 UTC; Codex, single exposed rater; scope v1; start `e960f666e1146d26b2f9435d98a447c2594b9883`; R1/P2. Prior pin/retraction source and documentation inspection preceded intake; no pristine prospective intake is claimed. Intake and Step 1 numeric projections are unavailable: frozen R1 calibration report/diff sources remain absent; the predecessor verified frozen feedback from its recorded Git revision. Recover exact sources before rating; no substituted reference, measured effort or completed outcome. Documentation scope includes ledger bookkeeping.

Revision checkpoint, 2026-10-23 UTC: scope **v2**, user-selected Option C; same baseline, R1/P2 and exposed single rater. Scope expands from withdrawing one source's effects (v1) to recursively retracting dependent items, including cross-author replies, with preview, authority and resumable completion requirements. Numeric projection remains unavailable pending exact R1 recovery; the v1 checkpoint above is preserved, not reinterpreted as a v2 estimate.

Revision checkpoint, 2026-10-23 UTC: scope **v3** explicitly adds physical source deletion via `delete-record`, source/dependent linkage, and recovery for already deleted sources. v1/v2 snapshots remain historical. Same baseline, R1/P2 and exposed single rater; new evidence includes the live archive retaining both pin-label records after reply deletion. Numeric projection remains unavailable pending frozen-reference recovery; no implementation approval or completed outcome.

Revision checkpoint, 2026-10-23 UTC: scope **v4** narrows C to recursive generated-effect cleanup after user clarification. Independently authored replies and their valid contributions survive both retraction and `delete-record`; root removal retains conversation structure. Broad cross-author reply cascades and their proposed authority/preview requirements in v2/v3 are superseded. Original query and historical options/checkpoints remain intact. Same baseline, R1/P2 and exposed single rater; numeric projection remains unavailable pending exact frozen-reference recovery.

Scope v4 approved by the user with “approved step 1.” Step 2 drafting authorized; implementation and commits remain unapproved.
