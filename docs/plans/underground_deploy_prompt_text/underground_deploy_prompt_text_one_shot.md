# Underground header deploy button text

Cycle v3/underground_deploy_prompt_text · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/underground_deploy_prompt_text`; host ledger [underground_deploy_prompt_text.md](../effort_ledger/underground_deploy_prompt_text.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.04**

Status: implemented, PR open; next permitted action: maintainer merge.

## Original request

Claude noticed while verifying [deploy_merged_branch](../deploy_merged_branch/deploy_merged_branch_one_shot.md) that the header Deploy now button in Underground shows a blank blue pill. Ilya (2026-11-27): "Yes, please fix that separately as … a one shot."

## Authorization

Bounded one-shot through an open PR: make the header Deploy now label legible in Underground and Underground Dark. Not authorized: other Underground header styling, deploy behavior.

## Problem

Underground paints the site name with a grain background clipped to the text and `-webkit-text-fill-color: transparent` on the whole `.eyebrow`. The deploy prompt sits inside the eyebrow, so the button inherits the transparent fill; its own background hides the clipped grain and the label vanishes. Computed: color `#f3f2fd` on `#2b2fd6`, text fill transparent.

## Outcome, reuse and risks

One rule in `public/assets/theme-underground.css`, scoped to `.deploy-prompt` in both Underground themes: `-webkit-text-fill-color: currentColor`, so the button keeps the shared active pair from `site.css` and the status text shows in plain theme ink. The brand name keeps its grain effect. Risk: the status loses the grain look, which is intended for a functional message.

## Completion conditions

Headless Chrome with real stylesheets: the button's text fill equals its color in Underground and Underground Dark, the brand name still transparent-filled; screenshot at 375px and 700px. `./v3 test` smoke set passes. Feedback: no matching item. Concepts touched: application update, unchanged.

Starting revision and branch: `5aa786e`, `fix/underground-deploy-prompt-text`. Planning commit: `f1b2fbe`.

## Study history

- 2026-11-27 UTC, intake and entry: **0.04**. Claude, single exposed rater. R1 not reverified this session.
- 2026-11-27 UTC, closeout: retrospective **0.04**. Same rater.

## Outcome and verification

One rule added to `theme-underground.css`. Passed: smoke set (`./v3 test`). Passed, headless Chrome with the real stylesheets and the header prompt injected, at 375px and 700px: the button's text fill now equals its colour (`#f3f2fd` on `#2b2fd6` in Underground, `#0d0e2b` on `#9a9dff` in Underground Dark) and the label renders; the site name keeps its transparent fill and grain. Not verified: on the maintainer's phone. Not run: the full suite. On `main` the long status still widens a phone header; [deploy_merged_branch](../deploy_merged_branch/deploy_merged_branch_one_shot.md) (PR #443) fixes that.

Commit, merge and publication: pushed to `fix/underground-deploy-prompt-text` with an open PR; not merged.

— Claude · 2026-11-27 UTC · Model: claude-opus-5-5
