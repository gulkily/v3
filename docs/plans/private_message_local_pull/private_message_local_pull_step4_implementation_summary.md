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

## Stage 2 - Transfers in the export

**Original estimate: 0.10** (unchanged)

- Changes:
  - `PrivateMessageHistorySyncStore::transfersFor()` returns transfers whose destination is the account, for the page's messages. Each carries its ID, source, target, ciphertext and only the items for those messages.
  - `PrivateMessageMailboxService::export()` takes an optional sync store and adds `transfers` to each page. `Application` passes a lazily opened sync store to `PrivateMessageApiController`, so other mailbox routes never open sync storage.
  - A new test in `PrivateMessageExportTest` covers same-account and sender-assisted transfers. Another account's transfer must not appear, and a later page must carry only its own items.
- Verification:
  - `./v3 test PrivateMessageExportTest PrivateMessageHistorySyncTest PrivateMessageApiRoutingTest`: 33 run, 33 passed.
- Notes:
  - **New understanding for Stage 4:** `upload()` derives a transfer ID by hashing its exact item list, which the export cannot reproduce once a transfer spans pages. Stage 4 will insert transfers under their original ID instead of reusing `upload()`. The scope is unchanged, and the Stage 4 estimate stays 0.20.
  - A sync-store failure makes the export return an error instead of silently leaving transfers out, so a pull never stores messages and misses their transfers.
  - Transfer size stays bounded by history sync's 100,000-byte upload limit. No separate export cap was added.
  - Retrospective stage-work assessment: **0.10**.

## Stage 3 - Register the local key on the source

**Original estimate: 0.20** (unchanged)

- Changes:
  - New `ForumRewrite\Support\LocalDevelopmentRemote`. It forwards only the calls in its `PATHS` list to the source address, keeps session cookies in the object only, and never follows redirects. The list also holds the export and acknowledge calls for Stages 4 and 5.
  - New `LocalDevelopmentRemoteController` at `POST /local/remote`. It sits behind the same loopback guard, token and origin check as the public import, and takes the actions `key_status`, `prepare_identity` and `create_identity`. `key_status` reads the key's username from the key itself, then reports four things: approved keys under that name on the source, whether the source knows and approves this key, whether this clone has the approval, and the key's User Access link on the source.
  - `Application` routes `/local/remote` and shares one `localDevelopmentImport()` factory with the page.
  - The local development page gains a **Your messages** card. It checks the browser key and walks through three steps: register, approve on the source, and import. **Register this key on zenmemes.com** signs the prepared record in the browser and relays it. "Identity already exists" counts as registered.
- Verification:
  - `./v3 test LocalDevelopmentImportTest`: 4 run, 4 passed. The two new tests cover the wrong token, a foreign origin, an unknown action, refusal outside development mode, a full register run and repeat register against a recording source, a real gpg key through `key_status`, refusal of unlisted paths and bad source addresses, and session cookies that reach only calls made after sign-in.
  - `python3 tests/browser/local_development.py`: passed, after adding the new runtime files to its list. The first run failed because the new "no key yet" note used `role=alert` and clashed with the import's alert. That note is now plain status.
- Notes:
  - The planned browser check against a second real instance is moved to Stage 5, which needs a second instance anyway. That check will cover registration too. A recording stand-in source covers relay behavior here.
  - Retrospective stage-work assessment: **0.20**.
