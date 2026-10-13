> **Feature plan:** [Step 1](./private_messaging_feature_flag_step1_solution_assessment.md) · [Step 2](./private_messaging_feature_flag_step2_feature_description.md) · [Step 3](./private_messaging_feature_flag_step3_development_plan.md) · [Step 4](./private_messaging_feature_flag_step4_implementation_summary.md)

Debug mode: disabled

Implementation on `feature/private-messaging-feature-flag`; planning commit `5e926950`.

## Stage 1 - Operator control and server enforcement

- Changes: Added default-enabled `FORUM_PRIVATE_MESSAGES_ENABLED` to existing signed operator controls, with deployment override locking. Gated the messaging page/API namespaces before private storage access; disabled responses are no-store HTTP 403 with `messaging_disabled` for APIs. Gated navigation/background script loading and profile/username composers.
- Verification: `php tests/run.php FeatureFlagEvaluatorTest PrivateMessageFeatureFlagTest PrivateMessagePageControllerTest FeatureFlagsBehaviorTest WriteApiSmokeTest::testFeatureFlagApisRequireRootApprovalAndBrowserSignature WriteApiSmokeTest::testPreparedFeatureFlagChangeVerifiesSignatureAndCommitsActionEvidence` — 26 passed. Extended gate tests then reran `PrivateMessageFeatureFlagTest` — 2 passed. Covered GET/POST for every messaging endpoint, guest/member access, future namespace routes, private-storage preservation, fresh-request state, profile/username entry points, navigation/scripts, defaults and both overrides. `git diff --check` passed.
- Notes: No schema or storage changes. Existing in-flight requests may complete. Browser disabled-state recovery follows in Stages 2–3; full signed messaging-toggle journey follows in Stage 4.

## Stage 2 - Stale-page and interrupted-send recovery

- Changes: Added a shared messaging request/availability component, loaded before messaging scripts. A `messaging_disabled` response latches the current page unavailable, displays accessible feedback, hides navigation, and keeps messaging buttons disabled even after in-flight completions; draft text remains editable. Routed key lookup, compose/retry, reader, list, and conversation-history requests through it. Drafts and original pending envelopes survive disable/re-enable.
- Verification: Focused PHP messaging suite — 12 passed; `node tests/browser/private_message_availability.cjs` passed (ordinary authorization errors remain recoverable, subsequent requests stop, admitted success remains confirmable). `node tests/browser/private_message_list_browser.mjs` passed, artifacts `/tmp/private-message-browser-BwMzZ1`, including real encrypted sends, shutdown with unsent draft, lost accepted-send response, preserved newer draft, re-enable/reload, and exactly one delivery. `git diff --check` passed.
- Notes: Installed the existing declared Playwright dependency locally without a lockfile change. Background unread/history-sync integration remains Stage 3. Existing browser tests now load the shared dependency just as production rendering does.

## Stage 3 - Stop unread and history-sync background work

- Changes: Routed all remaining messaging fetches through the shared availability component. Disabled detection cancels unread/seen scheduling and active sync work; queued sync requests drain without network retries. Existing durable read receipts, coverage, and transfer state are preserved.
- Verification: Focused history crypto/sync/read-state/feature-flag PHP suite — 26 passed. Extended history scheduling checks passed for disabled retries and queued concurrent requests. Full messaging browser runner passed, artifacts `/tmp/private-message-browser-Tn7xJI`: shutdown detected independently through unread, sync, and seen requests; focus/visibility/scroll/retry triggers produce no subsequent messaging traffic; re-enable/reload resumes. History-sync browser runner passed, artifacts `/tmp/history-sync-browser-oMB68N`, including real multi-device history recovery and unchanged original ciphertext. JS syntax and `git diff --check` passed.
- Notes: Browser testing exposed the expected race where background work detects shutdown before Send can be clicked; the stale-form test now handles that valid ordering. All messaging network calls now use the shared request component. Debug mode remains disabled.
