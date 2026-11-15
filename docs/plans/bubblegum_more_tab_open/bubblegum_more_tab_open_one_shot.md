# Bubblegum open More tab

Cycle: `v3/bubblegum_more_tab_open`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/bubblegum_more_tab_open`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)
Debug report: [log](bubblegum_more_tab_open_debug_log.md)

**Cycle estimate: 0.05**

Status: planned; next permitted action: implement.

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
Planning commit: pending.

## Dated execution history

- 2026-11-15: cause found by reading `site.css` and `theme-bubblegum.css` against the screenshot.

## Study history

- Intake: **0.05**, exposed rater Claude (claude-opus-5-5), recorded after locating the cause.
