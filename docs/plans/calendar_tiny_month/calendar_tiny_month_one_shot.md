# Calendar tiny month — one-shot

Cycle: `v3/calendar_tiny_month`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](calendar_tiny_month_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/calendar_tiny_month`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; one approach and no work units, so no unit estimates)

**Cycle estimate: 0.10**

Status: planning; next permitted action: planning commit, then implementation.

## Request and authorization

Feedback item: none. No item in `docs/feedback/items/` matches, and the request arrived as a voice memo with no post to cite in `Sources:`, so none was created.

Original request, 2026-11-09 (voice memo from the maintainer, transcribed): "I want the calendar view to have a month view with very tiny text so that I can also view it on iPhone with not just the number of things showing for each day but the first couple of words. And also I want a week view and a day view I guess, but the main thing is I want a tiny text month view."

Authorization, 2026-11-09: the maintainer sent the memo into a development session with no other words. Read as the same go-ahead as a pasted thread under [thread to pull request](../../agents/thread_to_pr.md): one-shot through an open PR for the part he called "the main thing". Exclusions: merge, deployment, the week view and the day view.

## Outcome and work type

Application change (UI, CSS only). On a phone-width `/calendar` or tag calendar, each day square shows the first couple of words of up to three event titles in very small text, beside the day number and event count it already shows. Tapping anywhere in the square still opens the day page, which lists the events in full.

Not in this cycle: a week view (new, follow-up) and a day view. A day view already exists at `/calendar/<date>`; whether he wants something different from it is an open question for the follow-up.

## Approach and reuse decisions

- **Reuse the existing markup.** The month template already renders up to `ENTRIES_PER_CELL` (3) titles and a "+N more" link in every cell; the phone media query in `public/assets/calendar.css` hides them. The change is in that media query only: show the entries at a tiny size, one line each, clipped at the square's edge, instead of hiding them. Server HTML stays identical for every viewer.
- **Taps.** At phone size the titles are too small to aim at. They stop taking taps there, so a tap anywhere in the square falls through to the existing whole-square day link (the `::after` overlay). Desktop keeps title links.
- **Count.** Kept, moved onto the number's line so the titles have room.
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

Planning commit: pending.

## Study history

- **Intake, 2026-11-09T03:27Z, 0.10.** Single exposed rater: Claude (claude-opus-5-5). Pristine intake missing: the calendar controller, templates and CSS were read before rating. Scope v1 as above. Compared with R1's duplicate preview suppression (0.10) and `v3/calendar_today` (0.08).

## Dated execution history

- 2026-11-09: record created.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
