# Compact deploy history

Cycle v3/deploy_history_compact · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/deploy_history_compact`; host ledger [deploy_history_compact.md](../effort_ledger/deploy_history_compact.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.10**

Status: implemented, PR open; next permitted action: maintainer merge and a look on the phone.

## Original request

"can you please tighten up the frontend on the deployment history list" (Ilya, 2026-11-22, after merging PR #367, [deploy_rollback](../deploy_rollback/deploy_rollback_step4_implementation_summary.md)).

## Authorization

A bounded one-shot through an open PR: the History section's markup and styles on Tools → Deploy. Not authorized: changes to what is recorded, to the rollback behavior, or to the confirmation.

## Problem

On a phone each version takes four to six lines: a subject that wraps, a full ISO timestamp, and a button on its own line. With 20 versions the section is several screens long, and most subjects begin with the same words ("Merge pull request #… from gulkily/…").

## Outcome and work type

UI change, phone first.

- One version per row: commit and subject on one line, cut with an ellipsis when too long; a short second line with the time; Roll back as a small button at the row's right edge.
- Merge subjects lose their repeated opening: "Merge pull request #363 from gulkily/state-button-verbs" shows as "#363 state-button-verbs". The full subject stays in the row's `title`.
- Times show as `2026-11-22 22:59` (UTC, as elsewhere on the page). "deployed" is dropped as the default; "rolled back", "running" and "before the first recorded deploy" stay.
- The five newest versions show; older ones sit under an "N earlier" disclosure, which works without JavaScript.
- Thin separators between rows, as in the facts table.

## Risks

- **The small button is hard to tap.** Mitigate by keeping its height at 2rem or more and checking at 380px.
- **A theme restyles buttons or lists.** Mitigate by checking Gloss, Gloss Dark, Word97 and Bubblegum, and Gloss with JavaScript off, with the page's real stylesheets loaded.

## Reuse decisions

- `templates/pages/deploy.php` and `.deploy-history` in `public/assets/tool-details.css`, changed in place. The row is rendered by one closure used for both the visible rows and the disclosed ones.
- Separator color and spacing from `.codebase-facts`; the disclosure follows `.deploy-incoming`.
- No script, no new component, no controller or `AppUpdate` change.

## Completion conditions

`./v3 test --smoke DeployPageTest` passes, with the page test covering the shortened subject, the time format and the disclosure. Screenshots at 380px in the four themes and with JavaScript off. PR open. Feedback: no matching item in `docs/feedback/items/`. Concepts touched: [application update](../../concepts/application_update.md), unchanged.

Starting revision and branch: `e2c112ca`, `fix/deploy-history-compact`. Planning commit: `e639e335`.

## Study history

- 2026-11-22 UTC, intake and entry: **0.10**. Claude, single exposed rater. R1 not reverified this session.
- 2026-11-22 UTC, closeout: retrospective **0.10**. Same rater.

## Outcome

Each version is one row of two short lines, with Roll back as a 32px-high button at the right edge. Merge subjects show as "#363 state-button-verbs"; times as `2026-11-22 22:59`; "running" is bold. The five newest rows show and the rest sit under "N earlier". At 380px a row is about 60px high, down from 130 to 180px. Only `templates/pages/deploy.php`, `tool-details.css` and the page test changed.

## Verification

- Passed: `./v3 test --smoke DeployPageTest`. The page test now builds eight versions and checks the shortened subject and its `title`, the time format, the position of the "3 earlier" disclosure, and the button counts before and after a rollback.
- Passed, headless Chrome at 380px with the real stylesheets: Gloss, Gloss Dark, Word97, Bubblegum, and Gloss with JavaScript off. Nothing overflows; the button is 80 to 112px wide and 32px high in each.
- Not verified: on the maintainer's phone, on production. Not run: the full suite.

## Final review

Diff: the template's History section, `.deploy-history` styles, the page test, this record, the index, the ledger and the debug log. No script, controller or `AppUpdate` change.

Commit, merge and publication: pushed to `fix/deploy-history-compact` with an open PR; not merged.

— Claude · 2026-11-22 UTC · Model: claude-fable-5-2
