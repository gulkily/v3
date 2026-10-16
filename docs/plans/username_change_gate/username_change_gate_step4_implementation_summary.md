> **Feature plan:** [Step 1](./username_change_gate_step1_solution_assessment.md) · [Step 2](./username_change_gate_step2_feature_description.md) · [Step 3](./username_change_gate_step3_development_plan.md) · [Step 4](./username_change_gate_step4_implementation_summary.md)

Debug mode: enabled

Branch: `feature/username-change-gate`. Planning commit: `595e9c7f`.

Implementation in progress; the flag ships off by default.

## Stage 1 - Server-side gate with a default-off flag

- Changes: new site-mutable flag `FORUM_USERNAME_CHANGES_ENABLED` (default off, ACCESS group) in `FeatureFlagRegistry`; `prepareProfileName` and `finalizePreparedProfileName` refuse with "Username changes are disabled." when it is off, checked before any write; README documents the flag.
- Verification:
  - `php tests/run.php WriteApiSmokeTest` — 163 passed, including the new `testUsernameChangesAreDisabledByDefaultAndGateBothEndpoints` (default off, prepare refused, finalize of a token prepared while enabled refused after toggle, no record written, later success when enabled).
  - `php tests/run.php FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest` — 19 passed after adding the flag to the registry listing expectation.
  - Existing rename test now enables the flag explicitly.
- Notes: the UI button and activity are still ungated and unchanged until Stages 2–6. The caption choice is still needed before Stage 3. A full-suite run is planned for Stage 7.

## Stage 2 - Hide "Choose username" when the flag is off

- Changes: `TemplateRenderer` exposes `usernameChangesEnabled` to every template; `account_key.php` keeps the "Choose username" button in the markup but adds `hidden` unless the flag is on (hidden, not deleted, matching the page-hook convention in `AccountKeyPageHooksTest`; the endpoint gate from Stage 1 still protects it).
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest BrowserSigningNormalizationTest` — 86 passed, including the new `testChooseUsernameButtonIsHiddenUnlessUsernameChangesAreEnabled` (flag off, on, and unconfigured default).
  - `php tests/run.php LocalAppSmokeTest` — 140 passed.
  - Cache risk checked: `/account/key/` is not a pre-built static artifact (not in `StaticArtifactInvalidator` or `StaticArtifactBuilder`), so a flag toggle cannot leave stale markup.
- Notes: real-browser confirmation of the hidden button is part of Stage 7. The caption (Stage 3) still needs the user's choice; default is "Replace key (new identity)".
