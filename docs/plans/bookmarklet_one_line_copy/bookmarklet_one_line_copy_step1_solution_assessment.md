> **Feature plan:** [Step 1](./bookmarklet_one_line_copy_step1_solution_assessment.md) · [Step 2](./bookmarklet_one_line_copy_step2_feature_description.md) · [Step 3](./bookmarklet_one_line_copy_step3_development_plan.md) · [Step 4](./bookmarklet_one_line_copy_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

Can you please check if this request has already been implemented:

Make it easier to copy the bookmarklets as one-line JS. This is what I need to add/bind bookmarklets in qutebrowser.

If not, please write Step 1 of Railspike.

## Current Status and Problem

Not implemented in the checked-out repository: users can drag bookmarklet links, but cannot explicitly copy a single-line source value for adding or binding in qutebrowser.

- The [page template](../../../templates/pages/bookmarklets.php) has drag instructions and links, with no copy control or selectable source field.
- The [generator](../../../public/assets/tools_bookmarklets.js) embeds multiline function source; a Node VM check of all eight kind/mode combinations found 31 physical line breaks in ordinary bookmarklets and 61 in tweet bookmarklets.
- Existing [smoke coverage](../../../tests/LocalAppSmokeTest.php) checks bookmarklet rendering and compose prefills, not single-line copying. This assessment does not establish deployed-site behavior or qutebrowser compatibility through a browser test.

## Revised Direction

Use a small, icon-only copy button beside each bookmarklet. Do not add a selectable source field; keep the interface compact.

## Option A: Icon-Only Copy Button

Add a small copy icon beside each bookmarklet that copies its one-line source, with an accessible name, a tooltip, and brief success or failure feedback.

- **Pros:** Matches the requested compact interface; provides direct copying for every variant; preserves existing drag behavior and both window modes.
- **Cons:** Clipboard access can fail; failure feedback must be clear and allow retry without claiming success. Exported text must preserve intentional newlines in clipped content.

## Option B: Selectable One-Line Source Only

Expose each bookmarklet as selectable single-line text with short usage guidance, leaving copying to the user's normal keyboard or browser actions.

- **Pros:** Small interface change; works without clipboard permissions; supports inspection and manual transfer.
- **Cons:** Adds the visual clutter the user explicitly rejected; requires more user actions; offers no confirmation that copying succeeded. Retained for comparison only.

## Recommendation

Choose **Option A**, following the user's revised direction: a small, keyboard-accessible, icon-only copy button and no selectable source field. Provide an accessible label and tooltip describing the copy action, plus brief feedback without adding persistent interface clutter. Keep one consistent source for copying and existing bookmarklet links, and validate actual qutebrowser adding/binding before claiming compatibility. The exported format, including the bookmarklet URL prefix, and exact command syntax and quoting remain to be verified during subsequent planning.

This is a small, releasable vertical slice: open Bookmarklets, copy any existing variant as one-line source, and use it in qutebrowser. If copying fails, show a brief failure message and let the user retry. Preserve current clipping and window behavior; no new bookmarklet types, routes, or database changes are needed.
