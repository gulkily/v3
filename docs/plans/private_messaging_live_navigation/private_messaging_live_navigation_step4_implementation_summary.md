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

## Stage 3 - Bounded history-sync restart

- Changes: Added repeatable sync start/stop, one listener set, cancelled-run draining before restart, and generation checks after asynchronous work. Old candidate completions cannot evict new cached work. Preserve durable scheduling preference and original private storage; reset transient counters on identity changes.
- Verification: `node tests/browser/private_message_history_scheduling.cjs` passed budget, fairness, visibility, queued shutdown and disable/enable during a delayed old response. `php tests/run.php PrivateMessageFeatureFlagTest PrivateMessageHistorySyncTest` passed 17 tests. Live-navigation browser checks passed. Full history browser journey passed (`/tmp/history-sync-browser-WI4PiT`), now verifying unread and sync resume after signed enable without reload. `git diff --check` passed.
- Notes: Existing eight-batch/time/concurrency budgets remain in force. Re-enable waits for an already-admitted cancelled run to drain; obsolete work cannot upload, acknowledge, or publish current progress. No messaging endpoint or storage schema changes.

## Stage 4 - Operator journey and release verification

- Changes: Extended the signed operator browser journey for reset, initially-disabled asset startup, repeat toggles across every available fixture theme, disabled traffic suppression, and following Messages. Added all-profile canonical navigation parity and delayed identity/concurrent-submit checks. Documented immediate settings-page behavior and reload fallback in README, messaging architecture, API help, and flag description. Validation refinements keep unavailable badge accessibility consistent, return reset focus to the switch, and avoid adding a draft notice/layout shift for an intentional settings-page disable. Repaired two source links after plan organization.
- Verification: Both full messaging browser journeys passed: `node tests/browser/private_message_list_browser.mjs` (`/tmp/private-message-browser-09bcEW`) and `node tests/browser/private_message_history_sync_browser.mjs` (`/tmp/history-sync-browser-1zguHZ`). Focused live-navigation, availability, and scheduling browser checks passed. Final focused PHP run passed **46/46** (`/tmp/live-navigation-final-focused-recheck.log`), including the added profile parity test. PHP/JS syntax, plan/debug links, file scope, and `git diff --check` passed.
- Full-suite evidence: `php tests/run.php` ran **1,126 tests: 1,118 passed, 8 failed** (`/tmp/live-navigation-full-tests.log`). This run started before the final profile-parity test was added; that test passed in the final focused run. All eight failures reproduced unchanged at pre-feature commit `865e6daf` with matching private configuration (`/tmp/live-navigation-baseline.log`): two LocalAppSmoke tests, two OfflineSnapshotBootstrap tests, two OfflineSnapshotPublishCommand tests, QDB welcome news text, and queued agent reply processing. Removed the temporary baseline worktree after comparison.
- Notes: The existing concurrent sync-store test failed once during a parallel focused run, then passed both in the full suite and on isolated/final focused reruns (`/tmp/live-navigation-concurrency-recheck.log`). No test or storage behavior was changed to mask it. The feature contract is complete; no schema migration, external service, production deployment, push, or merge is required or performed.
