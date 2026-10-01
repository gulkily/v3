# Offline Participation UI Refinement Step 4 Implementation Summary

## Stage 1 - Define signed offline intents and timestamps
- Changes:
  - Extended local Outbox items with action time, integration time, and a private signed-intent envelope while keeping those fields out of safe summaries.
  - Added a canonical offline-intent builder that binds a stable ID, action, target, action time, author identity, and payload before requesting a detached browser signature.
- Verification:
  - `node --check public/assets/outbox_intent.js` and PHP syntax checks passed.
  - `php tests/run.php OfflineOutboxStateTest OfflineOutboxIntentTest` — 4 run, 4 passed.
- Notes:
  - This stage only creates a local signed envelope; reaction API verification and UI capture arrive in later stages.
