> **Feature plan:** [Step 1](./pledge_cards_step1_solution_assessment.md) · [Step 2](./pledge_cards_step2_feature_description.md) · [Step 3](./pledge_cards_step3_development_plan.md) · [Step 4](./pledge_cards_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_cards_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_cards`; [ledger](../effort_ledger/pledge_cards.md). Standing P2 enrollment. Parent: `v3/pledge_frontend`.

Extended estimates: enabled

Status: Steps 1 (Option A) and 2 approved by the maintainer 2026-11-17. Step 3 approved by the maintainer 2026-11-17 ("approved step 3").

**Cycle estimate: 0.65**

# Step 3: Development Plan — Pledge cards

## Completion Contract

- Entry: a campaign thread with `FORUM_PLEDGES_ENABLED` on, on a phone and on desktop, signed in or not, with and without JavaScript.
- End-to-end outcome: each pledge is one row (name, amount, status). Rows next to each other form one list, and no row merges into a same-author run. Opening a row shows a short header, the note, the fill lines and "More", which holds every button. Other replies on the thread show the short header and "More". Only approved members' pledges, fills, withdrawals and reviews count. Other pledges read "Waiting for approval" or "Not counted: no name" and stay out of the total. `FORUM_PLEDGES_COUNT_UNNAMED_ENABLED` is gone.
- Recovery: a failed press inside "More" shows its usual error and can be pressed again. A key approved later counts on the next view. Turning `pledges` off returns every page to how it is now.
- Deployment: no schema change and no dependency. One flag removed; stored records that name it keep loading.
- Release condition: Ilya checks that the "claude" key is approved on zenmemes.com, or accepts that the demo's pledges will wait. Then, on his iPhone SE, he sees the list, opens a row and confirms a delivery from "More". Not possible from this session; it stays pending in Step 4.

## Findings from the checks Step 2 asked for

Checked by reading the code at `6e82449d`.

- **Removing the flag.** Neither `SiteFeatureFlagsRecordParser` nor `FeatureFlagChangeRecordParser` checks keys against the registry. `FeatureFlagEvaluator` reads only registered keys. `FeatureFlagChangePageController` falls back to the raw key when the definition is missing. A stored record that still names the flag loads; Stage 1 adds a test for it.
- **Buttons inside a closed disclosure.** `thread_reactions.js` handles clicks on each post root and finds the button with `closest()`. `pledge_withdraw.js` listens for `submit` on the document. Buttons moved into "More" work without changes; Stage 3's browser check confirms it.
- **Approval.** Thread posts carry `author_is_approved` from `AuthoredContentRepository`, with `was_approved` for retired keys. No new query.
- **The fold.** `post_card.php` makes a card foldable only when a tagset unlocks an exclusive state (`$postFoldable`). Pledge rows add a second trigger to the same `<details class="post-resolution">`.

## Key Risks

- **Two disclosures on one card.** Impact: on a `#meta` thread a pledge could fold for its tagset state and as a row. Early validation: Stage 2 renders a pledge on a `#meta` thread and checks for one `details.post-resolution`. Mitigation: one disclosure, opened if either reason says so.
- **Totals drop on release.** Impact: the demo thread, or a real campaign, falls to zero when the rule lands. Early validation: none in this session; the release condition covers it. Mitigation: waiting rows make it visible.

## Stage 1

**Estimate: 0.18**

- Goal: only approved members' pledge lines count; the count-unnamed flag is gone.
- Dependencies: none.
- Expected changes: `PledgeCampaign::fromPosts()` drops its `$countUnnamed` parameter. A pledge, fill, withdrawal or review counts only when `author_is_approved` is 1 and its attribution counts. A signed, unapproved pledge gets state `waiting` ("Waiting for approval"); a pledge with no name keeps "Not counted: no name"; the old unverified state folds into waiting. `ThreadAndPostPageController` stops passing the flag. `FeatureFlagRegistry` loses `PLEDGES_COUNT_UNNAMED_ENABLED` and its definition. `FeatureFlagEvaluatorTest`, `PledgeCampaignTest` and the browser check drop the flag and its `--count-unnamed` mode.
- Verification approach: `PledgeCampaignTest`: an approved pledge counts; an unapproved one waits and stays out of the sum; a fill, withdrawal or review from an unapproved key does not count. A pledge counts after its key is approved. `FeatureFlagEvaluatorTest`: a stored site record and a change record that name the removed flag still load. Fixtures mark their keys approved where tests expect counting. Smoke set.
- Estimate details: driver: fixtures across `PledgeCampaignTest` and the browser check that assume any signed key counts; uncertainty: how many.
- Risks or open questions:
  - Impact: existing tests rely on unapproved fixture keys counting. Early warning: the first test run. Mitigation: approve the fixture keys, as `ApprovalPolicy` tests do.
- Canonical components/API contracts touched: `PledgeCampaign::fromPosts()` (one parameter removed, one state renamed), `FeatureFlagRegistry`, the thread controller, `author_is_approved` (reused).

## Stage 2

**Estimate: 0.20**

- Goal: each pledge is a one-line row, and rows form a list.
- Dependencies: Stage 1.
- Expected changes: `post_card.php` gives a pledge reply on a campaign thread the fold's `<details>`, closed. Its summary shows name, amount and status from `pledge_amount.php`. A card that also folds for a tagset state keeps one disclosure. `thread.php` ends a same-author run at a pledge row. CSS joins adjacent rows into one list, with theme variables only.
- Verification approach: `PledgeCampaignTest` render checks for a row's name, amount and status; one disclosure on a `#meta` thread; two pledges in a row from one key render as two rows. Screenshots at 375 pixels in 3 themes. Smoke set.
- Estimate details: driver: the fold's existing states beside the new trigger; uncertainty: continuation runs.
- Risks or open questions:
  - Impact: a folded action reply, such as a bare `withdraw`, lands between two rows and breaks the list. Early warning: the screenshot. Mitigation: folded replies render nothing between rows, as today.
- Canonical components/API contracts touched: fold markup and CSS (`.post-resolution`, `PostStateView`), `pledge_amount.php` (row summary), run building in `thread.php`.

## Stage 3

**Estimate: 0.17**

- Goal: a short header and "More" in place of the byline and action row, on campaign threads.
- Dependencies: Stage 2.
- Expected changes: `post_card.php` gains one switch, on when the thread is a campaign. With it, the byline becomes "name · short date" without "(unapproved)", and the action row moves into a `<details>` "More" like the composer's menu. "More" holds Reply, the pledge forms, Like, Flag, unlocked tags, Agent and Retract. This applies to opened pledge rows and to ordinary replies. The opening post is unchanged.
- Verification approach: run the browser check unchanged first. Then add presses inside "More": Like, Confirm (with a failed first press), Withdraw, and Reply. The no-JavaScript visitor pledges and withdraws through "More". `PledgeCampaignTest`: header and "More" on a campaign thread, the plain byline on other threads. Smoke set.
- Estimate details: driver: the browser check's existing locators moving behind "More"; uncertainty: continuation run tails, where the byline sits after the body.
- Risks or open questions:
  - Impact: a button outside the reaction root stops working. Early warning: the unchanged browser run. Mitigation: "More" stays inside the post card, which is the root.
- Canonical components/API contracts touched: `post_card.php` (one switch), `$contentMeta` (reused, short form), the composer menu's `<details>` pattern, `thread_reactions.js` and `pledge_withdraw.js` (reused unchanged).

## Stage 4

**Estimate: 0.10**

- Goal: checked in every theme, replayed at the reported size, and documented.
- Dependencies: Stage 3.
- Expected changes: [pledge campaign](../../concepts/pledge_campaign.md) concept (rows, approval, waiting, the flag's removal); the [spec](../pledges_spec.md) and checklist record that cycle 1's "approval is not required" is overruled; the ledger, debug log and Step 4 closeout.
- Verification approach: the browser check in 17 themes at 375 and 1280 pixels. A local replay at 375 by 667 pixels, with and without JavaScript, of a thread with seven approved pledges, one unapproved and a question. Smoke set with `PledgeCampaignTest`, `WithdrawLeafFilterTest` and `FeatureFlagEvaluatorTest`; `git diff --check`; review the whole diff. The full suite is not run. The iPhone SE check stays pending for the maintainer.
- Estimate details: driver: 34 theme passes; uncertainty: low.
- Risks or open questions:
  - Impact: the headless pass is taken as the release check. Mitigation: the closeout lists the iPhone SE check and the "claude" key's approval as pending.
- Canonical components/API contracts touched: concept inventory (updated).

Concepts touched: pledge campaign, changed. No new entry. No matching item in `docs/feedback/items/`.

## Branch

Planned on `origin/main` at `6e82449d`. After `Approved Step 3`, Step 4 cuts `feat/pledge-cards` from a fresh `origin/main` and makes the planning commit first.

## Study checkpoint

- Step 3 handoff: **0.65** reaffirmed, scope v4. Four stages; stage work 0.18, 0.20, 0.17, 0.10, not summed. The flag and button checks above removed two Step 2 risks. Baseline `6e82449d`, R1, P2; Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
