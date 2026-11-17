> **Feature plan:** [Step 1](./pledge_withdraw_step1_solution_assessment.md) · [Step 2](./pledge_withdraw_step2_feature_description.md) · [Step 3](./pledge_withdraw_step3_development_plan.md) · [Step 4](./pledge_withdraw_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_withdraw_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_withdraw`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_campaigns`.

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-16 (Option A). Step 2 approved by the maintainer 2026-11-16, after one revision (decision 3). Approval is the maintainer's at every step of this cycle.

**Cycle estimate: 0.40**

# Step 2: Feature Description — Pledge withdraw

Second of seven cycles in [pledges_checklist.md](../pledges_checklist.md). Design: [pledges_spec.md](../pledges_spec.md). Builds on cycle 1 ([PR #268](https://github.com/gulkily/v3/pull/268), open and not merged on 2026-11-16). This plan sits on `plan/pledge-withdraw`, which contains `feat/pledge-campaigns` and is level with `origin/main`.

## Problem

A pledger cannot take a pledge back and leave it visible. Retracting the reply deletes its text and amount, and nothing else marks a pledge as withdrawn.

Approach (Step 1): Option A. The pledger replies to their own pledge with a `withdraw` line. The thread page reads it each time it is shown. Nothing new is stored.

## User stories

- As a pledger, I want to withdraw my pledge from the thread page so that the author stops planning on it.
- As a campaign author, I want a withdrawn pledge to leave the sum and stay visible so that I can see what changed and who changed it.
- As a reader, I want withdrawals to stay out of the conversation so that the thread reads as discussion and pledges.
- As an agent, or anyone typing by hand, I want to withdraw by replying with one line so that every client can do it.
- As a pledger who withdrew by mistake, I want to undo it so that my pledge counts again without a second pledge.

## Core requirements

- With the `pledges` flag on, a reply to a pledge whose body has the line `withdraw` withdraws that pledge when the reply and the pledge have the same author. The reply must have counted attribution, as a pledge must.
- A withdrawn pledge keeps its text. Its amount is struck through and labelled "Withdrawn" with the date. It leaves the sum and the pledge count. Every reader sees the same thing.
- A "Withdraw pledge" button appears on a counted pledge for its own pledger only. One press posts the withdraw reply. Typing the line into an ordinary reply to the pledge does the same.
- Retracting the withdraw reply undoes the withdrawal, and the pledge counts again. This is the existing retraction; no new undo control is added.
- Nothing is rejected. A `withdraw` line that does not withdraw anything stays as an ordinary reply and shows a short reason. With the flag off, the line is plain text.

## Decisions on the Step 1 questions

**1. Does the withdraw reply show in the conversation? It folds into the pledge, unless it says something.**

- A withdraw reply whose only text is the `withdraw` line leaves the conversation. The pledge's "Withdrawn" label links to the reply's own page, where its source, signature and retraction control are.
- A withdraw reply with a note ("order fell through, sorry") stays in the conversation as a reply under the pledge. The pledge is marked the same way.
- A bare withdraw reply that someone answered also stays, so that answer keeps its context.
- Reason: this is the rule the thread activity drawer already uses for hashtag-only replies. The conversation keeps replies with something to say. It also keeps a "Withdraw pledge" press from adding a one-word card to the thread.
- The fold does not depend on the activity drawer's flag, and withdrawals are not listed in the drawer. The pledge card is where a reader looks for a pledge's state.
- Only the thread page folds. The reply's own page, the paned layouts and the text output show it as an ordinary reply, as they show `pledge:` lines today.

**2. May the campaign author or an operator withdraw someone else's pledge? No. Only the pledger withdraws.**

- "Withdrawn" is the pledger's statement: I take this back. A withdraw line from anyone else would put that statement in the pledger's name. It stays an ordinary reply and shows "Not counted: only the pledger can withdraw".
- Operators keep what they have today: they can retract any pledge, which removes it entirely. The campaign author has no power over another person's pledge in this cycle.
- The author's real case is a pledge that will not arrive. The spec answers that by oversubscribing, and cycle 3 gives the author Dispute. If that proves too little, an author's mark needs its own word ("Released by ilyag"), not the pledger's. Not in this cycle; recorded under Enhancement requests.

**3. Part-filled pledges.** Nothing is built for them here. Fills arrive in cycle 3, which decides how withdrawing a part-filled pledge works. This cycle withdraws a whole pledge.

**4. Rewording the decided line.** Done with this step: the spec's "Withdrawal reuses the existing retraction method" now says withdrawal is a `withdraw` reply line and that retraction undoes it. The checklist records the change.

## Smaller rules

- `withdraw` and `withdraw:` both count; case does not matter; quoted lines are skipped, as for `pledge:`.
- The line takes no number. A line with anything after it (`withdraw: 100`) is not counted and shows a reason. To change a pledge, withdraw it and pledge again.
- A second withdrawal of the same pledge changes nothing and folds like the first.
- A `withdraw` line in a reply to something that is not a counted pledge is an ordinary reply with "Not counted: not a reply to a pledge".
- A retracted pledge is gone, as today; a withdrawal of it has no effect.
- An unnamed or unverified pledge is already out of the sum. It gets no button.

## Delivery scope

Work type: application change.

Not in this cycle: fills and part-filled pledges, stage changes when the sum drops under the goal, marker posts, board rows, the agent API's own report of pledge state, a withdraw control on the Reply page or in paned layouts, and any way for an author or operator to mark someone else's pledge.

## Completion boundary

- Entry: the "Withdraw pledge" button on the pledger's own pledge on the campaign thread page; or a reply to the pledge with the line typed.
- End-to-end outcome: mara presses the button on her 300 pledge. The pledge shows 300 struck through and "Withdrawn", the sum drops by 300, the count drops by one, and no new card appears in the conversation.
- Recovery: a refused or offline withdrawal reports the failure on the pledge and can be pressed again; the pledge stays counted until the reply exists. A mistaken withdrawal is undone by retracting the withdraw reply from its page. An operator turns the `pledges` flag off to return every line to plain text.
- Release: with the flag on at zenmemes.com, Ilya pledges on a campaign from his iPhone SE, withdraws it, sees the sum change, then undoes it and sees the sum return.

## Risks

- **The button must show for one reader on a page that is the same for all.** Impact: the wrong person sees it, or it appears after paint. Validate early: read how the Retract control decides this on thread pages, including static and high-traffic pages, before Step 3 is written. Mitigation: follow that control exactly; the server refuses nothing, so the read rule (same author) is the real guard.
- **Folding hides a reply that still matters.** Impact: a note or an answer disappears from the conversation. Validate early: tests for bare, noted and answered withdraw replies. Mitigation: only a body that is the line alone folds, and an answered one never does.
- **Undo leaves a deletion notice.** Impact: retracting a withdraw reply may show a "retracted" card under the pledge. Validate early: retract one on a test thread and look. Mitigation: `RetractedLeafFilter` already drops a retracted reply that nobody answered; Step 3 confirms it runs on this page.
- **One person, several keys.** Impact: a pledger who pledged with one key and withdraws with another may not match as the same author. Validate early: check what the thread rows carry as the author for two keys under one username. Mitigation: match on whatever "same author" already means for Retract, and state the limit if it is per key.

## Shared component inventory

- Totals reader (`src/ForumRewrite/View/PledgeCampaign.php`, `fromPosts()`): extended. It already has each reply's parent, author and attribution, and is the one place that decides what counts.
- Line readers (`src/ForumRewrite/Support/PledgeDirective.php` and its siblings): pattern reused for one new reader.
- Amount on a reply (`templates/partials/pledge_amount.php`): extended with the Withdrawn state and its link. No second badge component.
- Totals block (`templates/partials/pledge_totals.php`): used unchanged; it shows the new sum and count.
- Leaf filters (`src/ForumRewrite/ReadModel/TagOnlyLeafFilter.php`, `RetractedLeafFilter`): pattern reused for folding a bare withdraw reply, including "an answered reply stays".
- Retract control (`templates/partials/retract_post_control.php`, `public/assets/post_retraction.js`): its own-post rule and failure feedback are the model for the button; retraction itself is used unchanged for undo.
- Reply sending ([shared composer](../../concepts/shared_composer.md), signed and Outbox paths): used unchanged. The button posts an ordinary reply.
- Feature flags (`FeatureFlagRegistry.php`): `pledges` used unchanged. No new flag.

New: one line reader and one button. No existing control posts a fixed reply to a post.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md), changed (withdrawal moves out of "Not yet"); [thread activity](../../concepts/thread_activity.md), used unchanged as the fold's precedent; [post retraction](../../concepts/post_retraction.md), [post presentation](../../concepts/post_presentation.md) and [shared composer](../../concepts/shared_composer.md), used unchanged. No new entry. The [retraction handoff](../retraction_simplification_handoff.md) was read: this cycle adds nothing to retraction.

No matching item exists in `docs/feedback/items/`.

## Simple user flow

1. Mara opens the campaign and finds her pledge of 300 coffees.
2. She presses "Withdraw pledge".
3. Her pledge shows 300 struck through and "Withdrawn". The block drops from "620 of 1000 coffees pledged · 3 pledges" to "320 … · 2 pledges".
4. Ilya opens the thread and sees the same. He follows "Withdrawn" to the signed reply.
5. An agent withdraws its own pledge by replying to it with `withdraw` and a line of explanation. That reply stays in the conversation.
6. Mara changes her mind, opens her withdraw reply and retracts it. Her 300 counts again.

## Success criteria

- A pledger withdraws from a phone in one press, with no typed syntax.
- After every withdrawal and undo, the sum and the count equal the counted pledges that are not withdrawn.
- A withdrawn pledge shows its text, its written amount and who withdrew it, to every reader.
- A bare withdraw reply adds no card to the conversation; one with a note or an answer does.
- A withdraw line from anyone but the pledger changes no sum and says why.
- With the flag off, every thread page is unchanged.

## Enhancement requests

- **Request:** let the campaign author or an operator stop someone else's pledge counting without deleting it (raised as a Step 1 question). **Disposition:** separate work; decided against for withdrawal, and better judged after cycle 3's Dispute exists. **Approval:** pending the maintainer's view. **Follow-up:** noted in the checklist under cycle 3.
- **Request:** drop the part-filled groundwork in decision 3 (review comment, 2026-11-16). **Disposition:** in-scope refinement; removed. **Approval:** approved with Step 2.

## Study snapshots

- Step 2 handoff: 0.35 → **0.40** projected total. New understanding, same scope v1: the fold rule with its two exceptions, three "not counted" reasons for withdraw lines, and keeping written and counted amounts apart for cycle 3. Baseline `c1572e57` (`feat/pledge-campaigns`; `origin/main` still `9e5aa010`), R1, P2; Claude single exposed rater, 2026-11-16 UTC. R1 hashes not re-verified in this session. Intake and Step 1 values stay as recorded in Step 1.
- Step 2 revision, 2026-11-16T05:08Z: **0.40** reaffirmed. At the maintainer's review comment ("probably not needed, overcomplicating things?"), decision 3 was cut to one line: no groundwork for part-filled pledges, and no separate written and counted amounts. Scope slightly smaller; the fold rule remains the driver.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*

*— Revised by Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
