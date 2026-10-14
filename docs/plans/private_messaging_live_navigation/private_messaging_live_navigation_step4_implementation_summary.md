> **Feature plan:** [Step 1](./private_messaging_live_navigation_step1_solution_assessment.md) · [Step 2](./private_messaging_live_navigation_step2_feature_description.md) · [Step 3](./private_messaging_live_navigation_step3_development_plan.md) · [Step 4](./private_messaging_live_navigation_step4_implementation_summary.md)

Debug mode: enabled

## Progress

- Planning commit: `a686e5a1`; branch: `feature/private-messaging-live-navigation`.

## Stage 1 - Confirmed navigation refresh

- Changes: Added session-aware, no-store `GET /api/feature_flags/navigation` using canonical navigation rendering and asset resolution. Messaging saves refresh only the Messages link; confirmed disable stops activity immediately. Refresh failures retain Saved feedback with a reload button. Restore keyboard focus after pending controls unlock.
- Verification: `php tests/run.php PrivateMessageFeatureFlagTest FeatureFlagsBehaviorTest` (6 passed); `node tests/browser/private_message_live_navigation.mjs` (disable, rejected save, refresh failure, focus/scroll, no repeated write); `node tests/browser/private_message_history_sync_browser.mjs` (passed, artifacts `/tmp/history-sync-browser-Dy4Rmr`). PHP syntax and `git diff --check` passed.
- Notes: Eligibility matrix covers approval, authentication, and members-only access. Real browser flow verified session resolution, no-store response, signed toggle and environment overrides. Enable lifecycle follows in Stages 2–3. No migration or deployment required.

## Stage 2 - Repeatable enable and unread startup

- Changes: Load missing canonical messaging scripts once, reset availability after server confirmation, disconnect the disabled observer, clear obsolete notices, and start/stop unread tracking with one listener set. Serialize toggle/reset operations per settings row and reject identity changes during refresh. Asset failures retain explicit reload recovery and replace the loading count with unavailable feedback.
- Verification: `node tests/browser/private_message_live_navigation.mjs` passed initially-disabled enable, repeated off/on, single asset loads, canonical link order, unread failure and asset failure, plus Stage 1 recovery checks. `php tests/run.php PrivateMessageFeatureFlagTest FeatureFlagsBehaviorTest PrivateMessageReadStateTest` passed 11 tests, including delayed disabled-response rejection and admitted-send confirmation. `git diff --check` passed.
- Notes: Browser testing exposed and resolved observer-driven hiding of the new link. Background sync restart is the remaining Stage 3 integration. Existing conversation pages retain reload-based recovery.
