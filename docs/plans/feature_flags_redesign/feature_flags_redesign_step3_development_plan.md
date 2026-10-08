# Step 3: Development Plan — Feature Flags Page Redesign

**Scope note:** search/filter (toolbar, search input, status-filter chips) is deferred to a follow-up feature per Step 2. This plan covers layout, badges, grouping, dependencies, and the save-flow rewrite only.

## Stage 1
- Goal: Extend the backend data model — add a display `group`/label concept and wire the two additional dependency pairs.
- Dependencies: none
- Expected changes: `FeatureFlagDefinition` gains an optional `group` constructor param (falls back to key prefix when unset); `FeatureFlagRegistry` gains a small group→display-name lookup (e.g. `FeatureFlagRegistry::groupLabel(string $group): string`); set `requiresEnabledFlag` on `DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED` → `DEDALUS_AGENT_REPLIES_ENABLED` and `LLM_CONVERSATION_UI_ENABLED` → `LLM_CONVERSATION_RECORDING_ENABLED`.
- Verification approach: existing `FeatureFlagEvaluatorTest` passes; add a case asserting the two new dependent flags evaluate to disabled when their parent is off.
- Risks or open questions:
  - Must confirm in code (not just by description) that Automatic agent replies actually requires Agent replies, and LLM conversation UI actually requires LLM conversation recording, before wiring `requiresEnabledFlag` — a false dependency would hide a working flag.
  - `group` must stay separate from the existing `category` field (evaluation logic), not merged into it.
- Canonical components/API contracts touched: `FeatureFlagDefinition.php`, `FeatureFlagRegistry.php`.

## Stage 2
- Goal: Add the per-flag display helpers the template needs (lock reason, dependency state) without touching markup.
- Dependencies: Stage 1
- Expected changes: `FeatureFlagState` gains helper method(s), e.g. `lockReason(): ?string` branching on `source` (environment / private-config / default-immutable) and dependency-state exposure (parent key, parent label, whether parent is currently off).
- Verification approach: unit tests covering all `lockReason()` branches and the three dependency states (no dependency / satisfied / blocked).
- Risks or open questions: keep this on `FeatureFlagState`/`FeatureFlagRegistry` rather than introducing a new presenter class, per house preference for avoiding unnecessary abstractions.
- Canonical components/API contracts touched: `FeatureFlagState.php`, `FeatureFlagRegistry.php`.

## Stage 3
- Goal: Replace the `<table>` in the page template with the grouped-list markup (summary line, group headers, per-row name/description/key/switch/badges/dependency line), keeping no-JS form fallbacks.
- Dependencies: Stage 2
- Expected changes: `templates/pages/feature_flags.php` rewritten around a per-flag loop; preserve `data-feature-flag-row`, `data-flag-key`, `data-feature-flag-form` hooks; keep `data-role="feature-flag-effective"`/`"feature-flag-source"` present (visibly or as an sr-only/hidden node) for existing hook consumers; add a reset-to-default mini-form per mutable overridden flag, posting through the same `/tools/feature-flags/` no-JS path.
- Verification approach: page renders without fatal errors locally; visual spot-check of the new markup structure (no styling yet).
- Risks or open questions:
  - This will break existing table-column assertions in tests — tracked for update in Stage 8, not fixed here.
  - Reset-to-default reuses the existing save endpoint/contract; confirm no new endpoint is needed.
- Canonical components/API contracts touched: `templates/pages/feature_flags.php`; `/api/set_feature_flag` contract (reused, unchanged).

## Stage 4
- Goal: Style the grouped list, row, and switch — list/group/row layout, switch control, mobile (<520px) reflow — using only existing site tokens.
- Dependencies: Stage 3 (class names finalized)
- Expected changes: new rules appended to the shared stylesheet source for `.flag`-list/group/row/switch; remove now-unused `.codebase-facts` usage from this page.
- Verification approach: manual check at ~1150px and ~375px in light, dark, and one alternate theme (console or word97); confirm no mid-word wrapping of names/keys.
- Risks or open questions: confirm how a source CSS edit reaches the next hashed `site.<hash>.css` build artifact referenced by pages.
- Canonical components/API contracts touched: shared site stylesheet (source of `public/assets/site.<hash>.css`).

## Stage 5
- Goal: Style badges (overridden/locked) and the error banner, and fix the tools sub-nav wrap — same stylesheet, still using existing tokens only.
- Dependencies: Stage 4
- Expected changes: `.badge` (overridden/locked variants), `.feedback.feedback-error`-based banner styling for the invalid-site-value case, one-line nav rule so "Feature Flags" wraps evenly with its siblings.
- Verification approach: manual check across the same widths/themes as Stage 4; confirm badges/banner are legible with text, not color alone.
- Risks or open questions: none beyond Stage 4's.
- Canonical components/API contracts touched: shared site stylesheet.

## Stage 6
- Goal: Rewrite the save flow for switch semantics and wire the error banner.
- Dependencies: Stage 3 (markup), Stage 1–2 (state/source data)
- Expected changes: `feature_flags.js` submit handler updates `aria-checked` and the enabled/disabled label and the existing inline `data-role="feature-flag-status"` line (no toast) instead of `<select>` text; reset-to-default control follows the same handler path; template renders the Stage 5 error banner when `siteError` is present.
- Verification approach: manual toggle with JS on (row updates in place) and JS off (full-page POST + redirect still works via existing `ToolsPageController` flow); simulate a canonical-record parse failure to confirm the banner appears.
- Risks or open questions: none beyond prior stages.
- Canonical components/API contracts touched: `public/assets/feature_flags.js`; `ToolsPageController.php` redirect/message path (reused, unchanged).

## Stage 7
- Goal: Update and extend automated tests, then do a final manual pass.
- Dependencies: Stages 1–6
- Expected changes: update selectors in `FeatureFlagsBehaviorTest`/`WebServerRoutingTest` for the new markup; extend `FeatureFlagEvaluatorTest` coverage from Stage 1–2; add tests for override/lock/dependency badge rendering and the no-JS POST path; manually verify the page stays scannable with a temporary 50-flag test fixture (not committed to the real registry).
- Verification approach: full project test suite green; repeat the width/theme manual check from Stages 4–5 as a final confirmation.
- Risks or open questions:
  - Confirm the exact test-run command before starting this stage.
  - This is the largest stage by file count — split test-file updates into separate commits if it exceeds the ~1 hour/50-line guidance.
- Canonical components/API contracts touched: `tests/FeatureFlagsBehaviorTest.php`, `tests/FeatureFlagEvaluatorTest.php`, `tests/WebServerRoutingTest.php`.
