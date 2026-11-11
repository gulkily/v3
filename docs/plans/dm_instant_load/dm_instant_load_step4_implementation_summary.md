# DM instant load Step 4 implementation summary

> **Feature plan:** [Step 1](./dm_instant_load_step1_solution_assessment.md) · [Step 2](./dm_instant_load_step2_feature_description.md) · [Step 3](./dm_instant_load_step3_development_plan.md) · [Step 4](./dm_instant_load_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/dm_instant_load`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled. Stage estimates below are copied from the approved Step 3 plan; Claude single exposed rater throughout.

**Cycle estimate: 0.70**

Branch: `feature/dm_instant_load`, from `origin/main` at `f13dd20f`. Planning commit `6e138ddc`.

## Study snapshots

- Step 4 entry, 2026-11-11 UTC: 0.70, Step 3 reaffirmed unchanged. Recorded with the Stage 1 commit; the summary was created after the planning commit but alongside Stage 1 work rather than strictly before it.

## Stage 1 - Faster live reads

**Original estimate: 0.15**

- Changes:
  - `private_message_reader.js`: the browser's private key and each sender key are parsed once per page and reused; a failed parse is dropped so retry parses again.
  - Conversation and mailbox pages start loading OpenPGP when the reader binds, if this browser has a key.
  - Cards start reading newest first. All reads still run together and settle waits for every card.
- Verification:
  - `./v3 test --smoke PrivateMessageReaderTest PrivateMessageReleaseIsolationTest`: 451 passed.
  - `./v3 test PrivateMessageHistoryCryptoTest` (runs the history reader harness): 8 passed.
  - Messaging browser suite through `checkChatLayout`: passed, with two failures that also occur on main bypassed (Users link step; history-sync `state()` assertion).
  - Before/after timing on a 50-message fixture: not measured; no such fixture exists yet. Deferred to Stage 6 on the maintainer's phone.
- Notes:
  - The identity-change guard in `decryptEnvelope` still compares the stored key against the one read at the start, so a cached parse never decrypts for a replaced identity.
- Estimate details: original scope as Step 3; uncertainty: newest-first vs. history anchoring, which `checkChatLayout` covered; revisions: none.

**Retrospective stage-work assessment: 0.10** — smaller than planned because reads already ran concurrently, so reordering was one line.

*— Claude · 2026-11-11 UTC · Model: Claude Opus 5.5*
