> **Feature plan:** [Step 1](./nav_links_load_stability_step1_solution_assessment.md) · [Step 2](./nav_links_load_stability_step2_feature_description.md) · [Step 3](./nav_links_load_stability_step3_development_plan.md) · [Step 4](./nav_links_load_stability_step4_implementation_summary.md)

Debug mode: enabled

# Step 4: Implementation Summary — Stable Messages/Invite Nav Buttons

## Stage 1 - Diagnose late nav buttons
- Changes:
  - None to application code (read-only diagnosis).
- Verification:
  - Code reading only; no browser run yet.
  - `FrontController::resolveStaticArtifactPath()` serves prebuilt static HTML only for GET requests with no query string and **no cookies at all**; any cookie (session, `identity_hint`, theme hint) falls through to the dynamic `Application`.
  - Static HTML is built with no viewer, so it never contains Messages/Invite. Dynamic pages include them only when `resolveViewerProfileFromIdentityHint()` finds an approved viewer with `_authenticated_identity === true` (needs a live PHP session; an `identity_hint` cookie alone gives `false` and no items).
  - No script found that inserts Messages/Invite on a normal page load. `feature_flags.js` patches Messages only after a flag toggle; `private_site_auth.js` reloads the page after authenticating, and is loaded only in members-only/public-resume modes.
- Notes:
  - **Step 2 assumption not confirmed:** there is no client-side post-load insertion in the code. Plausible explanations, each unverified: (a) navigating between cookie-less static pages and dynamic pages makes the items appear/disappear between page loads; (b) a viewer with an `identity_hint` but no live session gets no items until a session is established; (c) site.css loads non-blocking (`media="print"` swap) so nav styling/icons for these links arrive after first paint.
  - Needs the user's observation (which pages, signed-in how, which theme) or a browser repro before Stage 2 so the fix targets the real cause.

## Stage 1 (revised plan) - Remove visible unread text, keep state in attributes/ARIA
- Changes:
  - `templates/partials/nav.php`: dropped the visible `…` count span; Messages caption is now exactly `Messages`. The hidden status span (`aria-describedby`) stays.
  - `private_message_unread.js`, `feature_flags.js`: stop writing visible count text (`?`, `(N)`); state still set via `data-unread-state`, `data-unread-count`, `title`, and the hidden status text.
  - Browser tests updated to assert the caption stays `Messages` and the status description is populated, instead of `…`/`?`.
- Verification:
  - `node tests/browser/private_message_live_navigation.mjs` passed.
  - `node tests/browser/private_message_list_browser.mjs` passed (includes unread UI checks).
  - `php tests/run.php` exit 0, 1225 passes, no FAIL lines.
- Notes:
  - Outline highlight is Stage 2; until then there is no visible unread signal.
  - Plan revision commit `5931a5da` precedes this stage; the earlier Stage 1 diagnosis (commit `7dc7856f`) is retained as history.

## Stage 2 - Outline highlight for unread Messages
- Changes:
  - `public/assets/site.css`: layout-neutral `outline` on `.nav-link[data-unread-count]` when the count is not `0`.
  - `public/assets/theme-sticker.css`: override (double outline) because sticker already uses a 2px outline as its resting nav style, which would hide the highlight.
  - New `tests/browser/nav_unread_outline.mjs`: for every theme sheet, asserts Messages/Invite boxes are identical across no-count, `0`, and `3`, that unread outline differs from the resting style and is visible, and that zero adds nothing.
- Verification:
  - `node tests/browser/nav_unread_outline.mjs`: passed in 16 themes.
  - `php tests/run.php`: exit 0, 1225 passes. A first run failed 3 tests because the selector included the string `data-private-message-unread` in shared CSS (guarded by release-isolation/nav tests); rekeyed on `data-unread-count` and reran clean.
  - `git diff --check` passed.
- Notes:
  - Contrast/aesthetics of the outline per theme were checked structurally (differs from resting, visible), not by eye; worth a visual look.
