# Pledge Complete pill

Cycle: `v3/pledge_complete_pill`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/pledge_complete_pill`; [cycle ledger](../effort_ledger/pledge_complete_pill.md). Standing P2 enrollment; follow-up to `v3/pledge_board_rows` (PR #320, merged).
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)

**Cycle estimate: 0.10**

Status: technically complete and committed; next permitted action: push and open the PR (authorized).
Debug report: [log](pledge_complete_pill_debug_log.md)

## Original request

2026-11-17, maintainer: "please review the styling of the complete pill, it looks off in some themes (dark text on dark background or similar)"

## Authorization

The maintainer asked for the pill's styling to be reviewed and, by naming the defect, fixed. That is a bounded fix with a clear outcome, so it runs as a one-shot. PR #320 merged, so it uses a new branch from `origin/main` and a new PR, as `AGENTS.md` requires. Authorized: this record, its planning and completion commits, pushing and a PR. Not authorized: merging or deploying. Excluded: the wording, the "Goal reached" pill (it passes), and other components that use `--status-ok` (`.chip--approved`).

## Measured defect

A "Complete" campaign and a "Goal reached" campaign were measured in all 17 themes, on the thread page and the board. The measurement composites each pill's colours over its ancestors and computes the WCAG contrast ratio.

- "Complete" text against its own background: Bubblegum 1.12, Spearmint 1.35, Word97 2.01, Arena 2.32, Sticker 2.73, Whitehot 4.29. All six are below 4.5; the first five are close to unreadable.
- "Goal reached": 5.19 or higher in every theme.
- Cause: the Complete rule in `site.css` sets the text to `--status-ok` but keeps the pill's `--active-bg` fill. Each theme picks `--status-ok` to read on its page and panel colours, not on its active fill. The fill is dark pink in Bubblegum, teal in Spearmint and navy in Word97.

## Outcome and work type

Application change, CSS only. The "Complete" pill keeps the theme's own text and fill pair (`--active-ink` on `--active-bg`), as "Goal reached" does, so it reads in every theme. Its border takes `--status-ok`, so Complete still looks finished without depending on that colour for the text.

## Approach and risks

- One rule in `public/assets/site.css` for `.pledge-totals` and `.thread-row__pledge`: drop the text colour change, keep the border colour.
- Risk: Complete and Goal reached look more alike. Mitigation: the word differs, and the border marks Complete. A stronger mark is a separate design question for the maintainer.
- Risk: a theme overrides `.pledge-status`. No theme file mentions it (checked with `grep`).

## Reuse decisions

The theme's existing active pair (`--active-bg`, `--active-ink`), which every theme defines for selected controls, and `--status-ok` for the border only. No new variables or classes.

## Completion conditions

On a "Complete" campaign's thread page and board row, the pill's text reaches 4.5:1 against its background in all 17 themes, at 375 and 1280 px.

## Concepts touched

[Pledge campaign](../../concepts/pledge_campaign.md): used unchanged. It does not describe the pill's colours.

## Verification and recovery plan

`tests/browser/pledge_campaign.mjs` gains a contrast check for every status pill on the thread page and the board, in each theme. Its long-unit campaign is made Complete (a pledge of 40, marked delivered), so both statuses are covered. The scratch measurement is rerun for the before-and-after table. Smoke set run once. Full suite not run, per `AGENTS.md`. No user-facing recovery applies to a colour.

Starting revisions and branch: `fix/pledge-complete-pill` from `origin/main` at `d27730e6` (#320 merged).
Planning commit: `d91d6f1d` (plan and index link only; confirmed with `git log`).

## Dated execution history

- 2026-11-17: defect measured as above; plan written.
- 2026-11-17: the Complete rule in `site.css` keeps only `border-color: var(--status-ok)`; the text colour change is gone, with a comment saying why.
- 2026-11-17: the browser check's long-unit campaign gets a pledge of 40 from the writer, marked delivered, so it reads "Complete". A `pillContrast()` helper measures every status pill on the thread page and the board in each theme and requires 4.5:1. The board must show both words.
- 2026-11-17, correction during verification: the first run's `goto` after "Mark delivered" collided with the page's own reload; the step now waits for the page to settle first.

## Study history

- Intake: **0.10**, exposed rater Claude (claude-opus-5-5), after measuring the pill in 17 themes and reading the Complete rule (no pristine intake).

- Execution entry: reaffirmed 0.10.
- Closeout retrospective: **0.10**. Not hours or measured labor; maintainer acceptance pending.

## Outcome

Delivered as bounded. "Complete" now uses the theme's own text and fill, like "Goal reached", with a `--status-ok` border. Its contrast is now 5.19 at the lowest (Spearmint), up from 1.12 at the lowest (Bubblegum). Complete and Goal reached now differ by word and border colour only; a stronger visual difference is a design question left to the maintainer.

## Verification

- `tests/browser/pledge_campaign.mjs` with the old CSS stashed: failed at Sticker 375 px, board "Complete" contrast 2.73. With the fix: the whole flow and 17 themes at 375 and 1280 px pass, including contrast for both pills on the thread page and the board.
- Scratch measurement, outside the repository, of all 17 themes after the fix: every "Complete" pill is between 5.19 (Spearmint) and 21.00 (Arena). "Goal reached" is unchanged.
- Screenshots checked by eye at 375 px in Bubblegum, Spearmint, Word97, Arena and Sticker.
- `./v3 test --smoke PledgeCampaignTest`: 509 run, 509 passed. Full suite not run, per `AGENTS.md`.
- Inapplicable: no PHP or template changed; the release check is the maintainer viewing a Complete campaign on zenmemes.com, pending.

## Final review

Diff checked: one CSS rule and its comment, the browser check, this record, its debug log, the plans index and the cycle ledger. `git diff --check` clean.

## Commit/merge/publication status

Completion commit: pending at writing; reported in the handoff. Not merged or deployed.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
