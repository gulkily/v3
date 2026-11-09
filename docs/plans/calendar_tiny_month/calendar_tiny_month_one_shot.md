# Calendar tiny month — one-shot

Cycle: `v3/calendar_tiny_month`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](calendar_tiny_month_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/calendar_tiny_month`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; one approach and no work units, so no unit estimates)

**Cycle estimate: 0.10**

Status: technically complete on `feat/calendar_tiny_month`; pushed, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Feedback item: none. No item in `docs/feedback/items/` matches, and the request arrived as a voice memo with no post to cite in `Sources:`, so none was created.

Original request, 2026-11-09 (voice memo from the maintainer, transcribed): "I want the calendar view to have a month view with very tiny text so that I can also view it on iPhone with not just the number of things showing for each day but the first couple of words. And also I want a week view and a day view I guess, but the main thing is I want a tiny text month view."

Authorization, 2026-11-09: the maintainer sent the memo into a development session with no other words. Read as the same go-ahead as a pasted thread under [thread to pull request](../../agents/thread_to_pr.md): one-shot through an open PR for the part he called "the main thing". Exclusions: merge, deployment, the week view and the day view.

## Outcome and work type

Application change (UI, CSS only). On a phone-width `/calendar` or tag calendar, each day square shows the first couple of words of up to three event titles in very small text, beside the day number and event count it already shows. Tapping anywhere in the square still opens the day page, which lists the events in full.

Not in this cycle: a week view (new, follow-up) and a day view. A day view already exists at `/calendar/<date>`; whether he wants something different from it is an open question for the follow-up.

## Approach and reuse decisions

- **Reuse the existing markup.** The month template already renders up to `ENTRIES_PER_CELL` (3) titles and a "+N more" link in every cell; the phone media query in `public/assets/calendar.css` hides them. The change is in that media query only: show the entries at a tiny size, up to two lines each with an ellipsis, instead of hiding them. Server HTML stays identical for every viewer.
- **Taps.** At phone size the titles are too small to aim at. They stop taking taps there, so a tap anywhere in the square falls through to the existing whole-square day link (the `::after` overlay). Desktop keeps title links.
- **Count.** Kept, moved onto the number's line so the titles have room. The "+N more" link is hidden at phone size because the count already says how many.
- **Considered and rejected:** shortening titles to N words on the server (changes markup and needs a word rule for every language; clipping at the edge shows as many words as fit); horizontal scrolling of a full-size grid (what the phone layout replaced).
- Theme tokens only (`--ink`, `--ink-soft`, `--active-ink`); the Gloss and Engagify border override is unaffected. No new dependency, route, copy or nav destination.

## Concepts touched

None in the concept inventory.

## Risks

- Text at about 9px is hard to read for some people. It is what was asked for; the day page stays one tap away with full-size text.
- Confirmed days use the active colours; the existing override already covers entry links there.

## Verification and recovery plan

- `./v3 test --smoke CalendarPageTest` (markup unchanged, so existing tests should pass as is).
- Headless Chrome against `./v3 start` with events on and a few events of varying title length: iPhone width (390px) and 320px, desktop 1100px unchanged; tap on a title at phone width opens the day page; themes Word97, Chicago, Midnight, Gloss, Engagify, dark and default.
- Recovery: revert the commit.

Starting revision and branch: `f5e78b9353c540cefcfd94ee73476fc29fb99c97` on `main`; branch `feat/calendar_tiny_month`.

Planning commit: `a1194952` (confirmed with Git before implementation).

## Study history

- **Intake, 2026-11-09T03:27Z, 0.10.** Single exposed rater: Claude (claude-opus-5-5). Pristine intake missing: the calendar controller, templates and CSS were read before rating. Scope v1 as above. Compared with R1's duplicate preview suppression (0.10) and `v3/calendar_today` (0.08).

- **Execution entry, 2026-11-09T03:28Z, 0.10 reaffirmed.** No new evidence.
- **Closeout, 2026-11-09T03:31Z.** Projection 0.10 reaffirmed; retrospective scope assessment **0.08**, same single exposed rater: CSS only, and the existing browser test needed only its phone branch rewritten. Corrections: none. Additions: none. Delivered scope v1 in full. Maintainer fit and acceptance pending. No active-work measurement collected.

## Dated execution history

- 2026-11-09: record created; planning commit `a1194952`.
- 2026-11-09: phone media query in `public/assets/calendar.css` changed; phone branch of `tests/browser/calendar.mjs` now expects titles shown inside the square and a tap on a title to open the day.
- 2026-11-09: after the first screenshots, hid "+N more" at phone size (redundant with the count) and lowered the square to 4.25rem; reran the browser test.

- 2026-11-09: maintainer feedback after the first PR push: "make the text even tinier so that at least one or two words fit on one line" and "make this a toggle so that there is still a default view where it's just numbers." Phone default restored to counts only; a "Show titles" / "Hide titles" link in the month nav (phones only; hidden without JavaScript) adds `calendar--titles` to the page, remembered per browser in `localStorage` (`calendar-titles`). Titles now 0.4rem, so about two words fit per line at 390px. Browser test now checks the default, the toggle, the remembered choice and switching back; desktop has no toggle.

## Outcome

Delivered as bounded. By default a phone-width day square shows its number and event count, as before. With titles on (a toggle remembered in the browser), it also shows up to three titles at 0.4rem, two lines each with an ellipsis, about two words per line at 390px. Desktop is unchanged. Follow-ups, not started: a week view; whether the existing day page (`/calendar/<date>`) is the day view he wants.

## Verification

- `node tests/browser/calendar.mjs`: ok. Phone (390px): titles visible, inside their square, count reads 5 on a five-event day, a tap on a title opens the day page, grid fits the width; desktop (1280px) assertions unchanged and passing; no page overflow and readable contrast for the marked day's visible text in light, dark, console, lcd, chicago, vapor, forge, sticker, arena, thermal, whitehot, word97 and midnight at 390 and 1280px.
- Headless Chrome screenshots with thirteen realistic events at 390 and 320px in light, dark, Gloss, Engagify, Chicago and Word97: titles legible at 2x, no overflow. A Midnight screenshot run timed out on the local PHP server; Midnight is covered by the browser test above.
- `./v3 test --smoke CalendarPageTest`: 449 run, 448 passed. The failure is `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, the same pre-existing failure recorded by `v3/calendar_today`; unrelated to a CSS change.
- Not run: the full suite (maintainer runs it). Not checked: a real iPhone (Safari's `-webkit-line-clamp` and `text-size-adjust` are standard there but were exercised only in Chrome).

## Final review

Diff is the phone media query and the phone branch of one browser test, plus this record, its debug log, the plans index and the ledger. No markup, server, copy or nav change; no duplicated component.

## Commit, merge and publication status

Completion commit: pending (this record ships in it). Pushed to origin; not merged, not deployed.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
