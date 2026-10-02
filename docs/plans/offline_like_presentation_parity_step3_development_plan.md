# Offline Like Presentation Parity — Step 3 Development Plan

## Stage 1
- Goal: Load the canonical interaction presentation for snapshot Like controls without changing offline Like behavior or adding other actions.
- Dependencies: Approved Step 2 description; the existing `thread-reaction-button` and `post-card-actions` classes emitted by `offline_reader.js`; the shared `content-interactions.css` asset and fingerprinting pipeline.
- Expected changes: Register `content-interactions.css` for the `offline_reader.php` page template; extend focused presentation/smoke coverage to assert the offline shell includes the shared stylesheet and snapshot root/reply Likes retain the natural online action-row contract and queue behavior.
- Verification approach: Run PHP syntax checks for changed PHP/tests, JavaScript syntax checking for the existing reader script if touched, then run the focused offline snapshot presentation and offline-reader smoke tests.
- Risks or open questions: None; this reuses an existing stylesheet and requires no schema, API, queue, signing, or template-markup changes.
- Canonical components/API contracts touched: `TemplateRenderer::PAGE_STYLESHEET_PATHS` registration for `offline_reader.php`; `content-interactions.css`; `thread-reaction-button` within the `button-row button-row-natural post-card-actions` contract; existing offline Like queue payloads.
