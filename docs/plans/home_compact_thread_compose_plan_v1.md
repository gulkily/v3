# Home Compact Thread Compose Plan

## Goal

As a user, I want to be able to start a new thread from the home page, using a compact compose field above the feed, similar to the x.com homepage.

## Current Context

- The home/feed route is rendered by `Application::renderBoard()` in `src/ForumRewrite/Application.php`.
- The board template is `templates/pages/board.php`.
- Full thread composition already exists in `templates/pages/compose_thread.php`.
- Thread creation already uses `/api/create_thread` and the browser-signing flow in `public/assets/browser_signing.js`.
- Optimistic thread creation already exists in `submitOptimisticThread()` and inserts a pending thread shell before navigation.
- The board page should stay cheap to load. The compact compose form can be rendered with the page, but the heavier signing/OpenPGP assets should load only after the user shows intent to compose.

## Plan

1. Extract reusable thread-compose markup from `templates/pages/compose_thread.php` into a new partial, likely `templates/partials/thread_compose_form.php`.
   - Parameters: `boardTags`, `subject`, `body`, `notice`, `error`, and a `compact` flag.
   - Preserve `data-compose-root`, `data-compose-form`, `data-compose-kind="thread"`, identity status, normalization status nodes, and field names so `browser_signing.js` continues to work.

2. Update `templates/pages/compose_thread.php` to render the shared partial in full mode.
   - Keep the existing `/compose/thread` page as the full-screen and no-JS fallback.
   - Avoid maintaining two independent thread compose forms.

3. Render the compact composer above the feed in `templates/pages/board.php`.
   - Place it after the board controls and before the thread feed loop.
   - Default `board_tags` to `general`.
   - Use a compact layout: small subject input, short body textarea, primary `Post` submit, optional anonymous submit, and clear action.
   - Keep the existing `New Post` nav link as a fallback until the inline composer is verified.

4. Add lazy signing initialization for the board composer.
   - `Application::renderBoard()` currently renders `board.php` without extra scripts.
   - Add a small board-only bootstrap asset, likely `/assets/lazy_compose_signing.js`, to the board render call instead of eagerly loading `/assets/openpgp_loader.js` and `/assets/browser_signing.js`.
   - The bootstrap should listen for first user intent on the compact composer, such as `focusin`, `pointerdown`, or `input` on the subject/body fields.
   - On first intent, dynamically load `/assets/openpgp_loader.js` and then `/assets/browser_signing.js`.
   - Guard against duplicate loads with an in-flight promise and a loaded flag.
   - Keep `/compose/thread` and reply compose pages loading the signing assets eagerly, because those routes are already explicit compose flows.

5. Make `browser_signing.js` safe to initialize after page load.
   - Check whether `browser_signing.js` currently initializes immediately or only from a `DOMContentLoaded` handler.
   - Expose an idempotent initializer, for example `window.ForumBrowserSigning.init()`, that can be called both on normal script load and after lazy loading.
   - Ensure calling the initializer more than once does not bind duplicate listeners, duplicate draft handlers, or duplicate pending-operation state.
   - The lazy bootstrap should call the initializer after both signing assets have loaded.
   - If initialization needs OpenPGP readiness, preserve the current `openpgp_loader.js` contract rather than duplicating loading logic.

6. Verify optimistic insertion behavior.
   - `browser_signing.js` already handles thread creation in `submitOptimisticThread()`.
   - Confirm `insertPendingThreadShell()` behaves correctly when the compose root is embedded in the board feed.
   - If the pending shell appears inside the composer instead of the feed, add a board-specific wrapper or `data-thread-feed` hook and adjust insertion logic narrowly.

7. Add compact styles in `public/assets/site.css`.
   - Add classes such as `.compact-thread-compose`, `.compact-thread-compose-fields`, and `.compact-thread-compose-actions`.
   - Keep the composer visually lighter than the full compose card.
   - Use existing theme variables rather than introducing a new palette.
   - Ensure the layout works on mobile, including stacked actions and textarea sizing.

8. Add tests.
   - Extend `tests/LocalAppSmokeTest.php` or `tests/WriteApiSmokeTest.php` to assert `/threads/` includes `data-compose-root`, `data-compose-kind="thread"`, `name="subject"`, `name="body"`, and the lazy compose bootstrap script.
   - Assert `/threads/` does not eagerly include `/assets/openpgp_loader.js` or `/assets/browser_signing.js`.
   - Add focused JavaScript coverage for `/assets/lazy_compose_signing.js` if the existing test setup supports asset-level JS tests. Cover first-focus loading, duplicate-interaction dedupe, and calling the exposed signing initializer after load.
   - Add or adjust JS coverage for `browser_signing.js` initialization idempotence if there is already a browser-signing test harness.
   - Add or adjust a smoke test to confirm the board page still renders feed cards.
   - Keep existing thread creation tests around `/api/create_thread` and `/compose/thread` passing.

9. Manual verification.
   - Load `/threads/`.
   - Confirm the compact composer appears above the feed.
   - Confirm the network panel does not show OpenPGP or browser-signing assets before interacting with the compact composer.
   - Focus the compact compose textarea and confirm the lazy bootstrap loads OpenPGP and browser-signing assets once.
   - Create a thread with browser identity.
   - Create a thread anonymously.
   - Confirm validation and Unicode normalization messages still appear.
   - Confirm failed submission restores draft text and successful submission navigates to the created thread.

## Implementation Progress

- 2026-06-24: Created branch `home-compact-thread-compose` and recorded the implementation plan as the baseline slice.
- 2026-06-24: Extracted the full thread compose form into `templates/partials/thread_compose_form.php` so the board and full compose page can share one markup path.
- 2026-06-24: Rendered the compact thread composer above the board feed and added responsive compact compose styles.
