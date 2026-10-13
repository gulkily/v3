> **Feature plan:** [Step 1](./private_messaging_feature_flag_step1_solution_assessment.md) · [Step 2](./private_messaging_feature_flag_step2_feature_description.md) · [Step 3](./private_messaging_feature_flag_step3_development_plan.md) · [Step 4](./private_messaging_feature_flag_step4_implementation_summary.md)

Debug mode: disabled

Implementation on `feature/private-messaging-feature-flag`; planning commit `5e926950`.

## Stage 1 - Operator control and server enforcement

- Changes: Added default-enabled `FORUM_PRIVATE_MESSAGES_ENABLED` to existing signed operator controls, with deployment override locking. Gated the messaging page/API namespaces before private storage access; disabled responses are no-store HTTP 403 with `messaging_disabled` for APIs. Gated navigation/background script loading and profile/username composers.
- Verification: `php tests/run.php FeatureFlagEvaluatorTest PrivateMessageFeatureFlagTest PrivateMessagePageControllerTest FeatureFlagsBehaviorTest WriteApiSmokeTest::testFeatureFlagApisRequireRootApprovalAndBrowserSignature WriteApiSmokeTest::testPreparedFeatureFlagChangeVerifiesSignatureAndCommitsActionEvidence` — 26 passed. Extended gate tests then reran `PrivateMessageFeatureFlagTest` — 2 passed. Covered GET/POST for every messaging endpoint, guest/member access, future namespace routes, private-storage preservation, fresh-request state, profile/username entry points, navigation/scripts, defaults and both overrides. `git diff --check` passed.
- Notes: No schema or storage changes. Existing in-flight requests may complete. Browser disabled-state recovery follows in Stages 2–3; full signed messaging-toggle journey follows in Stage 4.
