> **Feature plan:** [Step 1](./pledge_fill_review_step1_solution_assessment.md) · [Step 2](./pledge_fill_review_step2_feature_description.md) · [Step 3](./pledge_fill_review_step3_development_plan.md) · [Step 4](./pledge_fill_review_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_fill_review_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_fill_review`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_fills`.

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-16 (Option A; Released left out: "I don't think we will need it in the near future"). The maintainer approves every step in this cycle.

**Cycle estimate: 0.40**

# Step 1: Solution Assessment — Pledge fill review

Cycle 8 in [pledges_checklist.md](../pledges_checklist.md). It runs straight after cycle 3, ahead of cycle 4. Design: [pledges_spec.md](../pledges_spec.md). Builds on cycle 3, [pledge fills](../pledge_fills/pledge_fills_step4_implementation_summary.md) ([PR #282](https://github.com/gulkily/v3/pull/282), merged). Base: `origin/main` at `f13ed676`. The old branch `plan/pledge-fills` is stale and is not used.

## Original Query

We're building pledge campaigns on v3 (github.com/gulkily/v3).

State:
- Cycle 1 (pledge_campaigns, PR #268), cycle 2 (pledge_withdraw, PR #275) and cycle 3 (pledge_fills, PR #282) are merged.
- Next is cycle 8, pledge_fill_review. It runs straight after cycle 3, ahead of cycle 4. It is numbered 8 so the other cycle numbers stay stable.
- The old branch plan/pledge-fills is stale; ignore it.

Please clone the repo, fetch a fresh origin/main, and read, in this order:
1. docs/plans/pledges_checklist.md
2. docs/plans/pledges_spec.md
3. docs/plans/pledge_fills/pledge_fills_step4_implementation_summary.md
4. docs/concepts/pledge_campaign.md

Start a staged Railspike cycle called pledge_fill_review. Follow AGENTS.md and docs/railspike/RAILSPIKE.md, with debug mode on (as in the pledge_fills cycle). Enroll it in the railspike-effort study as AGENTS.md says, with parent v3/pledge_fills.

Scope from the checklist: the campaign author (and operators) confirm or dispute a pledger's "Delivered". A disputed fill stops counting, and both the fill and the dispute stay visible. The checklist also leaves open "Released": a way for the author or an operator to stop someone else's pledge counting without deleting it. Step 1 should say whether Released belongs in this cycle or later.

Notes from cycle 3: PledgeCampaign::fills() already gives each fill a role (pledger, author or operator), and "confirmed" is the part filled by the author or an operator. The fold (WithdrawLeafFilter) and the button script (pledge_withdraw.js) take a new line by adding to their maps and data roles. Cycle 2's "Withdrawn" reply state is set by a signed reaction, like Resolved and Closed; weigh that model against a reply line for confirm and dispute.

Write Step 1 and share it as an attached file in the chat. Keep it simple: a rule that belongs to a later cycle gets one sentence, not groundwork. I approve each step myself in this cycle. Keep pledges_checklist.md updated as cycles progress.

## Understood Intent

A correction to the last note: cycle 2's "Withdrawn" is a `withdraw` reply line, not a reaction (`PledgeCampaign::withdrawals()`). Resolved, Closed and Claimed are the reaction-set states (`resources/tagsets.txt`, `PostResolution`). So this step compares the two mechanisms as they exist: a reply line, which every pledge record uses so far, and a reaction-set state.

## Problem

A pledger's "Delivered" counts on their word alone. The author cannot say it is true (filling the same pledge again only reads "already filled"), and cannot say it is false without an operator deleting the reply.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md), changed in every option. [Post retraction](../../concepts/post_retraction.md): used unchanged, as undo, in A, C and D. [Operator grant](../../concepts/operator_grant.md): read in every option. [Tagset](../../concepts/tagset.md) and [reaction record](../../concepts/reaction_record.md): changed in B only.

No matching item in `docs/feedback/items/`.

## Option A — `confirm` and `dispute` reply lines

**Estimate: 0.40**

The author or an operator replies to a pledger's fill reply with `confirm` or `dispute`, or presses a button that posts that line. A confirmed fill moves into the confirmed share and reads "Delivered by mara · confirmed by ilyag". A disputed fill leaves the filled total, reads "Delivered by mara · disputed by ilyag", and its amount is open again. Both replies link from the fill and stay in the thread. Retracting the confirm or dispute reply undoes it.

- The same mechanism as pledge, withdraw and filled: read when the thread is shown, nothing stored, no schema change, works with no JavaScript and from agents.
- Reuses the reader's roles and "not counted" reasons, the fold, and the button script.
- A reply to a reply to a pledge: the reader matches a review to its fill, one level deeper than before.
- Changing a confirm to a dispute means a second reply; the later one decides.
- Estimate details: driver: a disputed fill must leave the "what is left" sum before later fills on the same pledge are capped; uncertainty: how much the fold changes for a reply that answers a fill.

## Option B — Confirmed and Disputed as reaction-set states

**Estimate: 0.70**

Confirmed and Disputed become a new exclusive reaction set on fill replies, like Resolved and Closed. A tap beside Like sets the state; the latest counted mark wins.

- One tap, no extra replies, and changing your mind is another tap.
- The state is a reaction record, so the reader needs each thread's reactions as well as its posts, and the totals stop being a reading of the posts alone.
- Reaction sets count only approved keys, and nothing limits who sets them. This needs a new rule (campaign author or operator only) in the write check.
- A tagset hangs off a thread tag and needs `FORUM_TAG_UNLOCKS_ENABLED`. Pledges would then use two mechanisms, and cycle 6's text output would have to report both.
- Estimate details: driver: the new rule for who may mark, in the reaction write path; uncertainty: loading reactions on every campaign page.

## Option C — `dispute` line only

**Estimate: 0.30**

Build only the `dispute` line from Option A. A self-reported fill stays "Delivered by mara" until disputed. Confirmed stays as it is now: the part filled directly by the author or an operator.

- Smallest. It removes the real gap: a false "Delivered" can be answered without deleting anything.
- An author cannot turn a pledger's "Delivered" into confirmed, so "Complete: 0 of 1000 confirmed" in cycle 4 could stay that way even when every fill is true.
- Confirm would follow later as a second line on the same reader, which is Option A in two cycles.
- Estimate details: driver: as in A; uncertainty: little.

## Option D — Option A plus Released

**Estimate: 0.55**

Option A, plus a `release` reply line to a pledge from the author or an operator. A released pledge stays visible, reads "Released by ilyag", and stops counting, like a withdrawal made by someone else.

- Settles the checklist's open item in one cycle and gives authors a tool against joke pledges ("pledge: 1000000") before cycle 4 starts celebrating totals.
- A second line with a different target (the pledge, not a fill) and its own rules for part-filled pledges, so the most review.
- The spec does not describe Released yet, so this cycle would also settle its design.
- Estimate details: driver: Released on a part-filled pledge; uncertainty: the spec gap.

## Recommendation

Option A. It finishes the spec's fill design with the mechanism every pledge record already uses, and keeps the totals a plain reading of the posts. It is a complete slice: mara marks delivered, ilyag confirms or disputes, and the block shows what is filled and what is confirmed.

Released: later, not in this cycle. Operator retraction already removes a joke pledge, so Released is a small follow-up (a `release` line built like `withdraw`) to add before cycle 4 only if that proves too blunt.

Questions for Step 2:
- Does a bare `confirm` reply fold into the pledge, as a bare `filled` does? Suggested: yes for confirm, no for dispute, so the dispute stays in the conversation where the pledger can answer it.
- Can a fill made by the author or an operator be disputed? Suggested: no. It is already confirmed, and retracting it undoes it.

Enhancement requests: "Let me know if we're open to one shot for any of these" (asked in chat about the remaining cycles). Answered in chat; no change to this cycle.

## Study snapshots

- Intake (after reading cycle 3's reader, fold and button script, and the reaction-set code; no pristine intake): 0.40 projected total for Option A. Baseline `f13ed676` (`origin/main`, which contains cycles 1 to 3), scope v1, R1, P2; Claude single exposed rater (claude-opus-5-5), 2026-11-16 UTC.
- Step 1 handoff: 0.40 reaffirmed. Option estimates A 0.40, B 0.70, C 0.30, D 0.55, not summed. R1 hashes not re-verified in this session.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
