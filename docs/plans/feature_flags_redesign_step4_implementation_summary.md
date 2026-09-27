# Step 4: Implementation Summary — Feature Flags Page Redesign

## Stage 1 - Backend data model extensions
- Changes:
  - `FeatureFlagDefinition`: added optional `?string $group` constructor param and a `groupKey(): string` method (falls back to the key's prefix before the first `_` when `group` is unset).
  - `FeatureFlagRegistry`: added a `groupLabel(string $groupKey): string` lookup (`FORUM` → "Forum", `DEDALUS` → "Dedalus agent", `LLM` → "LLM exchanges"; unknown keys pass through unchanged).
  - `FeatureFlagRegistry`: wired `requiresEnabledFlag: self::DEDALUS_AGENT_REPLIES_ENABLED` onto `DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED`.
- Verification:
  - `php -l` on both modified source files and the modified test file — no syntax errors.
  - `php tests/run.php FeatureFlagEvaluatorTest` — 9 run, 9 passed (7 pre-existing unchanged + 2 new).
  - `php tests/run.php FeatureFlagsBehaviorTest` — 2 run, 2 passed (unaffected, sanity check).
  - Added `testAutomaticAgentRepliesDependsOnAgentReplies` (asserts `effectiveValue=false`, `source='dependency'` when the parent is off via private config) and `testGroupKeyFallsBackToPrefixAndRegistryLabelsKnownGroups`.
- Notes:
  - **Deviation from the Step 3 plan**: did not wire `requiresEnabledFlag` for `LLM_CONVERSATION_UI_ENABLED` → `LLM_CONVERSATION_RECORDING_ENABLED`. Checked the actual call sites (`Application::viewerCanInspectLlmExchanges()` and `Application::llmExchangeStore()`, both in `src/ForumRewrite/Application.php`) — neither checks the recording flag; both gate only on the UI flag and read the same on-disk exchange DB the recorder writes to. There is no code-enforced dependency here, unlike the Dedalus case (`agentRepliesAutomaticEnabled()` explicitly short-circuits on its parent). Wiring it as planned would have made the UI show a false "inactive — requires recording" state that doesn't match actual behavior. Flagged to the user; only the confirmed Dedalus dependency was implemented.
  - `group` is intentionally kept separate from the existing `category` field (per Step 2/3), which still drives `FeatureFlagEvaluator`'s private-config gating and is untouched here.

## Stage 2 - State/view-model helpers
- Changes:
  - `FeatureFlagState`: added `isLocked(): bool`, `lockReason(): ?string` (branches on env / private-config / plain non-mutable default), and `isBlockedByDependency(): bool`.
  - `FeatureFlagState`: added `?bool $dependencyParentEnabled` constructor param.
  - `FeatureFlagEvaluator::evaluate()`: now always resolves and attaches `dependencyParentEnabled` for any flag with a `requiresEnabledFlag`, not just when the child's own value is currently on — so the template can show "requires X" / dim the row even when the child is already off for its own reasons.
  - `ToolsPageController::featureFlags()`: passes a `FeatureFlagRegistry` instance to the template as `registry` (needed for parent/group label lookups in Stage 3); no markup changed.
- Verification:
  - `php -l` on all four modified files — no syntax errors.
  - `php tests/run.php FeatureFlagEvaluatorTest` — 12 run, 12 passed (added `testDependencyParentEnabledIsExposedEvenWhenChildIsAlreadyOff`, `testLockReasonBranchesBySource`, `testLockReasonIsNullOnSiteErrorSoTheBannerOwnsThatMessage`).
  - `php tests/run.php FeatureFlagsBehaviorTest LocalAppSmokeTest WebServerRoutingTest` — 97 run, 92 passed; the 5 failures are pre-existing/long-standing (activity commit-manifest tests, unrelated to feature flags, tracked since 2026-09-25 per the runner's own history and `todo.txt`'s "clean up the failing tests" item). Both feature-flags-specific smoke tests (`testFeatureFlagsPageShowsSiteBackedValuesAndLayoutUsesThem`, `testFeatureFlagsPageReportsInvalidSiteRecordWithoutBreakingSite`) pass, confirming the controller change didn't regress the current template.
- Notes:
  - Found and fixed a real bug while writing tests: the first `isLocked()` draft excluded the lock badge whenever `siteError !== null` *anywhere on the state*, but `FeatureFlagEvaluator` attaches the evaluator-wide site error to every flag's state regardless of which branch produced its value (environment/private-config/default all carry it through). That meant one broken site record would have silently hidden the lock badge on *every* environment- and private-config-locked flag, not just the ones actually affected by the broken record. Fixed by keying the exclusion off `source !== 'invalid-site-value'` (the specific branch that broken-record fallback produces) instead of the raw `siteError` field. Caught by `testLockReasonBranchesBySource` failing before the fix.

## Stage 3 - Template markup rewrite
- Changes:
  - `templates/pages/feature_flags.php` rewritten from a `<table>` to a grouped-list layout: summary line (`feature-flags-summary`), one `feature-flag-group` section per group with a heading ("N of M on"), and one `feature-flag-row` div per flag containing name/badges, description, key, dependency line, and a switch/state control.
  - Preserved `data-feature-flag-row`, `data-flag-key`, `data-feature-flag-form` hooks unchanged; `data-role="feature-flag-effective"` and `data-role="feature-flag-source"` are still present (moved from `<code>`/`<td>` to `<span>` elements — the exact element tag changed, tracked for Stage 7 selector updates).
  - Overridden badge shows whenever `!$flag->isDefault()`; locked badge + tooltip uses `$flag->isLocked()`/`lockReason()` from Stage 2; dependency line uses `$flag->isBlockedByDependency()` and looks up the parent's label via the new `registry` template var and `FeatureFlagDefinition::requiresEnabledFlag`.
  - Reset-to-default is a second `data-feature-flag-form` form per mutable overridden flag, posting the flag's own default value — reuses the same save contract as the toggle, no new endpoint.
  - Removed the `<select>`/Save-button pair; the switch is now a single submit button posting the flipped value via a hidden input, so it still works with JS off.
  - Toolbar/search markup intentionally omitted (deferred feature). Page-level error banner for `invalid-site-value` intentionally deferred to Stage 6 per the plan (the per-row `data-role="feature-flag-source"` hook already reflects that source value in the interim).
- Verification:
  - `php -l templates/pages/feature_flags.php` — no syntax errors.
  - `php tests/run.php LocalAppSmokeTest` — 93 run, 85 passed. 3 new failures, all exact-string assertions expecting `<code data-role="feature-flag-source">...</code>` where the markup now emits `<span data-role="feature-flag-source">...</span>` — expected per the Step 3 plan's flagged risk, tracked for Stage 7. The 5 pre-existing long-standing activity-manifest failures are unchanged and unrelated.
  - Manually rendered `/tools/feature-flags/` via `Application::handle()` for three cases and inspected raw HTML: (1) an overridden+dependency-blocked flag (`FORUM_EMOJI_AUTHORED_TEXT` with `FORUM_UNICODE_AUTHORED_TEXT` off) shows the `is-blocked` row class, the "&#9888; inactive — requires Unicode authored text" line, and a working reset-to-default form on the overridden `FORUM_APP_VERSION_NOTIFICATION` row; (2) a locked private flag (`DEDALUS_AGENT_REPLIES_ENABLED`) shows the locked badge with "Not configurable from the site." tooltip and a disabled switch with correct `aria-checked`; (3) group headers show correct "N of M on" counts.
- Notes:
  - No PHP fatals/exceptions in any rendered case — the 3 broken assertions are purely about the hook's element tag, not page correctness.
  - CSS for all new classes (`.feature-flag-*`, `.badge`, `.switch`, `.sr-only`) doesn't exist yet — the page currently renders unstyled; Stages 4–5 add it.
