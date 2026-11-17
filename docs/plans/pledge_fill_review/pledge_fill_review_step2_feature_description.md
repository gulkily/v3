> **Feature plan:** [Step 1](./pledge_fill_review_step1_solution_assessment.md) · [Step 2](./pledge_fill_review_step2_feature_description.md) · [Step 3](./pledge_fill_review_step3_development_plan.md) · [Step 4](./pledge_fill_review_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_fill_review_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_fill_review`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_fills`.

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-16 (Option A; Released left out). Step 2 approved by the maintainer 2026-11-17.

**Cycle estimate: 0.40**

# Step 2: Feature Description — Pledge fill review

Cycle 8 in [pledges_checklist.md](../pledges_checklist.md). Design: [pledges_spec.md](../pledges_spec.md). Builds on [pledge fills](../pledge_fills/pledge_fills_step4_implementation_summary.md) (PR #282, merged). Base: `origin/main` at `f13ed676`.

## Problem

A pledger's "Delivered" counts on their word alone. The author cannot confirm it or say it never arrived, short of an operator deleting the reply.

Approach (Step 1): Option A. A `confirm` or `dispute` line in a reply to a pledger's fill reply.

## User stories

- As a campaign author, I want to confirm a pledger's delivery so that the campaign shows I received it.
- As a campaign author, I want to dispute a delivery that never came so that it stops counting without anything being deleted.
- As a pledger, I want to see a dispute and answer it in the thread so that we can sort it out in public.
- As a reader, I want each fill to say whether the author confirmed or disputed it so that I can trust the filled total.

## Core requirements

- With the `pledges` flag on, a reply to a pledger's counted fill reply with the line `confirm` or `dispute` reviews that fill. The reply needs a name and counted attribution, as a fill does.
- Only the campaign author (same key) or an operator at the time the thread is shown may review. Anyone else's line shows "Not counted: only the author or an operator can review".
- A confirmed fill reads "Delivered by mara · confirmed by ilyag" and joins the confirmed share. A disputed fill reads "Delivered by mara · disputed by ilyag", struck through, and leaves the filled total; that part of the pledge is open again. Each review links to its reply.
- When a fill has several reviews, the latest counted one in thread order decides. Retracting a review reply undoes it.
- For the author or an operator, each unreviewed pledger fill on a pledge shows "Confirm" and "Dispute" buttons, which post the bare line as a reply to that fill.

## Decisions on the Step 1 questions

Approval of Step 1 left both open; the suggested answers are used here, open to overrule.

1. **Folding.** A bare `confirm` reply that counted and has no answer leaves the conversation. A `dispute` reply never folds, so the fill and the dispute stay in the thread where the pledger can answer.
2. **Fills by the author or an operator** cannot be reviewed. They are already confirmed; retracting one undoes it. A review line on one shows "Not counted: not a reply to a pledger's fill".

## Smaller rules

- `confirm`, `dispute` and their `:` forms read in any case, quoted lines skipped. Text after the line is "Not counted" with a reason, as for `withdraw`; a note goes on another line.
- Reviews are read before fills, so a disputed fill frees its room and a later fill on the same pledge counts against what is left.
- A fill that counted only in part ("Counted 300 of 500") is confirmed or disputed for the part that counted.
- A reviewer cannot review their own fill (an author who pledged to their own campaign).

## Delivery scope

Work type: application change.

Not in this cycle: Released (dropped 2026-11-16; not planned for the near future); review buttons on the Reply page or in paned layouts; the text output and agent API (cycle 6); stages (cycle 4).

## Completion boundary

- Entry: "Confirm" or "Dispute" under a pledger's fill on the campaign thread page, or a typed reply to the fill reply.
- End-to-end outcome: mara and okafor each mark their pledges delivered (300 and 400). Ilya presses Confirm on mara's: it reads "confirmed by ilyag" and the block shows "700 filled · 300 confirmed". He presses Dispute on okafor's: it is struck through with "disputed by ilyag", the block shows "300 filled", okafor's pledge shows "Mark delivered" again, and the dispute reply sits in the conversation.
- Recovery: a refused or offline press shows the failure under the fill and can be pressed again. A mistaken review is undone by retracting it or replaced by a later review. Turning the `pledges` flag off returns every line to plain text.
- Release: with the flag on at zenmemes.com, Ilya confirms and disputes a test fill from his iPhone SE, then retracts the dispute.

## Risks

- **Reading order.** Impact: a dispute does not free room, so a later fill shows "already filled". Validate early: tests for fill, dispute, fill again, and for confirm then dispute, before Step 3. Mitigation: read reviews first, in the one reader.
- **The fold and answered fills.** Impact: a review reply unfolds the bare fill it answers, leaving an empty-looking card. Validate early: a fold test where a bare confirm folds and the fill folds after it, and a dispute keeps both. Mitigation: the fold already repeats until stable.
- **Where the buttons go.** A bare fill reply is usually folded, so it has no card to put buttons on. Validate early: confirm that the pledge's fill line can carry a form that replies to the fill. Mitigation: draw them there, with the same viewer test as "Mark filled".

## Shared component inventory

- Totals reader (`src/ForumRewrite/View/PledgeCampaign.php`): extended with a review pass before `fills()`; reuses its roles, operator cache and "not counted" reasons.
- Line readers (`src/ForumRewrite/Support/WithdrawDirective.php`, `FilledDirective.php`): pattern reused for the two review lines.
- Amount on a reply (`templates/partials/pledge_amount.php`): each fill line gains its review label and the two buttons; review replies that do not count show their reason, as fill replies do.
- Totals block (`templates/partials/pledge_totals.php`): unchanged; it already shows filled and confirmed.
- Fold (`src/ForumRewrite/ReadModel/WithdrawLeafFilter.php`): extended to bare confirm replies, not copied.
- Button script (`public/assets/pledge_withdraw.js`): unchanged; review forms use its `pledge-line` role.
- Operator test (`ApprovalPolicy::isActiveOperator()`, the page's `viewerCanRetractOthers`): used unchanged.
- Feature flag `pledges`: unchanged. No new flag.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md), changed (confirm and dispute leave "Not yet"; Released removed from it); [post retraction](../../concepts/post_retraction.md) and [operator grant](../../concepts/operator_grant.md), used unchanged. No new entry. No matching item in `docs/feedback/items/`.

## Simple user flow

1. Mara presses "Mark delivered" on her pledge of 300.
2. Ilya, the author, opens the thread and sees "Delivered by mara" with Confirm and Dispute.
3. He presses Confirm. The line reads "Delivered by mara · confirmed by ilyag".
4. On okafor's "Delivered", he presses Dispute. It is struck through, the filled total drops, and the dispute appears in the replies.
5. Okafor answers the dispute in the thread, and later marks it delivered again.

## Success criteria

- An author confirms or disputes a delivery from a phone in one press.
- After every fill, review and retraction, "filled" equals the counted, undisputed fills, and "confirmed" equals the author and operator fills plus the confirmed pledger fills.
- Every disputed fill and its dispute stay readable in the thread.
- A review from anyone other than the author or an operator changes no total and says why.
- With the flag off, every thread page is unchanged.

## Enhancement requests

- **Request:** Released (open in the checklist since cycle 2). **Disposition:** dropped. **Approval:** maintainer at Step 1 approval ("I don't think we will need it in the near future"). **Follow-up:** none.

## Study snapshots

- Step 2 handoff: **0.40** reaffirmed. Same scope v1, Released out. The buttons on a folded fill and the fold rule balance the unchanged totals block and button script. Baseline `f13ed676`, R1, P2. Claude single exposed rater (claude-opus-5-5), 2026-11-16 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
