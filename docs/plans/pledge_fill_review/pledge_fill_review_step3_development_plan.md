> **Feature plan:** [Step 1](./pledge_fill_review_step1_solution_assessment.md) · [Step 2](./pledge_fill_review_step2_feature_description.md) · [Step 3](./pledge_fill_review_step3_development_plan.md) · [Step 4](./pledge_fill_review_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_fill_review_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_fill_review`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_fills`.

Extended estimates: enabled

Status: Steps 1 (Option A) and 2 approved by the maintainer 2026-11-16 and 2026-11-17. Step 3 approved by the maintainer 2026-11-17.

**Cycle estimate: 0.40**

# Step 3: Development Plan — Pledge fill review

## Completion Contract

- Entry: "Confirm" or "Dispute" under a pledger's fill on the campaign thread page, for the author or an operator; or a typed reply to the fill reply with `confirm` or `dispute`.
- End-to-end outcome: a confirmed fill reads "Delivered by mara · confirmed by ilyag" and joins "confirmed". A disputed fill is struck through with "disputed by ilyag", leaves "filled", and its pledge is open again. Each review links to its reply. A bare confirm adds no card; a dispute and the fill it answers stay in the conversation.
- Recovery: a failed press reports under the fill and can be pressed again. Retracting a review undoes it; a later review replaces it. Turning `FORUM_PLEDGES_ENABLED` off returns every line to plain text.
- Deployment: no schema change, no rebuild, no new flag, no new dependency.
- Release condition: with the flag on at zenmemes.com, Ilya confirms and disputes a test fill from his iPhone SE, then retracts the dispute. Not possible from this session; it stays pending in Step 4.

## Findings from the checks Step 2 asked for

Checked by reading the code at `4a6c8bd0`; the tests that prove them land in Stages 1 and 3, before the stages that depend on them.

- **Reading order works.** Whether a review counts depends only on the counted pledges and on who wrote the fill reply, both known before `fills()` runs. So a review pass can run first, and `fills()` skips disputed replies, leaving their room open.
- **The fold already handles it.** `WithdrawLeafFilter` recomputes "answered" from the replies it has not folded, and repeats. A bare confirm folds first, then the bare fill it answered folds next time round. A dispute does not fold, so its fill stays answered and both stay.
- **The buttons fit.** `pledge_amount.php` draws each fill line from `fill_list`, so a form per line can reply to that fill's post ID. `pledge_withdraw.js` sends any `pledge-line` form's hidden body, so it needs no change.

## Refinements to Step 2, within its scope

- A disputed fill takes no room. Its line shows the amount it claimed: the number written, or what was left when it was bare.
- Buttons show only on an unreviewed pledger fill. Changing a review means typing the other line or retracting the first.

## Key Risks

- **High risk: totals disagree with the fills shown.** Impact: readers stop trusting "filled" and "confirmed". Early validation: Stage 1 tests fill, dispute, fill again; confirm then dispute; dispute then retract; a capped fill confirmed. Mitigation: one reader, reviews read before fills.
- **Folding hides the dispute.** Impact: the pledger cannot see or answer it. Early validation: Stage 3 fold tests for bare confirm, noted confirm, dispute and an uncounted review. Mitigation: fold only effective bare confirms.
- **Review buttons break the fill and withdraw buttons.** Impact: pledgers cannot mark delivered. Early validation: Stage 3 runs the existing browser check before adding the review press. Mitigation: reuse the `pledge-line` form and script unchanged.

## Stage 1

**Estimate: 0.12**

- Goal: review replies are read, and filled and confirmed follow them.
- Dependencies: none.
- Expected changes: new `Support/ReviewDirective` with `extract(string $body): ?array` (the word, confirm or dispute, and any text after it; quoted lines skipped) and `isBare(string $body): bool`. `PledgeCampaign::fromPosts()` gains a private `reviews()` run before `fills()`; the latest counted review on each fill reply decides. `fills()` skips disputed fills, and adds confirmed pledger fills to `confirmed`. Each `fill_list` entry gains `review` (confirmed, disputed or none), `review_by` and `review_post_id`. The result gains `reviews`, keyed by reply ID: effective or a reason. Reasons: not a reply to a pledger's fill, only the author or an operator can review, your own fill, no name, unverified, text after the line.
- Verification approach: `PledgeCampaignTest` for author and operator confirm and dispute; another key; a review on an author's fill; text after the line; fill, dispute, fill again; confirm then dispute and back; retracting a review; a capped fill confirmed; withdraw after a dispute. Smoke set alongside.
- Estimate details: driver: reading order and the "latest wins" rule; uncertainty: little.
- Risks or open questions:
  - Impact: totals disagree. Early warning: the order tests. Mitigation: reviews first, one pass.
- Canonical components/API contracts touched: `PledgeCampaign::fromPosts()` result (extended), operator check (reused), `WithdrawDirective`/`FilledDirective` pattern (reused).

## Stage 2

**Estimate: 0.06**

- Goal: every reader sees each fill's review and each uncounted review's reason.
- Dependencies: Stage 1.
- Expected changes: `templates/partials/pledge_amount.php` appends "· confirmed by ilyag" or "· disputed by ilyag" to a fill line, linked to the review reply; a disputed line is struck through like a withdrawn amount. A review reply that does not count shows its reason, as fill replies do. Totals block unchanged.
- Verification approach: `PledgeCampaignTest` render checks for both labels and a reason; a look at one theme at 375 pixels.
- Estimate details: driver: one template; uncertainty: strike-through styling in themes.
- Risks or open questions:
  - Impact: a theme does not strike the line. Early warning: Stage 4 theme pass. Mitigation: reuse the withdrawn state's markup.
- Canonical components/API contracts touched: `pledge_amount.php` (extended), existing withdrawn styling (reused).

## Stage 3

**Estimate: 0.12**

- Goal: one press confirms or disputes; a bare confirm folds.
- Dependencies: Stages 1 and 2.
- Expected changes: `WithdrawLeafFilter::apply()` takes the `reviews` map and folds effective bare confirms. The thread page passes it. `pledge_amount.php` draws "Confirm" and "Dispute" forms on an unreviewed pledger fill line for the author or an operator, with the same viewer test as "Mark filled"; each is a `pledge-line` reply to the fill with the bare line as body.
- Verification approach: `WithdrawLeafFilterTest` for bare confirm, noted confirm, dispute and an uncounted review. `tests/browser/pledge_campaign.mjs`: run unchanged first, then add a confirm press, a dispute press and a failed first press.
- Estimate details: driver: the browser check; uncertainty: headless Chrome availability.
- Risks or open questions:
  - Impact: existing buttons break. Early warning: the unchanged browser run. Mitigation: no script change.
- Canonical components/API contracts touched: `WithdrawLeafFilter` (extended), `pledge_withdraw.js` (reused unchanged), `viewerCanRetractOthers` (reused).

## Stage 4

**Estimate: 0.06**

- Goal: checked in every theme and documented.
- Dependencies: Stage 3.
- Expected changes: the [pledge campaign](../../concepts/pledge_campaign.md) entry gains review and drops Released from "Not yet"; the spec notes review lines and that Released was dropped; the checklist, ledger and debug log; the Step 4 closeout.
- Verification approach: the browser check in each theme at 375 and 1280 pixels; smoke set with `PledgeCampaignTest` and `WithdrawLeafFilterTest`; `git diff --check`; review the whole diff. The full suite is not run.
- Estimate details: driver: 17 themes; uncertainty: browser availability.
- Risks or open questions:
  - Impact: a theme check cannot run. Early warning: no browser binary. Mitigation: report it unverified.
- Canonical components/API contracts touched: concept inventory (extended).

Concepts touched: pledge campaign, changed; post retraction and operator grant, used unchanged. No matching item in `docs/feedback/items/`.

## Branch

Planned on `origin/main` at `4a6c8bd0` (refreshed 2026-11-17; the PRs since `f13ed676` touch no pledge code). After `Approved Step 3`, Step 4 cuts `feat/pledge-fill-review` from a fresh `origin/main` and makes the planning commit first.

## Study checkpoint

- Step 3 handoff: **0.40** reaffirmed, scope v1 with the two refinements above (neither widens scope). Stage work 0.12, 0.06, 0.12, 0.06, not summed; Stage 4 owns shared verification and documentation. Baseline `4a6c8bd0`, R1, P2; Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
