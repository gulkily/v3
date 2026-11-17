> **Feature plan:** [Step 1](./pledge_cards_step1_solution_assessment.md) · [Step 2](./pledge_cards_step2_feature_description.md) · [Step 3](./pledge_cards_step3_development_plan.md) · [Step 4](./pledge_cards_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_cards_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_cards`; [ledger](../effort_ledger/pledge_cards.md). Standing P2 enrollment. Parent: `v3/pledge_frontend`.

Extended estimates: enabled

Status: Steps 1 (Option A), 2 and 3 approved by the maintainer 2026-11-17. Step 4 complete on `feat/pledge-cards`, cut from `origin/main` at `6e82449d`. Release check pending: the maintainer's iPhone SE, and whether the "claude" key is approved on zenmemes.com.

**Cycle estimate: 0.65**

# Step 4: Implementation Summary — Pledge cards

## Entry

- Branch: `feat/pledge-cards` from `origin/main` at `6e82449d`. Planning commit `2752eebb` moved Steps 1–3 and the debug log into `docs/plans/pledge_cards/` and updated the plans index and the pledges checklist. The ledger file goes in with Stage 1.
- Entry projection: **0.65** reaffirmed from Step 3. Stage originals: 0.18, 0.20, 0.17, 0.10, not summed. Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC.

## Stage 1 - Only approved members' pledges count; the count-unnamed flag is gone

**Original estimate: 0.18**

**Retrospective: 0.22**

- Changes:
  - `src/ForumRewrite/View/PledgeCampaign.php`: a private `approved()` check (`author_is_approved` is 1 and attribution counts) decides pledges, fills, withdrawals and reviews. A signed pledge from a key not yet approved gets state `waiting`, "Waiting for approval" (the old `unverified` state folds into it). A pledge with no name keeps "Not counted: no name". Fills, withdrawals and reviews from such keys read "Not counted: waiting for approval". `fromPosts()` loses its `$countUnnamed` parameter.
  - `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php`: `PLEDGES_COUNT_UNNAMED_ENABLED` and its definition removed; the Pledges description says "from approved members". `ThreadAndPostPageController` stops passing it.
  - `tests/FeatureFlagEvaluatorTest.php`: the flag dropped from two lists; a new test loads a stored site record that still names it.
  - `tests/PledgeCampaignTest.php`: post builders mark authors approved; "unverified" expectations become waiting; the count-unnamed cases are replaced by an unapproved pledge, a later approval and an unapproved withdrawal; tests that used a post with no name as the second person now link a real second key (`secondIdentity()`, from the signature fixtures) and approve it (`approveKeys()`); one new render test.
  - `tests/browser/pledge_campaign.mjs`: the `--count-unnamed` mode removed. A helper approves a visitor by writing an approval seed record and updating its profile. The writer is approved once it posts the campaign; the second visitor's first pledge reads "Waiting for approval" and stays out of the sum, then counts once approved.
- Verification:
  - Passed: `./v3 test PledgeCampaignTest` (26 tests, 1 new) and `FeatureFlagEvaluatorTest` (24, 1 new).
  - Passed: the browser check, all 34 theme and width passes, with the new approval step.
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest FeatureFlagEvaluatorTest` (507 tests). The full suite was not run.
- Notes:
  - Two wrong first attempts, both in tests: marking `posts.author_is_approved` (the column lives on profiles and is joined when read), and approving a bare key string (the write service refuses authors with no identity). Then, in the browser check, a direct profile update was undone by the incremental read-model updater, which recomputes approval from records; an approval seed record fixed it. The PHP tests' `approveKeys()` updates the profile only, which holds there because no later write recomputes that key's approval in those tests.
  - Estimate details: original scope as in Step 3; no revision. Retrospective higher: the test fixtures needed real second identities and canonical approval.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 2 - Each pledge is a one-line row

**Original estimate: 0.20**

**Retrospective: 0.22**

- Changes:
  - `templates/partials/post_card.php`: on a campaign thread, a pledge reply with an amount is a row (`pledge-row` on the card). It renders inside the fold's `<details>`, closed, with its own summary (`data-role="pledge-row"`); a card that a tagset would also fold gets only this one disclosure. The old amount line above the text is gone.
  - `templates/partials/pledge_amount.php`: a `row` part draws the summary: name, amount and one status (Open, "5 of 20", Delivered, Withdrawn, or the reason it does not count). The `rest` part, shown once opened, gains a "Withdrawn" link line to the reply that withdrew it. The `head` part is removed. An unreadable pledge has no amount, so it stays an ordinary card with its reason.
  - `templates/pages/thread.php`: a pledge row never joins or extends a same-author run.
  - `public/assets/site.css`: row layout, and adjacent rows joining into one list. The fold hides summaries on cards with no tagset state; the row's summary is exempt.
  - `tests/PledgeCampaignTest.php`: amount expectations moved to the row markup; one new test (one disclosure on a `#meta` thread, two same-author pledges as two rows, a question stays a card).
  - `tests/browser/pledge_campaign.mjs`: `settled()` opens every row by pressing its summary, as a reader would; a check that a new pledge arrives closed with its note hidden; row text checked as "300 · Open"; the no-JavaScript visitor opens rows too. The theme pass screenshots the closed rows first, then checks every row is one line and that adjacent rows touch, then opens them for the existing checks.
- Verification:
  - Passed: `./v3 test PledgeCampaignTest WithdrawLeafFilterTest` (30 tests, 1 new).
  - Passed: the browser check, all 34 theme and width passes, with the closed-row checks.
  - Passed: light, Gloss, Word97, Sticker, Console and Bubblegum at 375 pixels, closed rows looked at: one line each, struck amounts on pledges that do not count.
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest FeatureFlagEvaluatorTest ThreadActivityTest` (510 tests). The full suite was not run.
- Notes:
  - Word97 and Bubblegum draw every card as a window with a title bar, so their rows stay separate windows instead of one joined list. Each is still one line. Left as is: joining them would break those themes' frames.
  - Refinements found by the checks: without JavaScript on a phone, the fixed bottom bar covered a row scrolled to the edge, so the test centres a row before pressing it. Arena's card padding made the first height check fail although each row was one line; the check now measures the summary in lines.
  - The row status says "5 of 20" where the card said "Part delivered: 5 of 20", as Step 2 decided.
  - Estimate details: original scope as in Step 3; no revision. Retrospective slightly higher: the browser check needed a row-opening helper throughout.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 3 - A short byline and "More" on campaign threads

**Original estimate: 0.17**

**Retrospective: 0.16**

- Changes:
  - `templates/partials/post_card.php`: one switch, `$compactCard`, on for every reply card on a campaign thread (the opening post is unchanged). With it, the byline uses the list byline `$rowMeta` ("mara · Nov 17"), and the action row sits in a closed `<details class="post-more">` "More". Reply, Like, Flag, unlocked tags, Agent and Retract are inside it, and so are the pledge's own buttons, placed right after Reply.
  - `templates/partials/pledge_amount.php`: a `buttons` part holds Mark delivered or Mark filled, Withdraw pledge, and Confirm and Dispute for each delivery awaiting review; the `rest` part keeps only lines and reasons. When more than one delivery awaits review, each pair names its amount ("Confirm 20").
  - `public/assets/site.css`: the "More" summary and its row.
  - `tests/PledgeCampaignTest.php`: one new test (short byline and "More" on a pledge and a question, the pledger's buttons inside "More", other threads unchanged); the row test counts fold disclosures only.
  - `tests/browser/pledge_campaign.mjs`: the opening helper also opens "More"; a `press()` helper centres a control before pressing it; a new check that "More" starts closed, then Like works from inside it and Reply links to the right reply.
- Verification:
  - Passed: `./v3 test PledgeCampaignTest WithdrawLeafFilterTest` (31 tests, 1 new).
  - Passed: the browser check, all 34 theme and width passes. Withdraw, Mark delivered, Confirm (with a failed first press) and Dispute are pressed from inside "More", with JavaScript; the no-JavaScript visitor opens rows and posts as before.
  - Passed: the light theme at 375 pixels, closed and opened, looked at.
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest FeatureFlagEvaluatorTest ThreadActivityTest` (511 tests). The full suite was not run.
- Notes:
  - Deviation from Step 2's decision 4 ("(unapproved)" leaves the header): the card reuses the site's list byline, which shows an unapproved author as a small "Pending" chip, and for an approver the chip opens Approve. It reuses an existing component, and it lets the author approve a waiting pledger from the card.
  - Inside "More", authors and operators also see the Retract control's own "…" menu (Show as Markdown, Retract): a second level for two rare actions. Left as is, since that control is shared with every page.
  - An opened row names the pledger twice: in the row and in the byline, whose name links to the profile and carries the Pending chip. Left for the maintainer to judge.
  - Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 4 - Themes, replay at the reported size, documentation and closeout

**Original estimate: 0.10**

**Retrospective: 0.10**

- Changes:
  - [Pledge campaign](../../concepts/pledge_campaign.md) concept: Counting rewritten for approved-only counting, waiting rows and the flag's removal; a new Rows entry; the thread page entry points to it.
  - [Spec](../pledges_spec.md) decision 5 notes the overrule; [checklist](../pledges_checklist.md), ledger closeout and this summary.
- Verification:
  - Passed: the browser check in all 17 themes at 375 and 1280 pixels on the final code (Stage 3's last run, after the last code change).
  - Passed: a local replay at 375 by 667 pixels, the iPhone SE screen, of a thread with five approved members, one key not yet approved, eight pledges, a withdrawal, fills and a question, as a visitor, without JavaScript, and as the author. Each view: seven one-line rows joined into lists, broken by the question; one "Waiting for approval" row, struck and out of the sum; the sum 73, which is the five counted pledges. The replay script was kept outside the repository.
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest FeatureFlagEvaluatorTest ThreadActivityTest` (511 tests, Stage 3). The full suite was not run.
  - Passed: `git diff --check`; the whole code diff reviewed (10 files from `6e82449d`; no schema change, no dependency, one flag removed); no current doc names the removed flag except the concept line that records its removal.
  - Pending: the release check on zenmemes.com from the maintainer's iPhone SE, and whether the "claude" key is approved there. The headless replay does not satisfy it.
  - Not exercised: an approval made through the site's own Approve flow (tests approve through a seed record or the profile row); the paned layouts.
- Notes:
  - Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Cycle retrospective

**Retrospective scope assessment: 0.70**

- Delivered scope: as approved in Steps 2 and 3, with one recorded deviation (the list byline's Pending chip instead of dropping "(unapproved)") and two accepted limits (Word97 and Bubblegum keep rows as separate windows; the Retract control's own "…" sits inside "More").
- Starting revision `6e82449d`; planning commit `2752eebb`; four stage commits. Stage retrospectives 0.22, 0.22, 0.16, 0.10, not summed.
- Corrective work inside the cycle: test fixtures needed real second identities and canonical approval; the browser check needed helpers to open rows and centre controls under the phone's fixed bar. No new requests.
- Maintainer comfortable-fit judgment: pending. Release check: pending.
- Single exposed rater Claude (claude-opus-5-5), 2026-11-17 UTC; R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
