# Compose menu options — one-shot

Cycle: `v3/compose_menu_options`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](compose_menu_options_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/compose_menu_options`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.08**

Status: technically complete on `feat/compose_menu_options`; pushed, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Feedback item: [PF-20261109-F01](../../feedback/items/PF-20261109-F01.txt), created for this cycle (no existing item matched).

Original request, 2026-11-08 (https://zenmemes.com/threads/thread-20261108235455-97e8f62c, by ilyag): "Get rid of the formatting help link in the menu".

Authorization, 2026-11-09: the maintainer pasted the thread link into a session, then added in session: "I think all the extra things that are config and not actions should move to something expandable" and "So instead of removing the link, let's move it". Under [thread to pull request](../../agents/thread_to_pr.md) this authorizes a one-shot cycle through an open PR and a reply in the thread. Exclusions: merge, deployment.

## Outcome and work type

Application change (UI). The composer's ... menu keeps its actions at the top level: Attach image, Post/Reply anonymously, Save to Outbox, Remove timestamps (quote composer), Discard draft. The settings move into one collapsed "Options" row at the bottom of the menu: the Markdown box, the Formatting help link (moved, not removed) and the theme choice. Field names and values are unchanged, so posting behaves as before.

## Approach and reuse decisions

- **Reuse.** Nested native `<details>` inside the existing `templates/partials/composer.php` menu items, the same element the menu itself and `.compose-disclosure` already use. Existing `.compose-action-menu-check` / `.compose-action-menu-link` / `.compose-action-menu-theme` row classes keep their styling; selectors relax from direct-child (`.compose-action-menu-list >`) to descendant where needed.
- **Menu script.** `public/assets/compose_disclosure.js` closes the menu only on button clicks and outside clicks; the nested summary is inside the menu, so it stays open. Escape still finds the outer toggle first. `public/assets/reply_viewport.js` listens for the outer menu's `toggle`; it switches to a capture listener so the phone reply panel also re-measures when Options opens.
- **Placement (added during execution).** The menu opens upward from the action row. On the New post page on a phone `main` already ran 11px past the top of the page; with Options open it ran 76px. `compose_disclosure.js` now measures the list on any toggle inside the menu (capture listener) and adds `compose-action-menu--below`, which opens it downward, when it would start above the page. The phone reply panel keeps its own placement and is skipped.
- **Considered and rejected:** a separate gear button beside the ... toggle (another control on the action row, which is already tight on phones); removing Formatting help (superseded by the maintainer's in-session instruction).
- No new dependency, route, field or asset file. New copy: the word "Options", with a ▸/▾ marker so the row reads as expandable.

## Concepts touched

None in the concept inventory.

## Risks

- Options starts collapsed on every page load, so a member who uses Markdown often needs one more tap. Accepted as the requested trade-off; the box state itself is unchanged.
- Without JavaScript the native `<details>` still works.

## Verification and recovery plan

- `WriteApiSmokeTest::testComposersOfferTheMarkdownBoxAndTheUnsignedFormPathHonoursIt` updated to check the controls sit inside the Options section.
- Smoke set plus browser composer checks where the environment allows; headless Chrome render of the open menu with Options collapsed and expanded on desktop and phone widths, in a few themes.
- Recovery: revert the commit.

Starting revision and branch: `ddf8fa550e49626c0fe75bd3d7c76d15712eae29` on `main`; branch `feat/compose_menu_options`.

Planning commit: `c617cd8` (confirmed with `git log`).

## Study history

- **Intake, 2026-11-09T18:05Z, 0.06.** Single exposed rater: Claude (claude-opus-5-5). Pristine intake missing: the composer template, menu CSS and scripts were read before rating. Scope v1 as above. Compared with R1's duplicate preview suppression (0.10).
- **Execution entry, 2026-11-09T18:06Z, 0.06 reaffirmed.** No new evidence.
- **Closeout, 2026-11-09.** Revised to **0.08**; retrospective scope assessment **0.08**, same single exposed rater. Addition: downward menu placement, found in visual verification (the expanded menu ran off the top of the phone page). Delivered scope v1 in full plus that addition. Maintainer fit and acceptance pending. No active-work measurement collected.

## Dated execution history

- 2026-11-09: record created; scope widened in session from removing the link to moving all settings into an expandable section, keeping the link.
- 2026-11-09: planning commit `c617cd8`; moved the settings into a nested `<details>` in `templates/partials/composer.php`, relaxed menu CSS, capture `toggle` listener in `reply_viewport.js`; test assertions added.
- 2026-11-09: headless Chrome showed the expanded menu clipped above the page on phones (pre-existing 11px on `main`, 76px expanded). Addition: downward placement in `compose_disclosure.js` and `compose.css`. Added the ▸/▾ marker.

## Outcome

Delivered as bounded, plus downward placement. Limitations: Options starts collapsed on each page load; production changes only after deployment.

## Verification

- Passed: `./v3 test --smoke WriteApiSmokeTest::testComposersOfferTheMarkdownBoxAndTheUnsignedFormPathHonoursIt`, 442 run, 442 passed.
- Passed: `node tests/browser/compose_disclosure.mjs`.
- `node tests/browser/composers.mjs` and `node tests/browser/reply_actions_layout.mjs` fail identically on `main` with this change stashed (pre-existing: a reply-post wait in the first, a missing autoloader for `ThreadTheme` in the second's template harness).
- Passed, headless Chrome against `./v3 start` on `/compose/thread`, 1100px and 390px, default, dark, Gloss, Word97 and Chicago themes: top-level menu shows only Post anonymously, Save to Outbox, Discard draft and Options; Markdown is hidden until Options opens; opening Options and ticking Markdown keep the menu open; no page errors. List top on a phone: 325px with the menu opened downward (was -11px on `main`, -76px before the placement fix); desktop stays upward at 75px.
- Not run: the full suite (maintainer runs it). Not checked: the phone reply panel with Options open, the inline board and thread composers in a browser (same partial, covered by the template test), the remaining themes.

## Final review

Diff checked: one template partial, one stylesheet, two existing scripts, one test, plus records. No duplicated components, no new fields or routes, no dependency.

## Commit, merge and publication status

Completion commit: pending (this record ships in it). Pushed to origin; not merged, not deployed.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Follow-up: Options moves out of the menu (2026-11-09)

Request, 2026-11-09 after PR #103 merged, in session: "Please move from the menu to the dialog", clarified as "everything that is in the new options thing that you created under the menu, I meant for that to be another expandable thing in the compose dialog". Branch `feat/compose_options_section` from `origin/main` `b62f35c`.

- Options is now a `compose-disclosure` (the Tags/Event component) holding the Markdown box, Formatting help and the theme choice. On the full New post composer it sits in the tools row after Tags and Event; on the board, thread and reply composers it is its own row below the action row (those composers have no tools row, and `LocalAppSmokeTest` keeps it that way). Inline composers keep it hidden until expanded, like their other rows.
- Its summary shows what is set, e.g. "Markdown · Gloss", through the existing disclosure summary script (new `options` kind, plus a `change` listener).
- The ... menu is back to actions only. The menu's downward placement stays; its nested-Options code and CSS are removed, and `reply_viewport.js` is back to a plain toggle listener.
- Verification: `./v3 test --smoke WriteApiSmokeTest::testComposersOfferTheMarkdownBoxAndTheUnsignedFormPathHonoursIt LocalAppSmokeTest` (442 passed; the two named tests also run alone, 166 passed). `tests/browser/compose_disclosure.mjs` and `tests/browser/calendar.mjs` ok. Headless Chrome on New post, board and a thread's reply composer at 1100px and 390px in default, Gloss and Word97: no settings left in the menu, Options collapsed by default, ticking Markdown and picking a theme updates the summary, no page errors. Full suite not run; phone reply panel with Options open not checked.
- Effort: follow-up revision, no new estimate.

## Follow-up: Options as a settings list (2026-11-09)

Request, in session with a screenshot of the Options panel: "let's design something better, with anticipation for a few more options in the future". Three mocked directions (settings list, chips, grouped list); the maintainer chose the settings list ("i agree with A"). Branch `feat/compose_options_list` from `origin/main` `d0cd655`.

- One row per option with hairlines between: name on the left, control on the right. Markdown is a switch (still the same `body_format` checkbox, `role="switch"`), with Formatting help as a small "?" beside its name. The theme choice is a styled menu. Labels are no longer bold.
- Collapsed: "Options · Markdown · Gloss"; the summary also follows the Markdown box when `markdown_detect.js` ticks it as the body is typed.
- Switch colors come from each theme's text and page colors (outlined when off, filled when on) with `:root` specificity above the themes' generic input rules; the first version used `--active-line` and lost its "on" state in Word97, Midnight and Arena.
- Rule for future options: one row each; on/off is a switch, a choice is a menu; a hint line only when the name can't explain itself; group headings once there are about five rows.
- Verification: `./v3 test --smoke WriteApiSmokeTest::testComposersOfferTheMarkdownBoxAndTheUnsignedFormPathHonoursIt LocalAppSmokeTest`, 442 passed. `tests/browser/compose_disclosure.mjs` and `tests/browser/calendar.mjs` ok. Headless Chrome on New post (1100px and 390px) and a thread's reply composer (390px) in default, dark, Gloss, Word97, Chicago, Midnight, Console, LCD, Arena and Forge: no overflow, no page errors, summary follows auto-detect and the theme choice; on and off states reviewed. Full suite not run; phone reply panel with Options open and remaining themes unchecked.

