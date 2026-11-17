# Pledge row byline

Cycle: `v3/pledge_row_byline`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/pledge_row_byline`; [cycle ledger](../effort_ledger/pledge_row_byline.md). Standing P2 enrollment; parent `v3/pledge_cards`.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)

**Cycle estimate: 0.10**

Status: technically complete and committed; next permitted action: the sibling polish cycles, then push and open the PR (authorized).
Debug report: [log](pledge_row_byline_debug_log.md)

## Original request

2026-11-17, maintainer, after a list of small polish items left by cycle 10 (`pledge_cards`): "please do the small polish fixes as one-shots". This cycle is the first item: an opened pledge row shows the pledger's name twice, once in the row and again in the byline under it.

## Authorization

The request above; earlier in the session the maintainer authorized making PRs. Authorizes this one-shot, its plan and completion commits, pushing and a PR. Shares the branch `feat/pledge-polish` with the two sibling polish cycles ([`pledge_more_flat`](../pledge_more_flat/pledge_more_flat_one_shot.md), [`pledge_approval_reload`](../pledge_approval_reload/pledge_approval_reload_one_shot.md)) and one PR, so the plans index and checklist do not conflict across three PRs. Does not authorize merging or deploying.

## Outcome and work type

Application change. In an opened pledge row, the byline under the row leaves out the name: it shows the date, and the Pending chip when the pledger is not approved. Other replies on a campaign thread keep the name in their byline.

Excluded: the row line itself; list pages that use the same short byline.

## Approach and risks

- `$rowMeta` takes an optional third argument, `withAuthor` (default true). `post_card.php` passes false for a pledge row.
- Risk: the name in the byline was the only link to the pledger's profile inside the row. Accepted: the Pending chip still links to the profile for anyone not approved, and the row name is plain so a tap opens the row.

## Reuse decisions

Existing `renderRowMeta` and its date and chip; no new markup.

## Completion conditions

Open a pledge row on a campaign thread: the name appears once; the date and any Pending chip remain.

## Concepts touched

[Pledge campaign](../../concepts/pledge_campaign.md): changed (Rows entry).

## Verification and recovery plan

`PledgeCampaignTest` check on an opened row's byline; the browser check across 17 themes. Smoke set plus `PledgeCampaignTest`. Full suite not run, per `AGENTS.md`.

Starting revisions and branch: `feat/pledge-polish` from `d33114d2`.
Planning commit: `826e5df6` (plan and index link only; confirmed with `git log`).

## Dated execution history

- 2026-11-17: plan written.
- 2026-11-17: `$rowMeta` takes `withAuthor` (default true); `renderRowMeta` leaves the author out when false. `post_card.php` passes `!$isPledgeRow`. `PledgeCampaignTest::testEachPledgeIsOneClosedRowAndRowsNeverJoinARun` now checks that a row's byline starts with its date and a question's does not.

## Study history

- Intake: **0.10**, exposed rater Claude (claude-opus-5-5), after reading `post_card.php`, `pledge_amount.php` and `renderRowMeta` (no pristine intake).

- Execution entry: reaffirmed 0.10.
- Closeout retrospective: **0.10**. Not hours or measured labor; maintainer acceptance pending.

## Outcome

Delivered as bounded. An opened pledge row reads "guest  300 Delivered", then "Nov 17", then the note; the name appears once. Other replies on the thread keep "guest · Nov 17".

Limitation: inside an opened row there is no link to an approved pledger's profile.

## Verification

- `./v3 test PledgeCampaignTest`: 28 passed; the new check failed with the base `post_card.php` (27 of 28).
- `tests/browser/pledge_campaign.mjs`: whole flow and 17 themes at 375 and 1280 pixels passed; the Light phone screenshot of opened rows looked at by the agent.
- Smoke set run once for the branch at the end (see [`pledge_approval_reload`](../pledge_approval_reload/pledge_approval_reload_one_shot.md#verification)). Full suite not run, per `AGENTS.md`.

## Final review

Diff checked: `TemplateRenderer.php`, `post_card.php`, one test, the concept's Rows entry, this record, its debug log, the plans index and the cycle ledger. Other `$rowMeta` callers unchanged (default keeps the author).

## Commit/merge/publication status

Completion commit: `2039ac65` (confirmed with `git log`). Pushed with a PR on `feat/pledge-polish`; not merged or deployed.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
