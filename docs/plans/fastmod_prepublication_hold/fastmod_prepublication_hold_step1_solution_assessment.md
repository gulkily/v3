> **Feature plan:** [Step 1](./fastmod_prepublication_hold_step1_solution_assessment.md) · [Step 2](./fastmod_prepublication_hold_step2_feature_description.md) · [Step 3](./fastmod_prepublication_hold_step3_development_plan.md) · [Step 4](./fastmod_prepublication_hold_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Please take a look at these partially completed todo items, ask me any questions as needed, and write step 1 of docs/railspike/RAILSPIKE.md.

```text
xmake another llm/agentic feature for Fastmod using lesser models
x sending only short instructions and the text of the post
x   for replies, include some context
x request only a 0-1 probability in response
  later, as a separate requirement: use validated Fastmod probabilities to
  hold qualifying content for human moderation before it is public
    requires a threshold, calibration, audit trail, override, and rollback
```

## Understood Intent

Assess the remaining prepublication moderation requirement; the marked scoring work is already delivered. Existing [Fastmod](../../reference/fast_post_scoring.md) scores published content asynchronously and remains advisory; its historical audit is not calibration against human judgments.

## Revised Direction

New posts by unapproved users are initially held privately, replacing the earlier public scoring period. Approved users bypass holds, and any approved user can approve an individual held post without approving its author. Preserve the earlier requirement that a history trigger can also hold earlier submissions; define how that interacts with explicit human approvals. Automatic release is permitted when validated content/history criteria are met. Interpret “no time bound” as no age cutoff on earlier submissions covered by a trigger and no automatic release merely because a hold has lasted a certain time.

## Problem

Privately receive and score unapproved users' submissions, support approved-user review, and use validated Fastmod history to govern automatic release and later holds without delaying approved authors.

## Option A — Initial hold with human release

Hold each new submission from an unapproved user, score it asynchronously, and let any approved user approve it for publication. Use early scores and history to inform review and any defined retroactive-hold policy; scores alone do not release content.

- Pros: Directly satisfies initial holds and approved-user review; no provider wait in submission requests; clear release authority.
- Cons: Every submission from an unapproved user needs review; score history no longer determines whether new content starts held.

## Option B — Initial hold with conditional automatic release

Hold and score new submissions from unapproved users privately, then release only those meeting validated content/history criteria; retain the rest for approval by any approved user.

- Pros: All submissions start private; can reduce review burden while using early scores to influence later treatment.
- Cons: Automatic release requires calibration and clear history rules; provider failures and stale scores need explicit recovery.

## Recommendation and slice viability

Recommend **B**, reflecting authorization for automatic release, with human approval available to any approved user. The initial hold is unconditional for unapproved authors; a score threshold cannot decide whether that initial hold exists. Validate Fastmod against representative human judgments before scores drive automated release or retroactive decisions; numeric validity alone is not calibration.

- Bound the first slice to new local text threads and replies through normal and signed submission paths; apply retroactive triggers to earlier submissions without an age cutoff; define imported-content coverage. Defer score backfill, edits, and attachment analysis.
- Complete the journey: private receipt and author status → asynchronous private scoring → automatic release when eligible or continued hold → per-post approval by any approved user → publication, with audited override and recovery. Existing scoring consumes published content, so private scoring requires extending that contract.
- Define history aggregation, trigger and release thresholds, missing/stale-score handling, rejection authority, and whether retroactive triggers may override explicit per-post approvals. A hold has no time-based release; a provider failure or elapsed time is not evidence of safe content. Do not infer permission to reject from permission to approve.
- Keep held content and attachments out of public pages, APIs, exports, snapshots, and canonical distribution. Define treatment of replies to held roots without imposing Fastmod holds on approved authors. Retroactive holds require visibility updates; previously downloaded or remote copies cannot be recalled.
- Audit content identity, score, model/rubric/policy versions, hold/release decisions, reviewer, and reason. Account approval bypasses new holds; define disposition of existing holds separately from individual post approval. Provider failure must not silently publish initially held content.
- Rollback stops new enforcement with an explicit intake policy and preserves pending items for deliberate disposition; disabling scoring must not silently release the backlog.
- If this exceeds one day/eight stages, split into separately approved vertical slices, retaining automatic release in the planned outcome rather than substituting a manual-only result. Do not omit review or privacy guarantees to fit the limit.

## Decisions needed before Step 2

- Choose calibrated content/history trigger and release criteria; define imported-content coverage, rejection authority, and whether explicit human post approvals override later automatic holds. The no-age-cutoff retroactive scope and absence of timeout release are recorded interpretations of “no time bound.”
