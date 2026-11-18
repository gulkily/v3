> **Feature plan:** [Step 1](./private_message_local_pull_step1_solution_assessment.md) · [Step 2](./private_message_local_pull_step2_feature_description.md) · [Step 3](./private_message_local_pull_step3_development_plan.md) · [Step 4](./private_message_local_pull_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/private_message_local_pull`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.95**

Branch `feature/private_message_local_pull` from `origin/main` `31e16b06`, which is newer than the planning baseline `6bdc52d7`. Planning commit `1f3a8157`.

## Step 4 Entry

2026-11-18T05:55Z: Step 3 projection reaffirmed, **0.95 projected total**, before implementation. Original stage-work estimates copied from Step 3, not re-rated: Stage 1 0.15, Stage 2 0.10, Stage 3 0.20, Stage 4 0.20, Stage 5 0.20, Stage 6 0.10, Stage 7 0.15. Claude, single exposed rater; R1 hashes not re-verified.

## Stage 1 - Member export of own messages with read state

**Original estimate: 0.15** (unchanged)

- Changes:
  - `PrivateMessageStore::exportPage()` returns the account's sent and received envelopes, oldest first, 25 per page and at most 2 MiB of ciphertext. Each received message carries `read`, using the existing baseline and seen boundaries.
  - `PrivateMessageMailboxService::export()` takes the account from the approved viewer only, and validates the position.
  - New member route `GET /api/private_messages/export?after_time=&after_id=` in `PrivateMessageApiController`, `Application` and `ApiRouteCatalog`. The position is the last message's creation time and ID, in plain text.
  - New `tests/PrivateMessageExportTest.php`, registered in `tests/run.php`. Added the route to `PrivateMessageApiRoutingTest` and to the disabled-namespace list in `PrivateMessageFeatureFlagTest`.
- Verification:
  - `./v3 test --smoke PrivateMessageExportTest PrivateMessageApiRoutingTest PrivateMessageFeatureFlagTest PrivateMessageStoreTest PrivateMessageMailboxServiceTest`: 497 run, 497 passed. The stray warnings in the output come from smoke tests' own fixtures and appear on passing runs.
  - The isolation test covers three members and checks that neither other conversation appears. The disabled flag returns 403 for the new route without touching private storage.
- Notes:
  - Ordering is by creation time, then ID, as Step 3 planned. Local rowids then follow time order, which the read-state import in Stage 4 relies on.
  - Retrospective stage-work assessment: **0.15**.

*— Claude · 2026-11-18 UTC · Model: claude-opus-5-5*
