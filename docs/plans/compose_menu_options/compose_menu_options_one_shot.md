# Compose menu options — one-shot

Cycle: `v3/compose_menu_options`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](compose_menu_options_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/compose_menu_options`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.06**

Status: planning; next action: implement on `feat/compose_menu_options`.

## Request and authorization

Feedback item: [PF-20261109-F01](../../feedback/items/PF-20261109-F01.txt), created for this cycle (no existing item matched).

Original request, 2026-11-08 (https://zenmemes.com/threads/thread-20261108235455-97e8f62c, by ilyag): "Get rid of the formatting help link in the menu".

Authorization, 2026-11-09: the maintainer pasted the thread link into a session, then added in session: "I think all the extra things that are config and not actions should move to something expandable" and "So instead of removing the link, let's move it". Under [thread to pull request](../../agents/thread_to_pr.md) this authorizes a one-shot cycle through an open PR and a reply in the thread. Exclusions: merge, deployment.

## Outcome and work type

Application change (UI). The composer's ... menu keeps its actions at the top level: Attach image, Post/Reply anonymously, Save to Outbox, Remove timestamps (quote composer), Discard draft. The settings move into one collapsed "Options" row at the bottom of the menu: the Markdown box, the Formatting help link (moved, not removed) and the theme choice. Field names and values are unchanged, so posting behaves as before.

## Approach and reuse decisions

- **Reuse.** Nested native `<details>` inside the existing `templates/partials/composer.php` menu items, the same element the menu itself and `.compose-disclosure` already use. Existing `.compose-action-menu-check` / `.compose-action-menu-link` / `.compose-action-menu-theme` row classes keep their styling; selectors relax from direct-child (`.compose-action-menu-list >`) to descendant where needed.
- **Menu script.** `public/assets/compose_disclosure.js` closes the menu only on button clicks and outside clicks; the nested summary is inside the menu, so it stays open. Escape still finds the outer toggle first. `public/assets/reply_viewport.js` listens for the outer menu's `toggle`; it switches to a capture listener so the phone reply panel also re-measures when Options opens.
- **Considered and rejected:** a separate gear button beside the ... toggle (another control on the action row, which is already tight on phones); removing Formatting help (superseded by the maintainer's in-session instruction).
- No new dependency, route, field or asset file. New copy: the word "Options".

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

Planning commit: pending (this record ships in it).

## Study history

- **Intake, 2026-11-09T18:05Z, 0.06.** Single exposed rater: Claude (claude-opus-5-5). Pristine intake missing: the composer template, menu CSS and scripts were read before rating. Scope v1 as above. Compared with R1's duplicate preview suppression (0.10).

## Dated execution history

- 2026-11-09: record created; scope widened in session from removing the link to moving all settings into an expandable section, keeping the link.
