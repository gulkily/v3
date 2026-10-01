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

## Stage 2 - Verify and integrate signed reactions
- Changes:
  - Added a signed-reaction endpoint that verifies the browser's detached signature, binds its target/payload/author, and accepts only Likes.
  - Persisted signed action time and stable intent ID on new thread-label and post-reaction records while retaining server-created time as the integration time used by existing derived state.
- Verification:
  - PHP syntax checks passed.
  - `php tests/run.php CanonicalRecordParsersTest WriteApiSmokeTest::testApplyThreadTagApiDuplicateShortCircuitsWithoutNewRecord WriteApiSmokeTest::testApplyPostTagApiDuplicateShortCircuitsWithoutNewRecord WriteApiSmokeTest::testSignedReactionApiVerifiesCommentLikeAndRetainsBothTimes` — 38 run, 38 passed.
- Notes:
  - Existing unsigned reaction endpoints and legacy records remain supported; offline UI capture switches to the signed endpoint in Stage 4.
