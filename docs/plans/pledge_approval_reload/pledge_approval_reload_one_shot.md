# Pledge approval reload

Cycle: `v3/pledge_approval_reload`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/pledge_approval_reload`; [cycle ledger](../effort_ledger/pledge_approval_reload.md). Standing P2 enrollment; parent `v3/pledge_cards`.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)

**Cycle estimate: 0.10**

Status: technically complete and committed; next permitted action: push and open the PR (authorized).
Debug report: [log](pledge_approval_reload_debug_log.md)

## Original request

2026-11-17, maintainer: "when i approve a user from a 'pending' pill, the awaiting messages on the page don't update until reload. is it expensive to improve that?" We answered with two costs: a reload when it matters (about 0.1) or swapping rows in place (about 0.25). PR #317 made the reload show the right rows. Then, after a list of small polish items that included this one as "the cheap option we priced but haven't picked": "please do the small polish fixes as one-shots". This cycle takes the cheap option.

## Authorization

As in [`pledge_row_byline`](../pledge_row_byline/pledge_row_byline_one_shot.md#authorization): this one-shot, its plan and completion commits, pushing and a PR, on the shared branch `feat/pledge-polish` with one PR. Does not authorize merging or deploying.

## Outcome and work type

Application change, `public/assets/pending_user_summary.js` and its browser test. After a key is approved from a Pending chip on a campaign thread, and that key has a reply on the page (a chip for it inside a post card), the page reloads so the rows and totals show the person as approved. Elsewhere, approving keeps today's behaviour: the chips go and nothing reloads.

Excluded: swapping rows or totals in place; other pages that would also change (lists, profiles).

## Approach and risks

- In `settle(slug)`, before removing the chips: if the page has `[data-pledge-totals]` and a chip for `slug` sits inside an `article`, call `location.reload()` after closing the summary.
- The same applies when the summary finds the key already approved by someone else, since the page is stale then too.
- `AGENTS.md`: post-paint changes only for real state changes. An approval is one, and the reload shows a page that is final on first paint.
- Risk: before #317 was deployed, the reloaded page could still be the old saved copy. #317 is merged, so deployments now carry both.

## Reuse decisions

Existing `settle`, chip markup (`data-pending-profile`) and the totals marker; no new attributes or endpoints.

## Completion conditions

On a campaign thread, approve a waiting pledger from their chip: the page reloads and their row counts. On a list page, approving removes chips without a reload.

## Concepts touched

[Pledge campaign](../../concepts/pledge_campaign.md): changed (Counting entry: what a viewer sees after approving).

## Verification and recovery plan

`tests/browser/pending_chip_approval.mjs`: the existing page (no totals) loads once; a new campaign-like page with a chip inside a post card reloads after Approve. Smoke set plus `PledgeCampaignTest` for the branch. Full suite not run, per `AGENTS.md`.

Starting revisions and branch: `feat/pledge-polish` at `68c99378` (after `pledge_more_flat`).
Planning commit: `a85a4753` (plan and index link only; confirmed with `git log`).

## Dated execution history

- 2026-11-17: plan written.
- 2026-11-17: `settle` collects the key's chips, decides whether counts change (totals on the page and a chip inside an `article`), removes the chips, closes the summary and reloads when they change. The header comment says so.
- 2026-11-17: `pending_chip_approval.mjs` counts page loads: the existing list page still loads once after Approve; a new campaign page (totals and a chip inside a pledge row) loads twice. The new case timed out waiting for the reload with the base script.

## Study history

- Intake: **0.10**, exposed rater Claude (claude-opus-5-5), after reading `pending_user_summary.js` and its browser test (no pristine intake).

- Execution entry: reaffirmed 0.10.
- Closeout retrospective: **0.10**. Not hours or measured labor; maintainer acceptance pending.

## Outcome

Delivered as bounded. Approving a waiting pledger from their chip on a campaign thread closes the summary and reloads; with #317 the reloaded page shows their pledge counted. On other pages the chips go and nothing reloads.

Limitations: the reload also happens when the key's only reply on the page is not a pledge (a question), where nothing visible changes; cheap and harmless. Approving from the profile page or the tools page does not touch an open campaign tab.

## Verification

- `tests/browser/pending_chip_approval.mjs` (Chrome headless, mocked server): passes; the new reload case failed with the base script.
- `./v3 test --smoke PledgeCampaignTest` for the branch: 506 run, 506 passed. Full suite not run, per `AGENTS.md`. Not checked: a real approval on zenmemes.com after deploy.

## Final review

Diff checked: `pending_user_summary.js`, its browser test, the concept's Counting entry and sources, this record, its debug log, the plans index, the checklist and the cycle ledger.

## Commit/merge/publication status

Completion commit: `5b6d21c5` (confirmed with `git log`). Pushed with a PR on `feat/pledge-polish`; not merged or deployed.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
