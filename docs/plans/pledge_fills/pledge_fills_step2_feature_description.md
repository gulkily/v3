> **Feature plan:** [Step 1](./pledge_fills_step1_solution_assessment.md) · [Step 2](./pledge_fills_step2_feature_description.md) · [Step 3](./pledge_fills_step3_development_plan.md) · [Step 4](./pledge_fills_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_fills_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_fills`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_withdraw`.

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-16 (Option B). Step 2 approved by the maintainer 2026-11-16. Approval is the maintainer's at every step.

**Cycle estimate: 0.50**

# Step 2: Feature Description — Pledge fills

Third cycle in [pledges_checklist.md](../pledges_checklist.md). Design: [pledges_spec.md](../pledges_spec.md). Builds on [pledge withdraw](../pledge_withdraw/pledge_withdraw_step4_implementation_summary.md) (PR #275, merged). This plan sits on `plan/pledge-fills`, level with `origin/main` at `66856253`.

## Problem

A campaign shows what was promised and nothing about what arrived. Nobody can tell a pledge that came through from one that did not, and the campaign can never be complete.

Approach (Step 1): Option B. A reply to a pledge with a `filled` line fills it. Confirm and dispute move to the next cycle, `pledge_fill_review`.

## User stories

- As a pledger, I want to mark my pledge delivered so that the campaign shows it arrived, even if the author is quiet.
- As a campaign author, I want to mark any pledge filled so that the thread records what I received.
- As a reader, I want each fill to say who reported it so that I can tell a self-report from the author's word.
- As an agent, or anyone typing by hand, I want to fill part of a pledge with one line (`filled: 200`) so that every client can do it.

## Core requirements

- With the `pledges` flag on, a reply to a counted pledge with the line `filled` fills what is left of it; `filled: 200` fills 200. The reply needs counted attribution and a name, as a pledge does.
- Three people may fill: the pledger (same key), the campaign author (the opening post's key) and an operator at the time the thread is shown. Anyone else's line shows "Not counted: only the pledger, the author or an operator can fill".
- The pledge shows each fill with who made it: "Delivered by mara" for the pledger, "Filled by ilyag" for the author or an operator. Each links to its reply.
- The totals block gains "N filled". When part of that is pledger-reported, it also shows "M confirmed", the share filled by the author or an operator.
- A "Mark delivered" button for the pledger and a "Mark filled" button for the author and operators post a bare `filled` reply. Partial fills are typed. Retracting a fill reply undoes it.

## Decisions on the Step 1 questions

1. **Withdrawing a part-filled pledge.** The spec's rule stands: only the unfilled part is withdrawn. A withdrawn pledge counts in "pledged" as what was filled on it, before or after the withdrawal. A fully filled pledge cannot be withdrawn; its withdraw line shows "Not counted: already filled".
2. **A fill larger than what is left.** It counts up to what is left and the fill shows "Counted 100 of 150". A fill on a pledge with nothing left shows "Not counted: already filled". Nothing is rejected.
3. **Folding.** A bare `filled` reply that took effect and has no answer leaves the conversation, as a bare `withdraw` does. One with a note, an answer or a reason stays.
4. **Words.** "Delivered" for the pledger and "Filled" for the author or an operator, as in the spec. The totals use "filled" for both.
5. **Released.** Left to `pledge_fill_review`, which decides it alongside Dispute.

## Smaller rules

- `filled` and `filled:` both read, any case, quoted lines skipped. A number may be followed by the campaign's unit. Other trailing text is "Not counted" with a reason.
- Fills add up across replies and people, in thread order, up to the pledge's amount.
- A fill on an uncounted pledge (unnamed, unverified, unreadable) or on a non-pledge shows "Not counted: not a reply to a counted pledge".
- "The pledger" and "the author" mean the same key, as for withdrawal. The same limit applies: a second key of the same username does not match.

## Delivery scope

Work type: application change.

Not in this cycle: confirm, dispute and Released (cycle `pledge_fill_review`); stages and Complete (cycle 4); board rows; the text output and agent API (cycle 6); buttons on the Reply page or in paned layouts.

## Completion boundary

- Entry: the "Mark delivered" or "Mark filled" button on a pledge on the campaign thread page, or a typed reply to the pledge.
- End-to-end outcome: mara presses "Mark delivered" on her 300 pledge. It shows "Delivered by mara". The block shows "300 filled · 0 confirmed". Ilya then types `filled: 100` on okafor's 400 pledge, and the block shows "400 filled · 100 confirmed". No new card appears for the bare reply.
- Recovery: a refused or offline press shows the failure on the pledge and can be pressed again. A mistaken fill is undone by retracting its reply. Turning the `pledges` flag off returns every line to plain text.
- Release: with the flag on at zenmemes.com, Ilya pledges from his iPhone SE, marks it delivered, sees the filled total change, and retracts the fill.

## Risks

- **Knowing who is an operator when the thread is shown.** Impact: a slow page, or an operator's fill not counted. Validate early: time `ApprovalPolicy::isActiveOperator()` for the distinct fill authors of a thread before Step 3. Mitigation: check each distinct author once per page.
- **Two buttons for different viewers on a page that is the same for all.** Impact: the wrong person sees a button. Validate early: confirm that "Withdraw pledge"'s viewer test extends to the author and to `profileIsActiveOperator()`. Mitigation: reuse that test; the read rule is the real guard.
- **Fills and withdrawal interact.** Impact: "pledged" or "filled" disagree with the pledges shown. Validate early: tests for fill then withdraw, withdraw then fill, and an over-fill. Mitigation: one reader computes both totals in one pass.

## Shared component inventory

- Totals reader (`src/ForumRewrite/View/PledgeCampaign.php`): extended with fills next to `withdrawals()`.
- Line readers (`src/ForumRewrite/Support/WithdrawDirective.php`, `PledgeDirective.php`): pattern reused for a `filled` reader; amounts through `PledgeAmount.php`.
- Amount on a reply (`templates/partials/pledge_amount.php`): extended with fill labels and the two buttons.
- Totals block (`templates/partials/pledge_totals.php`): extended with filled and confirmed.
- Fold (`src/ForumRewrite/ReadModel/WithdrawLeafFilter.php`): extended to bare fill replies, not copied.
- Button script (`public/assets/pledge_withdraw.js`): extended to post either line, not copied.
- Operator test (`RouteServices::profileIsActiveOperator()`, `ApprovalPolicy::isActiveOperator()`): used unchanged.
- Feature flag `pledges`: used unchanged. No new flag.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md), changed (fills leave "Not yet"); [thread activity](../../concepts/thread_activity.md), [post retraction](../../concepts/post_retraction.md) and [operator grant](../../concepts/operator_grant.md), used unchanged. No new entry. No matching item in `docs/feedback/items/`.

## Simple user flow

1. Mara opens the campaign and presses "Mark delivered" on her pledge of 300.
2. Her pledge shows "Delivered by mara"; the block shows "300 filled".
3. Ilya replies to okafor's pledge of 400 with `filled: 100`. It shows "Filled by ilyag · 100"; the block shows "400 filled · 100 confirmed".
4. Okafor withdraws. His pledge shows Withdrawn, and "pledged" keeps the 100 that arrived.

## Success criteria

- A pledger marks a pledge delivered from a phone in one press.
- After every fill, withdrawal and retraction, "filled" equals the counted fills, each capped at its pledge.
- Every fill names who made it, to every reader.
- A fill from anyone other than the pledger, author or an operator changes no total and says why.
- With the flag off, every thread page is unchanged.

## Enhancement requests

- **Request:** Released, an author's way to stop someone else's pledge counting (carried from cycle 2). **Disposition:** separate work, for `pledge_fill_review`. **Approval:** maintainer's direction at Step 1 approval. **Follow-up:** checklist cycle 8.

## Study snapshots

- Step 2 handoff: **0.50** reaffirmed. Same scope v1. Fold and button reuse offset the withdraw interaction and the over-fill rule. Baseline `66856253`, R1, P2. Claude single exposed rater, now on Claude Opus 5.5 (Step 1 was on Claude Fable 5.2), 2026-11-16 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
