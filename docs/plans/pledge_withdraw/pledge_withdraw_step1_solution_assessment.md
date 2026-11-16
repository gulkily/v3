> **Feature plan:** [Step 1](./pledge_withdraw_step1_solution_assessment.md) · [Step 2](./pledge_withdraw_step2_feature_description.md) · [Step 3](./pledge_withdraw_step3_development_plan.md) · [Step 4](./pledge_withdraw_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_withdraw_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_withdraw`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_campaigns`.

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-16 (Option A). Approval is the maintainer's; the one-time delegation in cycle 1 does not carry over.

**Cycle estimate: 0.35**

# Step 1: Solution Assessment — Pledge withdraw

Second of seven cycles in [pledges_checklist.md](../pledges_checklist.md). Design: [pledges_spec.md](../pledges_spec.md). Builds on cycle 1, [pledge campaigns](../pledge_campaigns/pledge_campaigns_step4_implementation_summary.md) (PR #268, not merged when this was written).

## Original Query

Amazing work, Claude. What is the next cycle, and can you please write step 1 for it? And commit and push to step 1 and give me a prompt for a new chat. And be mindful of the checklist, please.

## Understood Intent

The next cycle in the checklist is `pledge_withdraw`: "a pledger withdraws a pledge; it stays visible, marked withdrawn, and stops counting." The checklist leaves the mechanism open, because plain retraction deletes the pledge's text. This step compares mechanisms.

The spec's decided list says "Withdrawal reuses the existing retraction method." That line was written before the code was read. Options C and D keep it; Options A and B depart from it and say why.

## Problem

A pledger cannot take a pledge back in a way that leaves it visible: retracting the reply removes its text and amount, and nothing else marks a pledge as withdrawn.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md), changed in every option. [Post retraction](../../concepts/post_retraction.md): used unchanged in A and B, changed in C, relabelled in D. [Tagset](../../concepts/tagset.md) and [reaction record](../../concepts/reaction_record.md): changed in B only. [Shared composer](../../concepts/shared_composer.md): used unchanged.

No matching item exists in `docs/feedback/items/`.

## Option A — A `withdraw` line in a reply to the pledge

**Estimate: 0.35**

The pledger replies to their own pledge with the line `withdraw`. The thread page reads it as it reads `pledge:` lines. The pledge keeps its text, shows "Withdrawn" with its amount struck through, and leaves the sum. A "Withdraw pledge" button on the pledger's own pledge posts that reply.

- Same mechanism as cycle 1: nothing stored, no schema change, works from any client and without JavaScript.
- `PledgeCampaign::fromPosts()` already has each reply's parent and author, so the rule "same author as the pledge" needs no new query.
- Undoing a withdrawal is retracting the withdraw reply, which is the existing retraction method.
- A reply line can carry a number. Cycle 3 needs that for partial fills (`filled: 200`), so both cycles would share one mechanism.
- Each withdrawal adds a reply to the thread. Step 2 must decide whether it shows in the conversation or folds into the pledge.
- A pledge with no name cannot be withdrawn this way. It is not counted either.
- Estimate details: driver: the button for the pledger only, on pages that are the same for every reader; uncertainty: how the withdraw reply is shown.

## Option B — A "Withdrawn" state set by a reaction

**Estimate: 0.50**

"Withdrawn" becomes a reply state like Resolved and Closed. The pledger taps Withdraw beside Like and Flag, which writes a signed post reaction. The pledge shows the state and leaves the sum.

- One tap, no extra reply in the thread, and the state already reaches the thread rows.
- Reply states are unlocked by a thread tag and set by any approved key. Withdrawal needs two new rules: unlocked by a goal line, and set only by the pledge's author.
- It depends on a second flag, `FORUM_TAG_UNLOCKS_ENABLED`.
- A reaction carries a tag and no number, so cycle 3's partial fills would need another mechanism.
- Undoing needs a second tag, as Reopen does.
- Estimate details: driver: new rules in the tagset write check; uncertainty: reactions from unapproved pledgers, which are stored and not counted today.

## Option C — A withdrawal record in the retraction family

**Estimate: 0.85**

A new signed record names the pledge with `Target-ID`, as a retraction does, but marks the post instead of deleting it. The read model stores the mark.

- Closest to the spec's wording: the same request path, signature check and receipt as retraction.
- Adds a record type, changes the signed request validator, and needs a read-model column and a rebuild.
- The retraction handoff of 2026-10-24 asks for retraction to get simpler. This adds a second meaning to it.
- Estimate details: driver: record format and schema; uncertainty: rebuild cost and import of the new record.

## Option D — Plain retraction, labelled "Withdraw pledge"

**Estimate: 0.10**

The pledger's own pledge gets a "Withdraw pledge" button that retracts the reply. This already stops the pledge counting today.

- Almost no work, and it is the spec's sentence taken literally.
- The pledge's text and amount are deleted. The thread shows a deletion notice, not a withdrawn pledge. It does not meet "stays visible".
- Readers cannot see how much was pledged and taken back, which later stages and markers refer to.
- Estimate details: driver: one button; uncertainty: none.

## Recommendation

Option A. It is a complete slice: withdraw from the page, see the pledge marked and the sum drop, undo by retracting the reply. It needs no schema change or second flag, and it gives cycle 3 a way to carry amounts. Plain retraction (Option D's effect) keeps working beside it for anyone who wants a pledge gone entirely.

Questions for Step 2:
- Does the withdraw reply show in the conversation, or fold into the pledge it withdraws?
- May the campaign author or an operator withdraw someone else's pledge, or only retract it?
- The spec says withdrawing a filled pledge withdraws only the unfilled part. Fills arrive in cycle 3; this cycle should only avoid blocking that rule.
- Should the checklist's decided line "Withdrawal reuses the existing retraction method" be reworded once an option is chosen?

Enhancement requests: none in this request.

## Study snapshots

- Intake (after cycle 1's code and a read of the retraction, tagset and reaction concepts; no pristine intake): 0.35 projected total for Option A. Baseline `c1572e57` (`feat/pledge-campaigns`, which contains cycle 1; `origin/main` was `9e5aa010`), scope v1, R1, P2; Claude single exposed rater, 2026-11-16 UTC.
- Step 1 handoff: 0.35 reaffirmed. Option estimates A 0.35, B 0.50, C 0.85, D 0.10, not summed. R1 hashes not re-verified in this session.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
