# Compact Mode Menu Buttons Step 3 Development Plan

## Stage 1
- Goal: Add shared compact-mode density rules for existing navigation, menu, and action controls.
- Dependencies: Approved Step 2; existing `data-thread-density="compact"` state and canonical control classes.
- Expected changes: Extend `public/assets/site.css` with compact-mode selectors for shared navigation links, menu buttons, and action rows; reduce nonessential spacing while preserving readable labels, visible focus, and mobile minimum targets; regenerate any required fingerprinted asset.
- Verification approach: Inspect compact and comfortable renders at desktop and narrow mobile widths across representative pages and themes.
- Risks or open questions:
  - Broad selectors could unintentionally affect controls outside thread/board contexts.
  - Visual density must not reduce the existing touch-target minimum.
- Canonical components/API contracts touched: `thread_density_toggle.php`; `layout.php` density state; shared `.nav-link`, button-row, and control CSS; no API or persistence changes.

## Stage 2
- Goal: Lock down state isolation and usability with focused regression coverage.
- Dependencies: Stage 1 selectors and regenerated assets.
- Expected changes: Add CSS-contract assertions or browser-behavior coverage for compact versus comfortable state, focus visibility, and mobile control sizing; retain existing density-toggle tests.
- Verification approach: Run focused density/render tests, PHP and asset syntax checks, responsive viewport smoke checks, and `git diff --check`.
- Risks or open questions:
  - Exact visual dimensions may require manual viewport review rather than reliable string assertions.
- Canonical components/API contracts touched: `LocalAppSmokeTest` or existing density-toggle tests; `public/assets/thread_density_toggle.js` contract remains unchanged.
