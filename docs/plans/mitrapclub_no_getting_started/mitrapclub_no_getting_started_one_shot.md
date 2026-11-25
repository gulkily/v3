# No Getting started on mitrapclub

Cycle v3/mitrapclub_no_getting_started · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/mitrapclub_no_getting_started`; host ledger [mitrapclub_no_getting_started.md](../effort_ledger/mitrapclub_no_getting_started.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.05**

Status: implemented, PR open; next permitted action: maintainer merge and deploy to mitrapclub.com.

## Original request

"https://mitrapclub.com/threads/ let's start improving this site. get rid of the getting started button please" and "and start page, we don't need it on this instance" (Ilya, 2026-11-25).

## Authorization

A bounded one-shot through an open PR: remove the Getting started link and the /start/ page on the mitrapclub instance only. Other sites keep both.

## Outcome

- A `gettingStarted` site-profile setting (default true, validated as a boolean), read by `SiteProfileRegistry::gettingStarted()`. The mitrapclub profile sets it to false.
- With it false, /start/ answers 404 and the board shows no Getting started link: not in the toolbar when the board falls back to All, and not in the empty-board state.

## Reuse decisions

- A site-profile key rather than a feature flag: the choice is per instance and ships with the deploy, as `guestbookThread` and `zenStripTags` do. mitrapclub hides the Tools nav, so a flag would be harder for its operator to reach.
- /start/ uses the existing `routeServices()->notFound()`, as /agent/ does when its flag is off.
- Existing toolbar and empty-state templates; no new markup.

## Completion conditions

`./v3 test --smoke BoardListTest` passes, including the new `testSiteWithoutGettingStartedHasNoStartPageOrLinks`. The full suite was not run. Feedback: no matching item in `docs/feedback/items/`. Navigation icons: no nav destination changed.

## Study history

- 2026-11-25 UTC, intake and entry: **0.05**. Claude, single exposed rater. R1 not reverified this session.
- 2026-11-25 UTC, closeout: retrospective **0.05**. Same rater.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*
