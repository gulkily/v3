> **Feature plan:** [Step 1](./thread_like_activity_step1_solution_assessment.md) · [Step 2](./thread_like_activity_step2_feature_description.md) · [Step 3](./thread_like_activity_step3_development_plan.md) · [Step 4](./thread_like_activity_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, cycle `v3/thread_like_activity`; standing P2 enrollment; [ledger](../effort_estimation_trial.md#cycle-v3thread_like_activity).

## Original Query

Looks like my IDE showed stale results, never mind. Please write Step 1 of fixing this bug.

## Understood Intent

Show a newly accepted like in Activity even when another person already liked the thread. The reported local thread is `thread-20261010002251-6cb36993`: its newer like was saved at `2026-10-23T02:23:22Z` in commit `78e1f3f`, but Activity contains only the first `like` addition at `2026-10-10T00:22:57Z`. Incremental and full rebuild aggregation currently emit thread-label activity only when a label first becomes present. The user canceled the request to commit remaining changes; this request authorizes planning only.

## Problem

Thread-like activity tracks first label appearance rather than each accepted person's contribution, hiding valid later likes.

## Options

**Option A — Record each accepted thread-like action.** Derive Activity from accepted like contributions independently of the thread's effective label set, consistently during normal writes and rebuilds.

- Pros: accurately attributes later likes; recovers historical likes from existing records; ordinary Activity and its consumers share one result.
- Cons: must distinguish distinct accepted actions from replay/recovery duplicates, preserve source attribution, and apply existing withdrawal/visibility rules.

**Option B — Aggregate likes into one updated Activity item.** Maintain one item per thread summarizing its likes and latest contributor/time.

- Pros: reduces feed volume; can expose current participation at a glance.
- Cons: changes feed identity, actor attribution and ordering; obscures separate actions and adds a new summary interaction.

**Option C — Show write commits as the workaround.** Direct users to the existing Commits view, where the accepted like's canonical write is already recorded.

- Pros: no new event semantics; retains a technical audit trail.
- Cons: ordinary Activity still misses the user's action; couples user-visible feedback to technical commits and does not fix the reported flow.

## Recommendation

Choose **A**, narrowly for thread-level `like` contributions; preserve other thread-label activity semantics and existing reply-reaction behavior. A separately accepted person's like gets an actor-attributed event at its original action time; replay/retry of that same accepted contribution must not create another event. Existing write eligibility and duplicate-vote rules remain authoritative.

The vertical slice is **Like → accepted result → ordinary Activity entry**, with equivalent classic/Forte views, counts, pagination and supported feeds. Rebuilding must recover eligible historical contributions—including the reported later like—without changing scores, effective labels or vote eligibility. Preserve source-record links, current signature/attribution evidence, hidden/retracted-post filters and the approved source-owned-label withdrawal behavior. If the update cannot publish, retain the accepted like and provide existing actionable recovery rather than claiming the Activity list is current.

Verify two different identities liking an already-liked thread, identical-action replay/retry, historical rebuild, withdrawn source-owned likes, hidden/retracted targets and pagination/count parity; check rendered Activity on phone/desktop. General reaction redesign, aggregate reaction summaries and fixes for unrelated effect families are outside this cycle. The earlier label-cleanup cycle remains a separate observation, not additional implementation approval here.

## Study snapshots and approval

2026-10-23 UTC; scope v1; start `39be82932fcc36c5017fcf368655ef449bf042da`; Codex, single exposed rater; R1/P2. Prior diagnostic inspection of the live local records, database and both aggregators preceded intake; no untouched prospective intake is claimed. Intake and Step 1 total projections are unavailable pending exact frozen R1 calibration report/diff recovery; their recorded external paths remain absent. No substituted reference, reconstructed earlier score, measured effort or completed outcome. Scope includes this cycle's ledger bookkeeping; preserve the existing unrelated ledger draft and `.test_self_approval` file.

User approved Step 1 with “Approved Step 1.” Step 2 drafting authorized; implementation, commits and push remain unapproved for this cycle.
