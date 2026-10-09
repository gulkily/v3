> **Feature plan:** [Step 1](./offline_indicator_corner_links_step1_solution_assessment.md) · [Step 2](./offline_indicator_corner_links_step2_feature_description.md) · [Step 3](./offline_indicator_corner_links_step3_development_plan.md) · [Step 4](./offline_indicator_corner_links_step4_implementation_summary.md)

# Offline Indicator Corner Links Step 3 Development Plan

## Completion Contract

- **Entry:** reader opens saved content offline; the existing script reveals the offline-mode bar.
- **Outcome:** the bar renders as a compact fixed top-corner badge (no vertical space) with the "offline mode" label and visible links to `/offline/` and `/tools/outbox/`; archive and reader revision appear as hover titles.
- **Recovery:** unknown freshness values keep `unknown` fallbacks; the badge stays hidden when offline mode is not active.
- **Deployment/external verification:** none beyond the normal asset build; check phone width (360px) in a browser.
- **Release condition:** content starts at the same position with the bar shown or hidden, both links work, and offline tests pass.

## Key Risks

- **High risk:** badge overlaps header controls or tap targets at phone width. Validate in Stage 2 at 360px; mitigate with a small badge and offset away from header chrome.
- Freshness info is hover-only and unavailable on touch. Validate in Stage 1; mitigate by exposing the same text as the badge's accessible title and keeping the health page reachable from `/offline/`.
- Existing assertions expect the current bar markup. Validate in Stage 1; update tests in step with markup changes.

## Stage 1
- Goal: Put the new links and freshness titles into the offline-mode bar markup and script.
- Dependencies: none.
- Expected changes:
  - Add an `/offline/` link beside the Outbox link in `templates/pages/offline_reader.php`.
  - Remove the visible indicator spans; keep their data hooks so `setReaderDetails` still fills in archive and reader values, carried as title text on the badge.
  - Update the matching assertions in `tests/LocalAppSmokeTest.php`.
- Verification approach:
  - `node --check public/assets/offline_reader.js` and PHP syntax checks.
  - `php tests/run.php OfflineSnapshotPresentationTest LocalAppSmokeTest::testOfflineReaderFallbackRouteUsesLocalSnapshotShell`.
- Risks or open questions:
  - Impact: dropping visible indicators may break script lookups.
  - Early warning / validation: tests fail or console errors when the bar is shown.
  - Mitigation: keep data-role hooks and the existing fallbacks.
- Canonical components/API contracts touched: offline-mode bar markup, `offline_reader.js` `setReaderDetails`, `/offline/` and `/tools/outbox/` routes (unchanged).

## Stage 2
- Goal: Make the bar a compact fixed top-corner badge that adds no vertical space.
- Dependencies: Stage 1.
- Expected changes:
  - Replace the full-width `.offline-mode-bar` flow layout in `public/assets/site.css` with a small fixed top-corner badge; remove obsolete indicator and narrow-screen rules.
  - Keep theme variables and the existing small uppercase label style.
- Verification approach:
  - Manual check on wide and 360px screens: no layout shift, no header or tap-target overlap, links tappable.
  - Rerun the Stage 1 tests.
- Risks or open questions:
  - Impact: overlap with the sticky app-version banner or header controls.
  - Early warning / validation: visual check with the banner and header visible.
  - Mitigation: adjust offset or z-index below the banner; keep the badge minimal.
- Canonical components/API contracts touched: `.offline-mode-bar` styles in `site.css`; app-version banner and header (read-only).

## Stage 3
- Goal: Confirm the complete flow and record the result.
- Dependencies: Stage 2.
- Expected changes: none beyond fixes; update the Step 4 summary.
- Verification approach:
  - Run the full offline test group (`php tests/run.php` Offline* classes plus `LocalAppSmokeTest`).
  - Walk the flow offline: open saved content, see the badge, follow both links.
- Risks or open questions:
  - Impact: regressions in unrelated offline pages.
  - Early warning / validation: failing offline tests.
  - Mitigation: fix within scope or return to Step 2 if scope grows.
- Canonical components/API contracts touched: none new.

Approved Step 3?
