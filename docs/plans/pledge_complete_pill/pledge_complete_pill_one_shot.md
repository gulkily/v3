# Pledge Complete pill

Cycle: `v3/pledge_complete_pill`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/pledge_complete_pill`; [cycle ledger](../effort_ledger/pledge_complete_pill.md). Standing P2 enrollment; follow-up to `v3/pledge_board_rows` (PR #320, merged).
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)

**Cycle estimate: 0.10**

Status: planned; next permitted action: implement and verify.

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
Planning commit: pending.

## Dated execution history

- 2026-11-17: defect measured as above; plan written.

## Study history

- Intake: **0.10**, exposed rater Claude (claude-opus-5-5), after measuring the pill in 17 themes and reading the Complete rule (no pristine intake).

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
