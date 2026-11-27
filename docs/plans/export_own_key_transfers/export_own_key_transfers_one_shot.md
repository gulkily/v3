# Export only the signed-in key's history keys

Cycle v3/export_own_key_transfers · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/export_own_key_transfers`; host ledger [export_own_key_transfers.md](../effort_ledger/export_own_key_transfers.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.10**

Status: planned; next permitted action: implement.

## Original request

After [export_readable_json](../export_readable_json/export_readable_json_one_shot.md) shipped, Ilya's pull reported: "https://zenmemes.com sent a response too large for the relay (GET /api/private_messages/export, 8 MiB or more). Press Pull to resume." (2026-11-27). This continues the authorized work of fixing the pull: a follow-up one-shot on a new branch from `main` with its own PR.

## Problem

`PrivateMessageHistorySyncStore::transfersFor()` returns every history-sync transfer whose destination is the account. That covers every target key, not only the key that signed in. Ilya's envelopes are encrypted to about 60 keys. History sync makes transfers per donor–target pair in batches of up to 10 messages, so one export page carries hundreds of transfers and exceeds 8 MiB. The pull, and the local clone reading the mail, only use transfers to the local key, which is the signed-in key.

## Outcome and work type

Application change. The export includes only transfers whose target is the signed-in key. Exporting from the browser on zenmemes.com shows the transfers for that browser's key. Nothing else in the export changes.

Not authorized:
- Raising the relay's 8 MiB limit.
- Changing history sync itself.

## Risks

- **A transfer the pull needs is left out.** The pull verifies, trusts and reads only transfers to its own key (`unverifiedFor` and `sourcesFor` already filter by target). Sender-assisted transfers to that key are still included.
- **A page is still too large.** With one target, a message has a few transfers at most. The relay's error stays as the safety net.

## Reuse decisions

Extend `transfersFor()` with a target filter. The target comes from the session's `identity_id` in `PrivateMessageMailboxService::export()`, never from the request.

## Concepts touched

[Participant mailbox export](../../concepts/participant_mailbox_export.md): only the signed-in key's transfers.

## Verification plan

- Update and extend `PrivateMessageExportTest` with transfers to two keys of the same account. Rerun `LocalMessagePullTest`, `LocalDevelopmentImportTest` and the smoke set. Run the two-instance browser check.
- **Replay:** after deploy, Ilya presses Pull. Until he does, the original failure stays unverified here.

## Study checkpoint

Intake 2026-11-27T19:20Z, scope v1, R1, P2 standing enrollment; parent `v3/export_readable_json`. Claude, single exposed rater; diagnosed before rating. **0.10 projected total.**

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*
