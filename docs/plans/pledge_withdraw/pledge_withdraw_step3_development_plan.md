> **Feature plan:** [Step 1](./pledge_withdraw_step1_solution_assessment.md) · [Step 2](./pledge_withdraw_step2_feature_description.md) · [Step 3](./pledge_withdraw_step3_development_plan.md) · [Step 4](./pledge_withdraw_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_withdraw_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_withdraw`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_campaigns`.

Extended estimates: enabled

Status: Steps 1 and 2 approved by the maintainer 2026-11-16. Step 3 approved by the maintainer 2026-11-16.

**Cycle estimate: 0.40**

# Step 3: Development Plan — Pledge withdraw

## Completion Contract

- Entry: the "Withdraw pledge" button on the pledger's own pledge on the campaign thread page; or a reply to the pledge with the line `withdraw`.
- End-to-end outcome: the pledge shows its amount struck through and "Withdrawn", the sum and count drop, and a bare withdraw reply adds no card to the conversation.
- Recovery: a failed press reports on the pledge and can be pressed again. Retracting the withdraw reply from its own page restores the pledge. Turning `FORUM_PLEDGES_ENABLED` off returns every line to plain text.
- Deployment: no schema change, no rebuild, no new flag, no new dependency.
- Release condition: with the flag on at zenmemes.com, Ilya pledges from his iPhone SE, withdraws, sees the sum change, undoes it and sees the sum return. This cannot be done from this session and stays pending in Step 4.

## Findings from the two checks Step 2 asked for

- **The button can follow Retract exactly.** `retract_post_control.php` is drawn by the server from the viewer's identity hint and compares the viewer with the post's author. The Withdraw button uses the same test. A baked page drawn with no viewer has no Retract and will have no Withdraw.
- **"Same author" is per key.** A post's author is `openpgp:<fingerprint>`, one per key. A pledge made with one `ilyag` key cannot be withdrawn with another `ilyag` key; that reply shows "Not counted: only the pledger can withdraw". Retract has the same limit today. Matching by username would let an unsigned post naming mara withdraw mara's pledge, so this plan keeps the key match and states the limit in the concept entry.

## Refinements to Step 2, within its scope

- **Only a withdrawal that takes effect folds.** A `withdraw` line that is not counted stays in the conversation, because its reason has to be seen.
- **The button is a small form.** It posts a reply to the pledge with the body `withdraw` through the signed reply path the reply form uses. Without JavaScript it posts with no name; that reply does not withdraw and says why.
- **The button sits on the pledge's amount line**, not in the "…" menu, so withdrawing is one press.

## Key Risks

- **High risk: folding hides a reply that matters.** Impact: a note, an answer or a "not counted" reason disappears. Early validation: Stage 2 tests bare, noted, answered and uncounted withdraw replies. Mitigation: only a body that is the line alone, takes effect and has no shown answer folds; if the fold fails, the page shows every reply.
- **Static thread pages show an old sum.** Impact: a withdrawal posts and the total does not move. Early validation: Stage 1 bakes a thread, withdraws, and bakes again. Mitigation: a reply already drops the baked page, as cycle 1 confirmed.
- **The button breaks or bypasses signing.** Impact: an unsigned withdraw that does nothing, or a double post. Early validation: Stage 3 posts one signed withdrawal in a browser before styling. Mitigation: reuse `submitSignedReplyFormToApi`; disable the button while sending.

## Stage 1

**Estimate: 0.12**

- Goal: a `withdraw` reply by the pledger marks the pledge withdrawn and takes it out of the sum and count; retracting that reply restores it.
- Dependencies: none.
- Expected changes: new `Support/WithdrawDirective::extract(string $body): ?string` (text after `withdraw`, empty when bare, null when absent; quoted lines skipped) and `isBare(string $body): bool`. `View/PledgeCampaign::fromPosts()` gains a `WITHDRAWN` state; a withdrawn pledge's entry carries the withdraw reply's ID and date. The result gains `withdrawals`, keyed by reply ID: whether it took effect, and otherwise the reason (not the pledger, not a reply to a counted pledge, text after the line, no name, unverified). `pledge_amount.php` shows the struck amount with "Withdrawn" and the date, linked to the reply's page, and shows a withdraw reply's reason. One style rule in `site.css`.
- Verification approach: `PledgeCampaignTest` covers: pledger withdraws; another key tries; second withdrawal; `withdraw:` and upper case; `withdraw: 100`; quoted line; reply to a non-pledge; unverified and unnamed replies; retracted withdraw reply restores the sum; retracted pledge; flag off; a baked page before and after.
- Estimate details: driver: the reasons table; uncertainty: date format on the label.
- Risks or open questions:
  - Impact: old sum on a static page. Early warning: the baked-page test. Mitigation: existing invalidation on reply.
- Canonical components/API contracts touched: `PledgeCampaign.php` and `pledge_amount.php` (extended); `PledgeDirective.php` (pattern reused); `pledge_totals.php` (used unchanged).

## Stage 2

**Estimate: 0.08**

- Goal: a bare withdraw reply that took effect leaves the thread page's conversation.
- Dependencies: Stage 1.
- Expected changes: new `ReadModel/WithdrawLeafFilter::apply(array $replies, array $withdrawals): array`, modelled on `TagOnlyLeafFilter`: drop a reply when it is bare, took effect and no shown reply answers it; repeat until stable. `templates/pages/thread.php` applies it after `RetractedLeafFilter`, only when the campaign exists. Reply pages, paned layouts and Forte are unchanged.
- Verification approach: a new `WithdrawLeafFilterTest` for bare, noted, answered and uncounted replies. `PledgeCampaignTest` renders the thread page and checks the card is absent and the label's link works. `ThreadActivityTest` runs with the drawer flag on to confirm the two filters do not interfere. Retract a withdraw reply and confirm no "retracted" stub shows.
- Estimate details: driver: order of three filters; uncertainty: reply counts shown elsewhere on the page.
- Risks or open questions:
  - Impact: a reply count on the page disagrees with the cards shown. Early warning: look at the thread header after folding. Mitigation: leave counts as they are and record it, as the activity drawer does.
- Canonical components/API contracts touched: `TagOnlyLeafFilter.php` and `RetractedLeafFilter.php` (pattern reused; a third filter because the rule needs campaign results, which those two do not have); `thread.php` (extended).

## Stage 3

**Estimate: 0.14**

- Goal: the pledger withdraws with one press.
- Dependencies: Stage 1.
- Expected changes: `pledge_amount.php` shows a "Withdraw pledge" form on a counted, not withdrawn pledge when the viewer is its author (the `retract_post_control.php` test). The form carries the thread, the pledge as parent and the body `withdraw`. New `public/assets/pledge_withdraw.js` sends it with `submitSignedReplyFormToApi`, disables the button while sending, reports failure beside it and reloads on success. The thread page loads the script with `pledge_field.js`.
- Verification approach: `PledgeCampaignTest` checks the form shows for the pledger only and not on withdrawn, uncounted or other people's pledges. `tests/browser/pledge_campaign.mjs` gains: press, see the sum drop and no new card; a forced failure shows the message and a second press works. If no browser is available in the session, the browser check stays pending.
- Estimate details: driver: failure and retry feedback; uncertainty: what the signed reply path needs from a form that is not the composer.
- Risks or open questions:
  - Impact: the signed path expects composer fields. Early warning: the first browser press. Mitigation: add the missing hidden fields; if that is not enough, post through the reply API directly and record why.
- Canonical components/API contracts touched: `retract_post_control.php` (viewer test and feedback pattern reused); `browser_signing.js` `submitSignedReplyFormToApi` (used unchanged); `/compose/reply` form contract (used unchanged).

## Stage 4

**Estimate: 0.06**

- Goal: checked in every theme and documented.
- Dependencies: Stages 2 and 3.
- Expected changes: the [pledge campaign](../../concepts/pledge_campaign.md) entry gains withdrawal, the per-key limit and the fold; one line in [thread activity](../../concepts/thread_activity.md) naming the withdraw fold; the checklist, ledger and debug log; the Step 4 closeout.
- Verification approach: render a campaign with a withdrawn pledge in each theme at 375 and 1280 pixels and look at each; smoke set with `PledgeCampaignTest`, `WithdrawLeafFilterTest`, `ThreadActivityTest` and `RetractedLeafFilterTest`; `git diff --check`; review the whole diff. The full suite is not run.
- Estimate details: driver: 17 themes; uncertainty: browser availability.
- Risks or open questions:
  - Impact: a theme check cannot run. Early warning: no browser binary. Mitigation: report it unverified and list it for the maintainer.
- Canonical components/API contracts touched: concept inventory (extended).

Concepts touched: pledge campaign, changed; thread activity, one line added; post retraction, post presentation and shared composer, used unchanged.

## Branch

Cycle 1's PR #268 is open. Step 4 checks it again. If it is still open, this cycle needs the maintainer's word to stack on `feat/pledge-campaigns` (a follow-up cycle before merge) or to wait. If it is merged, Step 4 starts `feat/pledge-withdraw` from a fresh `origin/main`.

## Study checkpoint

- Step 3 handoff: **0.40** projected total reaffirmed, scope v1 with the three refinements above (none widens scope). Stage work 0.12, 0.08, 0.14, 0.06, not summed; Stage 4 owns shared verification and documentation. Baseline `c1572e57`, R1, P2; Claude single exposed rater, 2026-11-16 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
