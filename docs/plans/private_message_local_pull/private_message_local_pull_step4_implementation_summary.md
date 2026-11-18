> **Feature plan:** [Step 1](./private_message_local_pull_step1_solution_assessment.md) · [Step 2](./private_message_local_pull_step2_feature_description.md) · [Step 3](./private_message_local_pull_step3_development_plan.md) · [Step 4](./private_message_local_pull_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/private_message_local_pull`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 1.10** (revised from 0.95 at Stage 5; see Cycle Revision)

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

## Stage 4 - Pull messages, transfers and read state

**Original estimate: 0.20** (unchanged; scope note from Stage 2 applies)

- Changes:
  - `PrivateMessageStore::importEnvelope()` inserts under the original ID and returns added, existing or mismatch. It never replaces a local row. `importReadMark()` moves the local seen boundary forward through the existing `PrivateMessageReadState::mark()`.
  - `PrivateMessageHistorySyncStore::importTransfer()` inserts under the original transfer ID and returns added, existing or mismatch. Local transfers and items win.
  - New `ForumRewrite\Support\LocalMessagePull` pulls up to 10 export pages per local request. It saves progress in `state/private/local_pull_progress.json` (0600) and deletes the file once the pass ends. It refuses a sign-in that comes back as a different account.
  - `/local/remote` gains `challenge` and `pull`. A pull first checks that this clone has approved the key. It then signs in with the browser's fresh signature, checks approval on the source, and pulls. `Application` passes in the local stores and the progress path.
  - The page gains **Pull my messages from zenmemes.com**. It repeats challenge, sign and pull until the pass ends, with a running summary: new, already here, kept local, history keys.
- Verification:
  - `./v3 test LocalMessagePullTest PrivateMessageExportTest LocalDevelopmentImportTest`: 12 run, 12 passed. The new `LocalMessagePullTest` runs the real export service as the source. It covers original IDs, one transfer spanning two pages arriving once under its original ID, another member's mail staying out, a clean repeat, resume after a one-page interruption, a local same-ID copy being kept and reported, read state, and refusal of a different account. The relay test adds "pull before this clone knows the approval", which stops before any sign-in reaches the source.
  - `python3 tests/browser/local_development.py`: passed.
- Notes:
  - **New understanding:** each pass deletes its progress file at the end, so the next press rescans the whole mailbox. That is how transfers that arrive later for old messages get picked up, at the cost of re-reading envelopes that are already here. A personal mailbox stays small enough for that, and the export pages stay bounded.
  - The planned two-instance browser check, including pulling twice, runs with Stage 5's end-to-end check.
  - Retrospective stage-work assessment: **0.20**.

## Stage 5 - Verify locally, trust keys here, confirm on the source

**Original estimate: 0.20**

**Revised estimate: 0.35** (2026-11-18T06:40Z; changed scope; Claude, single exposed rater). Adds the one-click trust step the maintainer chose after the blocking discovery below.

- Changes:
  - `PrivateMessageHistorySyncStore::unverifiedFor()` lists pulled transfers to this key that are not yet covered locally. `sourcesFor()` lists their donor keys. `PrivateMessageStore::senderIdentitiesFor()` lists the keys that signed the account's messages.
  - `/local/remote` gains four actions:
    - `trust_candidates` offers this browser's key, the signers of pulled messages and transfer donors, but only keys the source approves and this clone has not approved. It reports separately any keys this clone has not imported yet.
    - `trust` rechecks each key against the source. It gives this browser's key a local approval seed, then approves the others with that key. Both go through the existing `LocalWriteService` writers, so the approvals stay in this clone.
    - `verify_list` sends each pulled transfer with its original envelopes, the donor key and the senders' keys.
    - `confirm` signs in fresh, confirms on the source in batches of 10, and records local coverage only after the source accepts a batch.
  - A pull now needs only that this clone knows the key, not that it has approved it.
  - The page:
    - After a pull, it lists the keys to trust and waits for one click on **Trust these keys here**.
    - It then verifies each transfer in the browser with `ForumPrivateMessageHistoryCrypto`: unwrap, digest, and the original sender's signature. It confirms what passed.
    - It follows the import form's source field and accepts `/local/?source=`. **Check again** reruns the key check.
    - A pull before the import ends with "Run Import above, then pull again."
  - New end-to-end check `tests/browser/local_message_pull.mjs`, with two real instances.
- Verification:
  - `node tests/browser/local_message_pull.mjs` passed. It runs with `playwright-core` resolvable; the run here used a temporary `tests/browser/node_modules` link, not committed. The flow:
    - Register through the relay, which hides the button once registered. Pull is unavailable before approval.
    - Approve on the source with Alice's old key. Her old device's natural visit donates 8 of 8.
    - **Replay:** pull before the import. It gets 8 new messages, then stops with "Run Import above".
    - Import, pull, then **Trust these keys here** lists 3 keys (this browser, old Alice, Bob). After trusting, the summary shows 8 confirmed and nothing unreadable.
    - The local store holds exactly 8 messages; Carol's message stays on the source. The source records 8 covered transfers for the new key.
    - A repeat pull reports 0 new and 8 already here.
    - At 390 px there is no overflow. Local Messages shows all 8 with a verified sender, and the page logged no errors.
    - Screenshots are in the run's temporary folder.
  - `./v3 test --smoke LocalDevelopmentImportTest LocalMessagePullTest PrivateMessageExportTest PrivateMessageHistorySyncTest PrivateMessageStoreTest`: 516 run, 516 passed.
  - `python3 tests/browser/local_development.py`: passed.
- Notes:
  - **Blocking discovery (resolved by the maintainer):** the public import deliberately excludes approvals (`docs/runbooks/instance_content_import.md`), so Step 2's "rerun the import so the clone learns the approval" was wrong. Local Messages needs an approved viewer and approved sender keys. The maintainer chose one-click trust from three options: one-click trust, automatic trust, and relaxed reading rules. See Enhancement Requests.
  - Your own key becomes a local root through its seed. Correspondents become ordinary approved members, approved by your key. None of this leaves the clone, since imports exclude approvals and seeds.
  - The browser key is already known locally before the import, because ordinary local pages publish it. Step 3 of the card is therefore an instruction about correspondents' keys, not a check mark.
  - Retrospective stage-work assessment: **0.40**. The revised 0.35 underestimated reworking the pull order.

## Enhancement Requests

- 2026-11-18T06:35Z, maintainer: "One-click trust (Recommended)". Asked after the discovery that imports carry no approvals. Disposition: implemented in Stage 5. This is a renewed scope approval for a dev-clone trust step, covering only keys that the source approves.

## Cycle Revision

2026-11-18T06:40Z: **1.10 projected total**, up from 0.95. Reason: changed scope (the local trust step), approved by the maintainer. Claude, single exposed rater; earlier snapshots kept above.

## Stage 6 - Failure messages and documentation

**Original estimate: 0.10** (unchanged)

- Changes:
  - Each failure from Step 2 has a message that names its cause and the next step:
    - Unregistered or unapproved key: card steps and the Register button.
    - Approved but not imported here: "Run Import above, then pull again."
    - Source unreachable: "Check the connection and retry."
    - Source without the endpoint: "does not support pulling messages yet. Deploy a newer version there."
    - Expired sign-in, retired key or rate limit: the source's own error, plus "Press Pull to resume."
    - Interrupted pull: "Press Pull to resume."
    - Key not approved on the source at pull time: "Approve it there, then pull again."
  - The Stages 3–5 code already carries these messages; this stage adds tests and documentation.
  - Docs:
    - New concept [participant mailbox export](../../concepts/participant_mailbox_export.md).
    - "Messages pull" entry in [local development import](../../concepts/local_development_import.md).
    - "Pulled history" entry in [private-message history](../../concepts/private_message_history.md).
    - "Export your own private messages" section in `docs/reference/agent_api.md`.
- Verification:
  - `./v3 test LocalDevelopmentImportTest`: 5 run, 5 passed. The new failure test covers unreachable, old source, expired sign-in, retired key, rate limit, an unapproved sign-in and an unregistered key.
  - Relative links in the four documents resolve. `git diff --check` is clean.
- Notes:
  - The page's message for an unapproved sign-in comes from the controller and has no direct test. Its inputs (`approved=0`) are tested, and the browser check covers the earlier "no Pull before approval" state.
  - Retrospective stage-work assessment: **0.10**.
