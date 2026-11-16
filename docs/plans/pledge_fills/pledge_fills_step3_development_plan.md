> **Feature plan:** [Step 1](./pledge_fills_step1_solution_assessment.md) · [Step 2](./pledge_fills_step2_feature_description.md) · [Step 3](./pledge_fills_step3_development_plan.md) · [Step 4](./pledge_fills_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_fills_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_fills`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_withdraw`.

Extended estimates: enabled

Status: Steps 1 (Option B) and 2 approved by the maintainer 2026-11-16. Step 3 written 2026-11-16, awaiting `Approved Step 3`.

**Cycle estimate: 0.50**

# Step 3: Development Plan — Pledge fills

## Completion Contract

- Entry: "Mark delivered" (the pledger) or "Mark filled" (the author or an operator) on a pledge on the campaign thread page; or a typed reply to the pledge with `filled` or `filled: 200`.
- End-to-end outcome: the pledge shows "Delivered by mara" or "Filled by ilyag" with the amount, linked to the reply. The block shows "N filled", plus "M confirmed" when part is pledger-reported. A bare fill reply adds no card.
- Recovery: a failed press reports on the pledge and can be pressed again. Retracting a fill reply undoes it. Turning `FORUM_PLEDGES_ENABLED` off returns every line to plain text.
- Deployment: no schema change, no rebuild, no new flag, no new dependency.
- Release condition: with the flag on at zenmemes.com, Ilya pledges from his iPhone SE, marks it delivered, sees "filled" change, then retracts the fill. Not possible from this session; it stays pending in Step 4.

## Findings from the checks Step 2 asked for

- **Operator cost is small.** `ApprovalPolicy::isActiveOperator()` reads one approval seed file and one retirement file per key. The reader asks once per distinct fill author, through a check passed in by the thread page.
- **The buttons follow Withdraw.** `pledge_amount.php` already compares the viewer from the identity hint with the pledge's author. "Mark filled" compares it with the opening post's author, or asks the same operator check. A baked page with no viewer shows no button. The read rule is the real guard.
- **Operator means operator now.** A fill by a key that later stops being an operator stops counting. That matches Step 2's "an operator at the time the thread is shown".

## Refinements to Step 2, within its scope

- **Fills are read before withdrawals, so order does not matter.** Each pledge's fills add up in thread order, capped at its amount. A withdrawal takes effect only when the pledge is not fully filled. A withdrawn pledge counts in "pledged" as its filled amount. Retracting any reply gives the same result as if it never existed.
- **One button per pledge.** The pledger sees "Mark delivered"; the author or an operator sees "Mark filled". Neither shows on a fully filled, withdrawn or uncounted pledge. An author's own pledge gets "Mark delivered".

## Key Risks

- **High risk: totals disagree with the pledges shown.** Impact: readers stop trusting the block. Early validation: Stage 1 tests fill then withdraw, withdraw then fill, over-fill, and retracting each reply. Mitigation: one reader computes pledged, filled and confirmed from the same pass.
- **Folding hides a reply that matters.** Impact: a note, an answer or a reason disappears. Early validation: Stage 3 tests bare, noted, answered and uncounted fill replies. Mitigation: reuse the withdraw fold rule unchanged.
- **The second button breaks the first.** Impact: Withdraw stops working. Early validation: Stage 4 runs the existing withdraw browser check before adding the fill press. Mitigation: extend the script by a role, not a rewrite.

## Stage 1

**Estimate: 0.14**

- Goal: fill replies are read and counted; withdrawal respects fills.
- Dependencies: none.
- Expected changes: new `Support/FilledDirective` with `extract(string $body): ?string` (text after `filled`, empty when bare, null when absent; quoted lines skipped) and `isBare()`. `PledgeCampaign::fromPosts()` gains an optional operator check, `?callable $isOperator`, and a private `fills()` run before `withdrawals()`. Each pledge entry gains `filled` and a list of fills (reply ID, who, amount, counted amount). The result gains `filled`, `confirmed` and `fills`, keyed by reply ID: effective or a reason. Reasons: not a reply to a counted pledge, not the pledger, author or an operator, no name, unverified, not a number or unit, already filled. Withdrawal gains "already filled". "Pledged" counts a withdrawn pledge as its filled amount.
- Verification approach: `PledgeCampaignTest` for pledger, author and operator fills; another key; partial and over-fill ("Counted 100 of 150"); two fills adding up; `filled: 2 coffees` and `filled: lots`; fill then withdraw and withdraw then fill; fully filled withdraw; retracting a fill; flag off. Smoke set alongside.
- Estimate details: driver: the reasons table and its order; uncertainty: little.
- Risks or open questions:
  - Impact: totals disagree. Early warning: the order tests. Mitigation: fills first, one pass.
- Canonical components/API contracts touched: `PledgeCampaign.php` (extended); `WithdrawDirective.php` and `PledgeAmount.php` (pattern and parser reused); `ApprovalPolicy::isActiveOperator()` (used unchanged).

## Stage 2

**Estimate: 0.08**

- Goal: readers see fills on each pledge and in the block.
- Dependencies: Stage 1.
- Expected changes: `ThreadAndPostPageController` passes the operator check. `pledge_amount.php` lists each fill ("Delivered by mara" or "Filled by ilyag", amount when partial, "Counted 100 of 150" when capped), linked to its reply, and shows a fill reply's reason. `pledge_totals.php` adds "N filled" and, when lower, "M confirmed". One style rule in `site.css` if needed.
- Verification approach: rendering tests in `PledgeCampaignTest` for both labels, the cap note, a reason, and the block. A baked page before and after a fill.
- Estimate details: driver: label wording; uncertainty: the author's display name.
- Risks or open questions:
  - Impact: a long fill list crowds the card. Early warning: three fills on one pledge. Mitigation: one line per fill, plain text.
- Canonical components/API contracts touched: `pledge_amount.php` and `pledge_totals.php` (extended); no second badge component.

## Stage 3

**Estimate: 0.05**

- Goal: a bare fill reply that took effect leaves the conversation.
- Dependencies: Stage 1.
- Expected changes: `WithdrawLeafFilter::apply()` gains the fills map and folds a bare `filled` reply by the same rule. `thread.php` passes it. The class keeps its name; its comment names both lines.
- Verification approach: `WithdrawLeafFilterTest` for bare, noted, answered and uncounted fill replies, and a withdraw reply answered by a fill reply.
- Estimate details: driver: none; uncertainty: little.
- Risks or open questions:
  - Impact: a fold hides a reason. Early warning: the uncounted test. Mitigation: only effective replies fold.
- Canonical components/API contracts touched: `WithdrawLeafFilter.php` and `thread.php` (extended, not copied).

## Stage 4

**Estimate: 0.12**

- Goal: one press marks a pledge delivered or filled.
- Dependencies: Stage 2.
- Expected changes: `pledge_amount.php` draws a fill form beside Withdraw, with the body `filled` and the label from the refinement above. The controller tells the template whether the viewer is an operator. `pledge_withdraw.js` handles both forms by role, with the failure message taken from the form.
- Verification approach: rendering tests for who sees which button and where none shows. `tests/browser/pledge_campaign.mjs`: the withdraw press still works; "Mark delivered" changes the label and the block with no new card; a forced failure shows the message and a second press works. If no browser runs in the session, the browser check stays pending.
- Estimate details: driver: three viewer rules; uncertainty: the operator flag on baked and high-traffic pages.
- Risks or open questions:
  - Impact: Withdraw breaks. Early warning: run its check first. Mitigation: role-based extension.
- Canonical components/API contracts touched: `pledge_withdraw.js` (extended); Outbox prepare and finalize calls and `/compose/reply` (used unchanged).

## Stage 5

**Estimate: 0.06**

- Goal: checked in every theme and documented.
- Dependencies: Stages 3 and 4.
- Expected changes: the [pledge campaign](../../concepts/pledge_campaign.md) entry gains fills; the spec notes fills read before withdrawals; the checklist, ledger and debug log; the Step 4 closeout.
- Verification approach: the browser check in each theme at 375 and 1280 pixels; smoke set with `PledgeCampaignTest`, `WithdrawLeafFilterTest` and `ThreadActivityTest`; `git diff --check`; review the whole diff. The full suite is not run.
- Estimate details: driver: 17 themes; uncertainty: browser availability.
- Risks or open questions:
  - Impact: a theme check cannot run. Early warning: no browser binary. Mitigation: report it unverified.
- Canonical components/API contracts touched: concept inventory (extended).

Concepts touched: pledge campaign, changed; thread activity, post retraction and operator grant, used unchanged.

## Branch

`plan/pledge-fills` was rebased onto `origin/main` at `c98b5221` on 2026-11-16 (two unrelated PRs landed). The rebase is local; the remote branch still holds the old base. After `Approved Step 3`, Step 4 cuts `feat/pledge-fills` from a fresh `origin/main`.

## Study checkpoint

- Step 3 handoff: **0.50** reaffirmed, scope v1 with the two refinements above (neither widens scope). Stage work 0.14, 0.08, 0.05, 0.12, 0.06, not summed; Stage 5 owns shared verification and documentation. Baseline `c98b5221`, R1, P2; Claude single exposed rater, 2026-11-16 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
