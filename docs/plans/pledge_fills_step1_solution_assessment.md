> **Feature plan:** [Step 1](./pledge_fills_step1_solution_assessment.md) · [Step 2](./pledge_fills_step2_feature_description.md) · [Step 3](./pledge_fills_step3_development_plan.md) · [Step 4](./pledge_fills_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_fills_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_fills`; [host ledger](./effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_withdraw`.

Extended estimates: enabled

Status: Step 1 written 2026-11-16, awaiting `Approved Step 1`. Approval is the maintainer's at every step, as in cycle 2.

**Cycle estimate: 0.50**

# Step 1: Solution Assessment — Pledge fills

Third of seven cycles in [pledges_checklist.md](./pledges_checklist.md). Design: [pledges_spec.md](./pledges_spec.md). Builds on cycle 2, [pledge withdraw](./pledge_withdraw/pledge_withdraw_step4_implementation_summary.md) ([PR #275](https://github.com/gulkily/v3/pull/275), merged 2026-11-16). This plan sits on `plan/pledge-fills`, cut from `origin/main` at `66856253`.

## Original Query

Great work, please write step 1, commit and push it, and write a prompt block for the next chat.

## Understood Intent

The next cycle in the checklist is `pledge_fills`: "the pledger marks a pledge delivered; the author confirms or disputes; the author and operators mark fills directly; partial fills add up. Totals gain filled and confirmed." The checklist leaves the mechanism open and adds two questions from cycle 2: how withdrawing a part-filled pledge works, and whether an author needs a way to stop someone else's pledge counting.

That is four record kinds (fill, partial fill, confirm, dispute) and three kinds of people. This step compares mechanisms and how much to build at once.

## Problem

A campaign shows what was promised and nothing about what arrived, so nobody can tell a pledge that came through from one that did not, and the campaign can never be complete.

Concepts touched: [pledge campaign](../concepts/pledge_campaign.md), changed in every option. [Thread activity](../concepts/thread_activity.md) and [post retraction](../concepts/post_retraction.md): used unchanged in A, B and D. [Tagset](../concepts/tagset.md) and [reaction record](../concepts/reaction_record.md): changed in C only. [Operator grant](../concepts/operator_grant.md): read in every option, to know who is an operator.

No matching item exists in `docs/feedback/items/`.

## Option A — Reply lines for everything in the checklist item

**Estimate: 0.85**

A `filled` line in a reply to a pledge fills it; `filled: 200` fills part. The pledger's fill reads "Delivered (reported by mara)"; the author's or an operator's reads "Filled (confirmed by ilyag)". The author answers a pledger's fill with `confirm` or `dispute`. The totals block gains filled and confirmed. Buttons post each line, as "Withdraw pledge" does.

- One mechanism for the whole spec, the one cycles 1 and 2 use: nothing stored, no schema change, works from any client.
- Carries amounts, so partial fills add up.
- Largest of the four: three new lines, a second level of reply (a dispute answers a fill, which answers a pledge), four buttons with three different viewer rules, and two new totals.
- Much to review in one pull request, and the dispute rules are the least settled part of the design.
- Estimate details: driver: confirm and dispute on a reply to a reply; uncertainty: knowing who is an operator when the thread is read.

## Option B — Reply lines, fills now; dispute in its own cycle

**Estimate: 0.50**

Build the `filled` line only. The pledger fills their own pledge; the author and operators fill any pledge; a number makes it partial and fills add up to the pledge's amount. The label says who reported it. The totals block gains "filled", and the share filled by the author or an operator is the confirmed share. `confirm` and `dispute` move to a new cycle straight after this one.

- A complete slice: mara presses "Mark delivered", her pledge shows "Delivered (reported by mara)", and the block shows what has arrived.
- An author confirms a delivery in this cycle by marking the same pledge filled themselves. Only dispute waits.
- Until dispute exists, a false "delivered" is answered the way any bad reply is: an operator retracts it. The label already says the claim is the pledger's own.
- Cycle 4 (stages, Complete) needs the filled total and can follow this cycle without waiting for dispute.
- Adds an eighth cycle to the checklist.
- Estimate details: driver: two viewer rules for the buttons (pledger, and author or operator); uncertainty: the same operator question as A.

## Option C — Delivered, Confirmed and Disputed as reply states

**Estimate: 0.70**

The three become exclusive reply states set by a reaction, like Resolved and Closed. A tap beside Like and Flag sets the state on the pledge.

- One tap and no extra replies in the thread.
- A reaction carries no number, so partial fills cannot add up. That is a decided rule in the spec.
- Reply states are set by any approved key. This needs new rules for the pledger, the author and operators, and the pledger may not be approved.
- Depends on a second flag, `FORUM_TAG_UNLOCKS_ENABLED`, and gives pledges two mechanisms: lines for pledge and withdraw, reactions for fills.
- Estimate details: driver: new who-may-set rules in the tagset write check; uncertainty: unapproved pledgers.

## Option D — Only the author and operators mark fills

**Estimate: 0.35**

Build the `filled` line for the author and operators only. The pledger cannot report a delivery, so there is nothing to confirm or dispute.

- Smallest. One button rule, one label, one new total.
- Departs from the decided design: pledgers mark their own pledges delivered, so that an author who goes quiet cannot freeze a campaign.
- Pledger self-report would be added later on top of it, which is Option B in two steps.
- Estimate details: driver: the operator question; uncertainty: little.

## Recommendation

Option B. It is the same mechanism as withdraw, so the reader, the reasons for lines that do not count, the fold and the button pattern are reused. It delivers a result a campaign can use, and it leaves the least settled part, dispute, to a cycle of its own where it can be discussed without holding up stages.

If approved, the checklist gains a cycle `pledge_fill_review` (confirm and dispute) after this one, and cycle 4 then needs only cycle 3.

Questions for Step 2:
- Withdrawing a part-filled pledge. The spec says only the unfilled part is withdrawn. The simpler rule is that a pledge with any fill cannot be withdrawn. Which one?
- A fill larger than what is left of the pledge: counted up to the pledge's amount, or not counted with a reason?
- Does a bare `filled` reply fold into the pledge, as a bare `withdraw` does?
- The words: "Delivered" for the pledger and "Filled" for the author, as in the spec, or one word for both?
- "Released", an author's way to stop someone else's pledge counting: suggested answer is to leave it for the dispute cycle.

The checklist's own question, who may mark when the pledger's key is not approved, is answered by Options A, B and D: a reply line needs no approval, only counted attribution, as a pledge does.

Enhancement requests: none in this request.

## Study snapshots

- Intake (after cycle 2's code and a read of the spec's fill rules and the tagset state code; no pristine intake): 0.50 projected total for Option B. Baseline `66856253` (`origin/main`, which contains cycles 1 and 2), scope v1, R1, P2; Claude single exposed rater, 2026-11-16 UTC.
- Step 1 handoff: 0.50 reaffirmed. Option estimates A 0.85, B 0.50, C 0.70, D 0.35, not summed. R1 hashes not re-verified in this session.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
