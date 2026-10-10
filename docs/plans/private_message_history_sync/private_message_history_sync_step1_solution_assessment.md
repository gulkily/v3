# Private message history synchronization Step 1 solution assessment

> **Feature plan:** [Step 1](./private_message_history_sync_step1_solution_assessment.md) · [Step 2](./private_message_history_sync_step2_feature_description.md) · [Step 3](./private_message_history_sync_step3_development_plan.md) · [Step 4](./private_message_history_sync_step4_implementation_summary.md)

[Storage and synchronization findings](./private_message_history_sync_storage_findings.md) record the infrastructure review and local lock probe.

## Original Query

Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md. Assume that we'll start a new chat after.

## Understood Intent

Automatically bring newly approved keys up to date on private conversations. The user proposed privately transferring message IDs and their “passphrases”; the relevant OpenPGP secrets are per-message **session keys**, not user-managed passwords.

- **Settled policy:** approved same-account keys may access all sent/received history without another confirmation or manual key management.
- **Settled limitation:** authorization does not guarantee recoverability; an available device must retain suitable private/session keys. The server cannot manufacture access.
- **Future context, not a dependency:** a planned default authentication mode restricts approval to admins or same-username keys. Do not implement or assume it here; honor current approved-key membership.

## Problem

New messages include approved account keys, but old envelopes do not gain new recipients automatically; recovery needs an available device to transfer access without exposing secrets to the server.

## Option A Session key bundles as chat messages

An existing device encrypts batches of message IDs and session keys for the new key and delivers them through private messaging.

- Pros: close to the original proposal; potentially reuses delivery infrastructure.
- Cons: special self-delivery/discovery rules; risks chat clutter, unread changes, and recursively synchronizing transfer messages.

## Option B Dedicated encrypted history transfers

Existing devices provide encrypted, authenticated session-key transfers for the new key, separate from conversations; original messages remain unchanged.

- Pros: preserves envelopes/signatures; resumable multi-device contributions without private-key export or unread changes.
- Cons: needs private transfer/coverage storage, authenticated synchronization, and early cryptographic validation.

## Option C Reencrypted history copies

An existing device creates new encrypted copies of recoverable historical messages for the new key.

- Pros: straightforward recovery model using whole-message encryption.
- Cons: duplicates content/storage; complicates original authorship, signatures, and message identity.

## Recommendation

Choose **Option B**, with these planning constraints:

- Approval requests synchronization; existing devices contribute on visits, and new devices retrieve transfers through Messages. Simultaneous availability is unnecessary. Also discover missing coverage for already-approved keys.
- Use bounded, resumable batches for sent/received history. Reconcile coverage, not timestamps alone, including stale-key-list sends. Devices contribute partially; failed attempts do not prove permanent loss. Synchronized devices can help later devices.
- Authenticate and bind transfers to account, source/target keys, and original message/envelope. Recheck approval; prevent cross-account access and duplicate/replayed work. Keep plaintext, private keys, and unwrapped session keys out of server storage/logs and public/offline artifacts. Revocation cannot retract delivered access.
- Preserve signature verification, message identity/order, unread semantics, and drafts. Distinguish waiting, progress, verified restoration, and unavailable history; upload alone does not prove recovery.
- First validate bundled-library session-key transfer and original signature checks. Deliver approval → existing-device contribution → new-device reading with retry/progress within one day/eight stages; otherwise split into usable releases. Exclude counterpart-assisted recovery and authentication redesign.

## New chat handoff

Cycles 1–5 are merged into `main`, including compact unavailable-message groups. Start with the [master checklist](../private_messaging_usability_master_checklist.md), [approved-key resolver](../../../src/ForumRewrite/Messaging/ApprovedUserKeyResolver.php), [composer](../../../public/assets/private_messages.js), and [reader](../../../public/assets/private_message_reader.js). The resolver currently groups approved profiles by normalized username. The [OpenPGP.js session-key documentation](https://docs.openpgpjs.org/global.html#decryptSessionKeys) supports assessing Option B; its complete application protocol is not yet validated.

The user explicitly approved this assessment with **Approved Step 1** on 2026-10-10. Continue with [Step 2](./private_message_history_sync_step2_feature_description.md) under [FDP](../../fdp/FEATURE_DEVELOPMENT_PROCESS.md); implementation is not approved. At the user's earlier explicit request to commit progress before pausing, the original Step 1 assessment was included in a checkpoint commit on `main`, an exception to the usual uncommitted Steps 1–3 workflow. Keep subsequent planning changes uncommitted; create the implementation branch only after **Approved Step 3**. No history-sync implementation has started.

The subsequent [instance portability assessment](../instance_portability_gap_assessment.md) catalogs export gaps and feasibility. The backup-page wording and its regression coverage were updated separately; export protocols and storage changes remain proposals.
