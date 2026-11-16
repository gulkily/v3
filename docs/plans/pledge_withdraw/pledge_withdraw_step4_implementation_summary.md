> **Feature plan:** [Step 1](./pledge_withdraw_step1_solution_assessment.md) · [Step 2](./pledge_withdraw_step2_feature_description.md) · [Step 3](./pledge_withdraw_step3_development_plan.md) · [Step 4](./pledge_withdraw_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_withdraw_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_withdraw`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_campaigns`.

Extended estimates: enabled

Status: Steps 1 to 3 approved by the maintainer 2026-11-16. Step 4 technically complete on `feat/pledge-withdraw`; release check and maintainer acceptance pending.

**Cycle estimate: 0.40**

# Step 4: Implementation Summary — Pledge withdraw

## Entry

- Branch: [PR #268](https://github.com/gulkily/v3/pull/268) was merged when Step 3 was approved, so Step 3's Branch question fell away. `feat/pledge-withdraw` starts from a fresh `origin/main` at `b9ffa6b8`. The Step 1 commit from `plan/pledge-withdraw` was carried over; nothing was pushed to an existing branch.
- Planning commit: `fc318f72`, Steps 1 to 3 and the debug log, moved to this folder.
- Entry projection: **0.40**, Step 3 value reaffirmed. Starting revision `b9ffa6b8` (Step 3 was written against `c1572e57`). Stage work estimates copied from Step 3: 0.12, 0.08, 0.14, 0.06; not summed. R1, P2; Claude single exposed rater, 2026-11-16 UTC; R1 hashes not re-verified.
- Discovery at entry, new understanding and same scope: `main` gained "Count pledges with no name" (`FORUM_PLEDGES_COUNT_UNNAMED_ENABLED`, PR #271) after Step 3 was written. A counted pledge with no name has no author to match, so nobody can withdraw it; an operator can still retract it. Stage 1 tests this. No estimate change.
- Reuse decisions carried from Steps 2 and 3: extend `View/PledgeCampaign.php` and `templates/partials/pledge_amount.php`; follow `Support/PledgeDirective.php` for the line reader, `ReadModel/TagOnlyLeafFilter.php` for the fold, and `templates/partials/retract_post_control.php` for the button's viewer test and feedback.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*

## Stage 1 - A withdraw reply marks the pledge and changes the sum

**Original estimate: 0.12**

**Retrospective stage work: 0.10**

- Changes:
  - New `src/ForumRewrite/Support/WithdrawDirective.php`: `extract()` reads `withdraw` or `withdraw:` and returns the text after it; `isBare()` says whether the body is that line alone.
  - `View/PledgeCampaign::fromPosts()` gains the `withdrawn` state and a `withdrawals` list keyed by reply ID, each with whether it took effect and, if not, the reason. A withdrawn pledge's entry names the withdraw reply and its time. Other entries keep their cycle 1 shape.
  - `templates/partials/pledge_amount.php` shows a withdrawn pledge as its struck amount, "Withdrawn" linked to the withdraw reply's page, and the date. A withdraw reply that did not take effect shows its reason.
  - No style rule was needed: the existing rule strikes every amount that is not counted.
- Verification:
  - Passed: `./v3 test --smoke PledgeCampaignTest`, 478 run, 478 passed. Three new tests cover the line reader; the pledger, another key, a second withdrawal, text after the line, unverified, no name, a reply to a non-pledge, a retracted withdraw reply (undo), a retracted pledge and the "count pledges with no name" flag; and the thread page with a baked page before and after a withdrawal, and the flag off.
  - Not run: the full suite.
- Notes:
  - The reason for text after the line reads "Not counted: write “withdraw” alone on its line". Step 2 asked for a reason and did not word it.
  - A reply that carries both a `pledge:` and a `withdraw` line is read as both: it withdraws its parent and pledges anew. Step 2's "withdraw it and pledge again" can be one reply.
  - Reuse: `PledgeDirective.php` pattern for the reader; `PledgeCampaign.php` and `pledge_amount.php` extended; `pledge_totals.php` unchanged.
- Estimate details: original scope as Step 3 Stage 1; no revision. Retrospective judged 2026-11-16T05:37Z, Claude single exposed rater.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*

## Stage 2 - A bare withdrawal folds into the pledge

**Original estimate: 0.08**

**Retrospective stage work: 0.06**

- Changes:
  - New `src/ForumRewrite/ReadModel/WithdrawLeafFilter.php`: drops a reply from the conversation when it is the `withdraw` line alone, took effect and no shown reply answers it. It repeats until stable, as `TagOnlyLeafFilter` does.
  - `templates/pages/thread.php` applies it after `RetractedLeafFilter` and before the activity drawer, only on a campaign.
- Verification:
  - Passed: `./v3 test PledgeCampaignTest WithdrawLeafFilterTest ThreadActivityTest RetractedLeafFilterTest`, 14 run, 14 passed.
  - New `WithdrawLeafFilterTest`: bare, noted, answered, not counted, a chain of two, and a `withdraw` reply the campaign did not read.
  - New page test with the activity drawer flag on: the bare withdrawal has no card, the pledge's "Withdrawn" links to its page, the reply's own page still shows it, and noted and answered withdrawals keep their cards. With the pledges flag off nothing folds.
  - Undo: after the withdraw reply is retracted, the pledge counts again and no "retracted" stub shows. The test marks the read-model row retracted; the signed retraction request itself was not replayed.
- Notes:
  - The reply count on the opening post is taken after the filters, so it matches the cards shown. The risk named in Step 3 did not occur.
  - A third filter was added because the rule needs the campaign's reading of each reply, which the other two filters do not have.
  - Reply pages, paned layouts and Forte are unchanged, as planned.
- Estimate details: original scope as Step 3 Stage 2; no revision.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*

## Stage 3 - Withdraw pledge button

**Original estimate: 0.14**

**Retrospective stage work: 0.12**

- Changes:
  - `templates/partials/pledge_amount.php` shows a "Withdraw pledge" form under a counted pledge when the viewer is its author. The test is the one `retract_post_control.php` uses. The form is a reply to the pledge with the body `withdraw`.
  - New `public/assets/pledge_withdraw.js` signs and sends that reply, disables the button while sending, shows "Withdrawal failed. Try again." beside it on failure, and reloads on success. `ThreadAndPostPageController` loads it on campaign thread pages. One spacing rule in `site.css`.
- Verification:
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest ThreadActivityTest RetractedLeafFilterTest`, 483 run, 483 passed. A new test checks the form shows once, for the pledger, on the counted pledge only; not for no viewer or another viewer; not on a withdrawn pledge or a counted pledge with no name.
  - Passed: `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/pledge_campaign.mjs --screenshots=/tmp/pledges` in headless Chrome. The pledger posts a signed pledge of 5 with a note and presses the button. The first press is made to fail: the message shows, the button returns and the sum stays. The second press works: 1305 becomes 1300, the count drops to 2, the amount is struck through with "Withdrawn" and the date, the note stays, no card is added, the button is gone, the label opens the reply's page, and a second reader sees the mark and no button. The 17 themes at 375 and 1280 px then passed with the Withdrawn label drawn in each.
  - Without JavaScript (form post in the PHP test): the reply has no name, the pledge still counts, and the reply shows "Not counted: no name".
  - Not run: the full suite. Outbox and offline sending were not exercised.
- Notes:
  - Refinement: Step 3 named `submitSignedReplyFormToApi`. That function is not exposed to other scripts and expects a composer form. The script uses `ForumBrowserSigning.prepareOutboxPost` and `finalizeOutboxPost`, the exposed pair the Outbox sender uses. `browser_signing.js` is unchanged.
  - A second press after a failure cannot do harm: a second withdrawal changes nothing and folds.
  - The server refuses nothing. The rule that only the pledger withdraws is applied when the thread is read (Stage 1).
  - Playwright was resolved from the workspace's own install through a link outside the repository; nothing was added to the project.
- Estimate details: original scope as Step 3 Stage 3; no revision.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*

## Stage 4 - Theme check, documentation and closeout

**Original estimate: 0.06**

**Retrospective stage work: 0.05**

- Changes:
  - `docs/concepts/pledge_campaign.md` gains withdrawal: the line, the button, the same-key limit, the reasons, the fold and undo. "Not yet" now starts at fills.
  - `docs/concepts/thread_activity.md` gains one line naming the withdraw fold.
  - `docs/plans/pledges_checklist.md` marks cycle 2 built and lists what to review first; `pledges_spec.md` carries the reworded withdrawal lines; the effort ledger has the closeout entry.
- Verification:
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest ThreadActivityTest RetractedLeafFilterTest`, 483 run, 483 passed.
  - Passed: `tests/browser/pledge_campaign.mjs` rerun in headless Chrome, with and without `--count-unnamed`. 17 themes at 375 and 1280 px each show the Withdrawn label with no page overflow. Four phone-width screenshots (Gloss, Word97, Bubblegum, Midnight) and the pledger's view with the button were looked at.
  - Passed: `git diff --check`; local links in the touched concept entries, this folder and the checklist resolve.
  - Diff review: 11 code and test files, all in Step 3's list except `site.css` (one spacing rule) and the controller's script list. No schema change, no new flag, no dependency.
  - Not run: the full suite.
  - Pending: the release check on zenmemes.com from the maintainer's iPhone SE. It cannot be done from this session.
  - Not exercised: a real signed retraction of a withdraw reply (undo was tested by marking the read-model row retracted); Outbox and offline sending of a withdrawal; a baked page for a signed-in viewer.
- Notes:
  - No navigation destination was added, so no icon mapping changed.
- Estimate details: original scope as Step 3 Stage 4; no revision.

## Completion Contract check

- Entry: passed. Button on the pledger's own pledge, and a typed `withdraw` reply.
- End-to-end outcome: passed in a browser. Struck amount, "Withdrawn", lower sum and count, no new card.
- Recovery: failed press and second press passed in a browser. Undo by retraction passed at the read-model level only. Flag off passed.
- Deployment: no schema change, rebuild, flag or dependency.
- Release condition: pending.

## Enhancement requests

- **Request:** an author's or operator's way to stop someone else's pledge counting without deleting it. **Disposition:** separate work, listed under cycle 3 in the checklist. **Approval:** pending. **Outcome:** not built.
- **Request:** drop the part-filled groundwork (maintainer's review comment on Step 2). **Disposition:** in-scope refinement. **Approval:** approved with Step 2. **Outcome:** nothing was built for part-filled pledges.

## Cycle retrospective

**Retrospective cycle scope: 0.35**

- Delivered scope: Step 2 as approved, with the refinements recorded in Step 3 and Stage 3. Four planned stages, four completed.
- Starting revision `b9ffa6b8`; planning commit `fc318f72`; stage commits `1a842424`, `7f7b86e2`, `0a8004c9` and this stage's commit.
- Entry projection 0.40, no revisions. The fold and the button each reused more than expected: the reply count already follows the filters, and the Outbox's signing pair needed no change.
- Corrective work: none. New requests during Step 4: none.
- Maintainer comfortable-fit judgment: pending. Study acceptance: pending.
- Stage retrospectives 0.10, 0.06, 0.12, 0.05 are separate judgments and are not summed. No measured time was recorded.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
