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
