> **Feature plan:** [Step 1](./private_message_local_pull_step1_solution_assessment.md) · [Step 2](./private_message_local_pull_step2_feature_description.md) · [Step 3](./private_message_local_pull_step3_development_plan.md) · [Step 4](./private_message_local_pull_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/private_message_local_pull`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.95**

## Completion Contract

- **Normal entry:** the local development page from `./v3 start`.
- **End-to-end outcome:** register the local key, approve it on zenmemes.com, rerun the public import, then pull. Local Messages shows verified messages or "not readable yet". zenmemes.com records verified transfers as confirmed.
- **Required recovery:** each failure from Step 2 shows its cause and next step, and pressing the button again resumes.
- **Deployment:** Ilya deploys the export endpoint to zenmemes.com with the normal SSH deploy. Nothing else changes in production.
- **Release condition:** Stage 7 replays on Ilya's laptop against production, with each failure case passing.

## Key Risks

- **High risk:** data. The export could return another member's mail. Validation: Stage 1 tests a three-member fixture before any client exists. Mitigation: select by the signed-in account only, never by a request parameter.
- **High risk:** data. An import could overwrite local messages. Validation: Stage 4 tests a same-ID, different-envelope message. Mitigation: insert only when missing; report mismatches.
- **Relay session.** The local PHP server handles one request at a time, so a zenmemes.com session cannot persist in memory across requests. Validation: Stage 3 relays from loopback to a test instance. Mitigation: every relayed action signs in fresh with a new challenge inside one local request, then drops the session. The browser signs without prompting, so the member does nothing extra. Step 2's "keep the session only for that pull" holds, and nothing reaches disk.
- **Transfers fail locally.** Validation: Stage 5 pulls a two-device test account into a fresh clone. Mitigation: confirm only what the local reader verified.
- **Rollback:** the export endpoint is additive. Reverting it leaves messages and transfers untouched.

## Stage 1

**Estimate: 0.15**

- Goal: zenmemes.com exports the signed-in member's envelopes with read state, in readable resumable batches.
- Dependencies: none.
- Expected changes: new member route `GET /api/private_messages/export?after=<message_id>`, ordered by creation time, then ID. `PrivateMessageMailboxService::export(array $viewer, string $after, int $limit): array` returns messages, each with its read flag, plus `next` (the last ID, or null). The position is a plain message ID, not an opaque token. Register the route in `ApiRouteCatalog`.
- Verification approach: a new focused PHP test with three members, covering isolation, ordering, resume from `after`, the last batch and the read flag. Rerun `PrivateMessageApiRoutingTest`.
- Estimate details: driver: isolation and ordering tests; uncertainty: low.
- Risks or open questions:
  - Impact: leaked mail. Early warning: the isolation test. Mitigation: account from session only.
- Canonical components/API contracts touched: `PrivateMessageApiController`, `PrivateMessageMailboxService`, `PrivateMessageStore` (new batched read beside `inboxFor` and `sentBy`), `PrivateMessageReadState`, `ApiRouteCatalog`.

## Stage 2

**Estimate: 0.10**

- Goal: each export batch also carries the transfers addressed to the member's keys for those messages.
- Dependencies: Stage 1.
- Expected changes: `PrivateMessageHistorySyncStore::transfersFor(string $account, list<string> $messageIds): array` returns transfer ID, source, target, ciphertext and items. Batch size stays within history sync's byte limit.
- Verification approach: extend the Stage 1 test with a transfer to another account's key, which must not appear. Rerun `PrivateMessageHistorySyncTest`.
- Estimate details: driver: byte bounding; uncertainty: low.
- Risks or open questions: none beyond Stage 1.
- Canonical components/API contracts touched: `PrivateMessageHistorySyncStore` (read added), the Stage 1 export route.

## Stage 3

**Estimate: 0.20**

- Goal: the local development page registers the local key on zenmemes.com.
- Dependencies: none; it runs in parallel with Stages 1 and 2.
- Expected changes: a local relay (`LocalDevelopmentRemote`) that forwards fixed calls to the configured source and never forwards any other path. A **Register this key** section shows the username and its approved-key count from `get_username`. It relays `prepare_identity`, the browser signs, and it relays `create_identity`. "Identity already exists" counts as success. The page then links to the key's page on zenmemes.com.
- Verification approach: extend `LocalDevelopmentImportTest` for the loopback guard, token, allowed paths and existing-identity success. A browser check registers against a second local instance.
- Estimate details: driver: relay guard; uncertainty: dev-server HTTPS client behavior.
- Risks or open questions:
  - Impact: an open proxy on loopback. Early warning: the allowed-path test. Mitigation: a fixed path list behind the existing guard.
- Canonical components/API contracts touched: `LocalDevelopmentPageController`, `LocalDevelopmentImport` (guard reused), `templates/pages/local_development.php`, `public/assets/local_development.js`, signing in `private_site_auth.js`, `prepare_identity` and `create_identity` (unchanged).

## Stage 4

**Estimate: 0.20**

- Goal: **Pull my messages** stores envelopes, transfers and read state locally, resumably.
- Dependencies: Stages 1–3.
- Expected changes: the relay gains sign-in (challenge, browser signature, `authenticate_identity`) plus export batches, all within one local request. `PrivateMessageStore::importEnvelope(array $row): string` returns added, existing or mismatch. Transfers go in through the existing `PrivateMessageHistorySyncStore::upload`, whose content-hash ID reproduces the original. A local private progress file keeps the last ID, so a press after an interruption resumes. The summary counts new, skipped and mismatched.
- Verification approach: a PHP test for import outcomes, including mismatch and repeat. A browser check pulls from a second local instance twice, the second pull reporting zero new.
- Estimate details: driver: resume and mismatch handling; uncertainty: request time limits on long histories.
- Risks or open questions:
  - Impact: overwritten local mail. Early warning: the mismatch test. Mitigation: insert-only.
  - Impact: a timeout mid-pull. Early warning: a large-fixture run. Mitigation: a bounded batch count per press, with progress saved.
- Canonical components/API contracts touched: `PrivateMessageStore::storeEnvelope` (wrapped), `PrivateMessageHistorySyncStore::upload` (reused), `PrivateMessageReadState`, `AuthApiController` (unchanged).

## Stage 5

**Estimate: 0.20**

- Goal: the pull verifies pulled messages locally and confirms verified transfers on zenmemes.com.
- Dependencies: Stage 4.
- Expected changes: after storing, the page runs the existing local reader and history crypto over pulled messages. The existing local acknowledge records local coverage. A relayed `acknowledge` then sends only the locally covered items, after a fresh sign-in. The summary adds confirmed and not readable yet.
- Verification approach: a two-device fixture with one covered and one uncovered message. Only the covered transfer is confirmed on the source. Rerun `PrivateMessageHistorySyncTest`.
- Estimate details: driver: reusing browser verification outside Messages; uncertainty: the coordinator's coupling to the Messages page.
- Risks or open questions:
  - Impact: confirming bad transfers. Early warning: the uncovered case. Mitigation: confirm from local coverage only.
- Canonical components/API contracts touched: `private_message_history_crypto.js`, `private_message_reader.js`, `PrivateMessageHistorySyncController` acknowledge (unchanged, relayed), `PrivateMessageHistorySyncStore` coverage.

## Stage 6

**Estimate: 0.10**

- Goal: every Step 2 failure names its cause and next step, and the docs describe the feature.
- Dependencies: Stages 3–5.
- Expected changes: messages for unregistered, unapproved, approved-but-not-imported, unreachable, missing endpoint, expired sign-in and interrupted pull. Update the [local development import](../../concepts/local_development_import.md) and [private-message history](../../concepts/private_message_history.md) concepts. Create a *participant mailbox export* concept. Add the export to `docs/reference/agent_api.md`.
- Verification approach: a test per failure message; check links and `git diff --check`.
- Estimate details: driver: copy for seven states; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: the local development page, concept entries and the API reference.

## Stage 7

**Estimate: 0.15**

- Goal: release and replay the known-user conditions.
- Dependencies: Stages 1–6, plus merge and deploy by Ilya.
- Expected changes: none to code, unless the replay finds a defect. Record evidence in the Step 4 summary, and write the ledger entry.
- Verification approach: on Ilya's laptop against production, replay unregistered, unapproved and not-imported keys, plus not readable yet. Then do a full pull, compare message counts, and check that a repeat pull reports zero new. Check confirmed transfers on zenmemes.com. Fixture passes alone do not count.
- Estimate details: driver: coordinating with production; uncertainty: Ilya's availability and history coverage.
- Risks or open questions:
  - Impact: release blocked on donor coverage. Early warning: the not-readable count. Mitigation: visit zenmemes.com on an existing device first.
- Canonical components/API contracts touched: none new.

## Concepts Touched

[Local development import](../../concepts/local_development_import.md) (changed), [private-message history](../../concepts/private_message_history.md) (changed note), [approval acceptance](../../concepts/approval_acceptance.md) (used unchanged), *participant mailbox export* (created in Stage 6).

## Study Checkpoint

2026-11-18T05:50Z; scope v2; starting revision `6bdc52d7`; reference R1 (hashes not re-verified this session); P2 standing enrollment. Claude, single exposed rater.

- Step 3 handoff: **0.95 projected total**, reaffirmed from Step 2 v2. Fresh sign-in per relayed action replaces a held session at no extra scope. Stage estimates are not additive. Not hours or measured labor; no approval to advance.
- Ledger update pending: lands with the planning commit in Step 4.

Status: Step 2 v2 approved by the maintainer ("Approved step 2", 2026-11-18T05:47Z).

*— Claude · 2026-11-18 UTC · Model: claude-opus-5-5*
