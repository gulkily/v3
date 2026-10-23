> **Feature plan:** [Step 1](./thread_like_activity_step1_solution_assessment.md) · [Step 2](./thread_like_activity_step2_feature_description.md) · [Step 3](./thread_like_activity_step3_development_plan.md) · [Step 4](./thread_like_activity_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, cycle `v3/thread_like_activity`; standing P2 enrollment; [ledger](../effort_estimation_trial.md#cycle-v3thread_like_activity).

## Problem and user stories

Later thread likes are saved but disappear from ordinary Activity when `like` was already present. Both normal updates and rebuilds currently confuse a new contribution with first label appearance.

- As a member, I want my accepted like to appear in Activity so that I can find the action I performed.
- As a reader, I want each distinct contribution correctly attributed so that Activity reflects who acted and when.
- As an operator, I want rebuilding to recover older missing likes without changing votes or scores.

## Core requirements

- **Coverage:** each distinct accepted, currently eligible thread-label record containing `like` produces one Activity item, regardless of an earlier like. Cover button-added and source-comment-generated likes. Separate accepted records remain separate actions even when their author is the same; existing scoring and write-eligibility deduplication still apply. Mixed-label records produce one item containing the like and any other labels already eligible under current rules.
- **Identity and evidence:** show that contribution's actor and original record/action time under existing timing conventions; preserve its own source record, signature status and accepting commit. A later like must not reattribute older entries or give them the later write's commit. Retries, recovery, repeated reads and replay of the same canonical record must not duplicate its Activity item.
- **Consistent recovery:** normal accepted writes update Activity; full rebuild/startup refresh recovers eligible historical contributions without another user action or new canonical like records. Counts, pagination, source/detail links and supported feeds agree with the listing. Accepted likes survive publication failure, with existing actionable retry/rebuild guidance rather than a false freshness claim.
- **Preserved behavior:** do not change scores, effective labels, vote eligibility, approval policy, reply-reaction behavior or other thread-label event semantics. Reuse existing hidden/retracted-target filtering and source-owned-label withdrawal policy. This slice does not turn Activity into a complete immutable repository history.

## Delivery scope and completion boundary

Application changes, focused regression/browser checks, affected Activity documentation, cycle plans and study-ledger bookkeeping. Reuse the Like action and Activity UI; no new navigation, feed summary format or reaction control. Completion means a second identity's accepted thread like is discoverable in normal Activity immediately after a healthy update and after rebuilding, with accurate evidence and unchanged scoring. Release requires incremental/rebuild parity, retry/replay checks, historical recovery and rendered phone/desktop verification.

The [repository coverage checklist](../backlog/activity_repository_coverage_checklist.md), withdrawal of button-added likes, and the separately identified duplicate guard that blocks liking again after retracting a `#like` source remain separate work. Their implementation is not authorized by this plan.

## Known local case and user flow

At `127.0.0.1:8000`, thread `thread-20261010002251-6cb36993` has an older like at `2026-10-10T00:22:57Z` and a later accepted like at `2026-10-23T02:23:22Z`, commit `78e1f3f`, currently absent from Activity. Reproduce this two-contribution relationship in a local ZenMemes fixture; inspect the real record read-only to confirm recovery expectations. Do not mutate the user's local content as a test fixture.

1. Another person has already liked the thread; the member clicks Like normally.
2. After acceptance, open All Activity and find the member's own action and evidence.
3. If an update fails, follow existing recovery guidance; do not submit another distinct vote to repair display.
4. Rebuild/refresh the derived model: both eligible likes remain, each once, with their original attribution.

## Shared components and risks

Extend the existing shared label-to-Activity derivation in full/incremental read-model paths; reuse the Activity service, existing source/signature metadata, classic Activity/RSS, Forte listing/detail/pagination APIs and counts. Standard/Forte Like controls, canonical records and the separate Commits view retain their existing contracts. No new component is needed.

- **Duplicate or missing events:** validate two identities, same-record replay and mixed-label records before Step 3; use stable action identity independently of score deduplication.
- **Incorrect historical attribution:** inspect incremental source-commit assignment before staging; compare older/newer record links, actor, time and signature evidence against a fresh rebuild.
- **Stale or newly exposed data:** validate startup freshness and existing hidden/retracted/withdrawn filters; retain current visibility policy and recovery rather than bypassing it to show more rows.

Success: the reported later-like pattern appears in normal and rebuilt Activity; one record yields one item; actors/times/evidence and listing totals agree; scores and unrelated reaction behavior remain unchanged. Verify phone/desktop classic/Forte views and existing detail/expanded/error states; record environment limitations separately from passes.

## Study checkpoint and approval

Step 1 Option A approved by the user. Scope v1 clarified; 2026-10-23 UTC; baseline `39be82932fcc36c5017fcf368655ef449bf042da`; Codex single exposed rater; R1/P2. Added exposure: incremental event identity, source-commit assignment and Activity consumer contracts. Numeric total projection remains unavailable pending exact frozen calibration report/diff recovery; preserve earlier unavailable checkpoints, not zero estimates. No measured effort, implementation or outcome is claimed. User approved Step 2 with “Approved Step 2”; Step 3 drafting authorized. Planning remains uncommitted; implementation awaits Step 3 approval.
