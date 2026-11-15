# Bubblegum open More tab

Cycle: `v3/bubblegum_more_tab_open`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/bubblegum_more_tab_open`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)
Debug report: [log](bubblegum_more_tab_open_debug_log.md)

**Cycle estimate: 0.05**

Status: technically complete and committed; next permitted action: push, open the PR, reply in the thread.

## Original request

Thread: [Small bug display on iPhone](https://zenmemes.com/threads/thread-20261115190951-97b38943), maintainer (ilyag), 2026-11-15, root post is a screenshot only. The screenshot shows Bubblegum on a phone with the More panel open: the More tab label in the bottom tab bar is white on the pale wavy tab bar. Feedback item: PF-20261115-B01.

## Authorization

Maintainer pasted the thread link into a Claude session on 2026-11-15, then wrote "Pr please". Under `docs/agents/thread_to_pr.md` this authorizes one-shot execution of the bounded outcome, pushing the branch, opening the PR and replying in the thread. It does not authorize merging, deploying or production access.

## Outcome and work type

Application change, Bubblegum stylesheet only. On phones, the open More tab takes the same selected look as the selected tab bar tabs (berry background, white label, on-pink shine). Excluded: other themes; the More panel itself; desktop.

## Approach and risks

- Cause: `site.css` gives the open trigger `background: var(--active-bg); color: var(--active-ink)` at specificity (0,5,0). Bubblegum's phone tab bar rule (`:root[data-theme="bubblegum"] [data-shell="app"] .site-header--app .account-menu__trigger`, also (0,5,0), later in the cascade) resets the background to transparent but sets no colour, so the label stays white on a transparent tab.
- Fix: add `.account-menu[open] > .account-menu__trigger` to Bubblegum's existing phone selected-tab rule (as Gloss and Midnight already do), and to the existing on-pink shine list so it matches the selected tabs.
- Risk: low; one theme file, no markup or behaviour.

## Reuse decisions

Existing Bubblegum selected-tab rule and on-pink shine list in `public/assets/theme-bubblegum.css`; pattern from `theme-gloss.css` and `theme-midnight.css` open-trigger rules. No new classes or files.

## Completion conditions

- Normal entry: Bubblegum, phone width, tap More.
- End-to-end: the More tab shows berry background with white label while open; closed state unchanged.
- Release: PR merged and deployed by the maintainer.

## Concepts touched

None.

## Verification and recovery plan

Render the open and closed More tab at 375px in headless Chrome with the repo stylesheets if a browser is available; otherwise check computed specificity by inspection and say rendering was not done. Smoke set plus `ThemeRegistryTest` and `NavIconSetsTest`. Full suite not run, per `AGENTS.md`.

Starting revisions and branch: `fix/bubblegum-more-tab-open` from `ec70987d5f05f8d4bfa0266da929a0ea7dbcaa8b`.
Planning commit: `eb726578` (plan, index link, feedback item only; confirmed with `git log`). Its feedback item used invalid `Workflow`/`Evidence` values (`open`, `none`); corrected in the completion commit.

## Dated execution history

- 2026-11-15: cause found by reading `site.css` and `theme-bubblegum.css` against the screenshot.
- 2026-11-15: planning commit `eb726578`, then implemented: open trigger added to Bubblegum's phone selected-tab rule. Correction to the plan: not added to the on-pink shine list, because that list is not phone-scoped (it would reach the desktop You button when open) and the phone selected-tab rule's `background` shorthand already drops the shine on the other selected tabs, so the open More tab now matches them exactly.

## Study history

- Intake: **0.05**, exposed rater Claude (claude-opus-5-5), recorded after locating the cause (no pristine intake). Execution entry reaffirmed 0.05; closeout retrospective **0.05**. Not hours or measured labor; maintainer acceptance pending.

## Outcome

Delivered as bounded: in Bubblegum on phones, the open More tab shows the berry selected look with a white label. Closed state, desktop and other themes unchanged. While More is open on a page that has its own selected tab (e.g. Board), both tabs show selected, as in every other theme (shared `site.css` behaviour).

## Verification

- Replayed the report in headless Chrome (Playwright, 375x667 at 2x) against a live snapshot of `/` with this branch's stylesheets and Bubblegum applied: before, open More label `rgb(255,255,255)` on transparent (matches the screenshot); after, white on `rgb(184,40,108)`. Closed state unchanged (berry label on transparent) in both.
- `./v3 test --smoke ThemeRegistryTest NavIconSetsTest`: 466 run, 466 passed.
- `./v3 feedback check`: no problems after correcting the item; `./v3 feedback index` regenerated.
- Full suite not run, per `AGENTS.md`. Not checked: iOS Safari itself, logged-in More panel contents (unchanged files), live site after deploy.

## Final review

Diff is one selector added to an existing rule, plus this record, its index line, the feedback item, the regenerated feedback index and the ledger/debug entries. No new rules or duplication.

## Commit/merge/publication status

Planning commit `eb726578`; completion commit is the one containing this text (hash pending, reported in the PR). Not merged or deployed.

## Attribution

Claude (claude-opus-5-5), 2026-11-15.
