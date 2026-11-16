> **Feature plan:** [Step 1](./pledge_campaigns_step1_solution_assessment.md) · [Step 2](./pledge_campaigns_step2_feature_description.md) · [Step 3](./pledge_campaigns_step3_development_plan.md) · [Step 4](./pledge_campaigns_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_campaigns_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_campaigns`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 1 approved 2026-11-16 (Option A). Step 2 approved 2026-11-16 by Claude under the maintainer's one-time delegation (see Delegated approval).

**Cycle estimate: 0.50**

# Step 2: Feature Description — Pledge campaigns

First of seven cycles in [pledges_checklist.md](../pledges_checklist.md). Design: [pledges_spec.md](../pledges_spec.md). Mock-ups: [design canvas](https://claude.ai/artifact/GaT42e25otTieeNpiFawch).

## Problem

A thread cannot state a goal in a unit, replies cannot carry an amount toward it, and nothing adds them up.

Approach (Step 1): Option A. The opening post carries a `goal:` line and a reply carries a `pledge:` line. The thread page reads them each time it is shown. Nothing new is stored.

## User stories

- As a campaign author, I want to write `goal: 1000 coffees` in a new thread so that readers see what is needed and how much is promised.
- As a reader, I want to pledge a number from the thread page so that I do not have to learn a line format.
- As an agent, or a reader without JavaScript, I want to pledge by typing one line so that every client can take part.
- As a pledger whose line could not be counted, I want to be told why so that I can post it again.
- As an operator, I want one flag that turns pledges off so that an instance can do without them.

## Core requirements

- With the `pledges` flag on, a thread whose opening post has a `goal:` line is a campaign. Its page shows a totals block under the opening post: the pledged sum, the goal with its unit, a bar with a goal mark, and the number of pledges. The sum may pass the goal.
- A reply with a `pledge:` line shows its amount and unit on the reply card. Counted amounts add up. One person may pledge more than once.
- The reply form on a campaign offers Pledge beside Reply. Pledge takes a number and an optional note and posts an ordinary reply with the line written in. It works without JavaScript and with signed, unsigned and Outbox sending.
- Nothing is rejected. A line that is not counted stays in the reply as written. Its author sees a short reason on that reply.
- A retracted pledge stops counting, as any retracted directive line does. With the flag off, the lines are plain text and no block or box appears.

## Decisions on the Step 1 questions

**1. Does a pledge count when its post's attribution is unverified? No, and only where the instance has asked for that.**

- A post is unverified when it names a member, is not signed by that member, and was made after the instance's cutoff. A cutoff exists only once the operator has turned on Verified attribution.
- An unverified pledge shows its amount, marked "unverified", and stays out of the sum. The same rule already governs `theme:` and `event:` lines, scores and post counts.
- Reason: the sum is what the author plans on. An unsigned post naming mara is not mara's promise, and it would otherwise raise the total in her name.
- On an instance without a cutoff, every named pledge counts, signed or not. Keyless readers lose nothing there.
- A pledge counts whether or not its key is approved. The spec says anyone who may reply may pledge. Fills, in cycle 3, are the check on empty pledges.
- A pledge from a post with no name shows its amount and stays out of the sum. Nobody can be asked to deliver it. The spec already lists anonymous pledges as not in v1.
- The `goal:` line needs no verification. It describes its own thread and claims nothing in anyone else's name.

**2. May amounts be decimals? Yes, to two places.**

- A number is digits with an optional decimal point and at most two decimal places: `300`, `1.5`, `12.50`. It must be above zero.
- Commas are read only as thousands grouping: `1,000` counts, `1,5` does not. No signs, fractions, exponents or words.
- The sum is exact to two places and drops trailing zeros. Each pledge shows its amount as written.
- The goal line is a number and then the unit as free text. A pledge line may repeat the unit (`pledge: 300 coffees`). Any other trailing text leaves the pledge uncounted, because units cannot be mixed.

## Delivery scope

Work type: application change.

Not in this cycle: withdrawal, fills, stages and the celebration banner, state badges on pledges, marker posts, board rows, the text view and agent API documentation, and configuration. Each has its own cycle in the checklist. The composer gets no goal field; the author types the goal line.

## Completion boundary

- Entry: the New post page for the goal line, and the reply form on the campaign thread for a pledge.
- End-to-end outcome: an author posts a campaign, a second person pledges from the Pledge box, and the thread shows the pledge amount and the new sum.
- Recovery: a refused or offline pledge keeps its number and note in the form, as replies do. An uncounted pledge tells its author why; they post a corrected one. An operator turns the flag off to hide every block and box.
- Release: with the flag on at zenmemes.com, Ilya posts a campaign and pledges from his iPhone SE, and sees the sum change on the thread page.

## Risks

- **Static thread pages show an old sum.** Impact: a pledge posts but the total does not move, including in high-traffic mode. Validate early: pledge on a thread served from its static file and reload. Mitigation: the block is part of the thread page, which is already rebuilt when a reply arrives.
- **The Pledge box breaks a composer mode.** Impact: signed, no-JavaScript, Outbox or offline replies stop working on campaigns. Validate early: post one pledge by each route before styling. Mitigation: the box only adds a line to the body the composer already sends.
- **The block looks wrong in some themes.** Impact: an unreadable bar or sum in one of 13 themes. Validate early: render the block in every theme at phone and desktop width. Mitigation: build it from existing theme colours, as the event block is.
- **The "not counted" reason needs to know the viewer.** Impact: thread pages are the same for every reader on first paint. Validate early: confirm the pattern used for a viewer's own reaction fits. Mitigation: if it does not, show a neutral "not counted" mark to everyone.

## Shared component inventory

- Directive readers (`ThemeDirective.php`, `EventStatusDirective.php`, `TitleDirective.php` in `src/ForumRewrite/Support/`): pattern reused for two new readers. Quoted lines are skipped and the first match in a post wins.
- Derived thread state (`View/ThreadTheme.php`, `Calendar/EventStatus.php`): pattern reused for a campaign totals reader, including their test for unretracted, counted posts.
- Verified attribution (`ReadModel/VerifiedAttribution.php`): used unchanged to decide which pledges count.
- Event block (`templates/partials/event_block.php`, shown by `thread_root_card.php`): the totals block sits in the same place and follows its styling.
- Reply card (`templates/partials/post_card.php`): extended with the amount line.
- [Shared composer](../../concepts/shared_composer.md) (`composer.php`, `reply_form.php`): extended with the Pledge choice. No second form.
- Feature flags (`FeatureFlagRegistry.php`): extended with `pledges`, off by default.
- Thread page (`ThreadAndPostPageController.php`, `templates/pages/thread.php`): extended to pass campaign totals, beside the thread theme and event state.

New: two line readers, a totals reader and a totals partial. No existing block sums amounts.

Concepts touched: [thread title](../../concepts/thread_title.md) and [page theme](../../concepts/page_theme.md), used unchanged; [shared composer](../../concepts/shared_composer.md) and [post presentation](../../concepts/post_presentation.md), changed; "pledge campaign", proposed for the inventory in Step 4.

## Simple user flow

1. Ilya opens New post, writes the pitch with a line `goal: 1000 coffees`, and posts.
2. The thread shows "0 of 1000 coffees pledged" and an empty bar.
3. Mara opens the thread, chooses Pledge, enters 300 and a note, and posts.
4. Her reply shows "300 coffees". The block shows "300 of 1000 coffees pledged".
5. An agent replies with the line `pledge: 12.50`. The sum becomes 312.5.
6. Rin replies `pledge: a few`. The reply stays as written, and Rin sees that it was not counted.

## Success criteria

- A campaign and a pledge can each be made from a phone with no typed syntax beyond the goal line.
- The sum equals the counted pledges exactly, for whole numbers and decimals, after every reply and retraction.
- Unverified and unnamed pledges are visible, marked, and absent from the sum.
- A pledge posts by every route: signed, unsigned, no JavaScript, Outbox and the agent API.
- With the flag off, every thread page is unchanged.

## Enhancement requests

- **Request:** the plain theme drawn for the mock-ups. **Disposition:** separate work; a new theme is not part of reading and showing pledges. **Approval:** pending. **Follow-up:** to be listed in the checklist.
- **Request:** withdrawal, fills, stages, board rows, agent text and configuration. **Disposition:** separate work, cycles 2 to 7. **Approval:** each cycle needs its own.

## Delegated approval

Maintainer, 2026-11-16 UTC, after reading the Step 2 handoff: "I was wondering if just for this one time we can experiment with you also being the approver of the steps and going all the way to completion, but still do the work for generating the steps and then look them over yourself."

- Boundary: this cycle only. Claude writes each step, reviews it against the step's checklist, and records the approval. Merging, deploying, dependency changes and production access stay with the maintainer. The flag stays off by default.
- Step 2 review: one page exceeded because of the decisions section; no code or schema details; risks are not buried; the slice is usable end to end. Approved as written, including the five calls listed in the handoff. The maintainer may still overrule any of them in PR review.

## Study snapshots

- Step 2 handoff: 0.45 → **0.50** projected total. New understanding, same scope v1: number rules, three display states for a pledge (counted, unverified, unnamed) and the author's "not counted" reason. Baseline `9e5aa010ddff888248ca670c932e3b5f7ec69274`, R1, P2; Claude single exposed rater, 2026-11-16 UTC. R1 hashes not re-verified in this session. Intake and Step 1 values stay as recorded in Step 1.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
