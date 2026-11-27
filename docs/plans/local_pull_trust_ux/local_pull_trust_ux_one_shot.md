# Messages pull: confirm once per message, responsive trust, grouped key list

Cycle v3/local_pull_trust_ux · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_pull_trust_ux`; host ledger [local_pull_trust_ux.md](../effort_ledger/local_pull_trust_ux.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.20**

Status: planned; next permitted action: implement.

## Original request

Ilya, 2026-11-27, after [export_own_key_transfers](../export_own_key_transfers/export_own_key_transfers_one_shot.md) made the pull work: "the 'trust these keys here' button needs better responsiveness, it just hangs while the process happens. also, the ui for the keys being displayed needs improvement. i got a new error message: https://zenmemes.com: Invalid history item. Press Pull to resume." Then: "on messages page, after i confirmed the keys, i was able to read the messages."

## Authorization

A follow-up one-shot on a new branch from `main` with its own PR, limited to the three reported issues on the local start page's **Your messages** card. Not authorized:
- Changing history sync on zenmemes.com.
- Changing how keys are approved, beyond splitting the work into one request per key.

## Problems

- **Invalid history item.** zenmemes.com's acknowledge rejects a batch that names the same message twice (`PrivateMessageHistorySyncService::items()`). A message can have several valid transfers to the local key, one per donor device, and the pull confirms every verified one. Locally, `unverifiedFor()` keeps offering a message's other transfers once one is covered, so every pull would retry them.
- **Trust hangs.** One request approves every key in turn: a source check plus a local write for each. With about 60 keys this takes minutes with no visible change, because the button only gets `aria-disabled`.
- **Key list.** One line per key, about 60 lines of usernames and fingerprint fragments.

## Outcome and work type

Application change.
- **Confirm once per message.** The browser and the local server confirm one verified transfer per message. Once a message is covered locally, `unverifiedFor()` leaves out its other transfers.
- **Trust shows progress.** The page sends one trust request per key, this browser's key first. The button reads "Trusting 12 of 61…", is busy and disabled, and the status line follows along. A failure keeps the keys already trusted and offers the button again for the rest.
- **Keys grouped by person.** One row per username with its key count, for example "ilyag: 31 keys, including this browser". Each row is a `<details>` that opens to the short fingerprints. It works without extra JavaScript.

## Risks

- **A message ends up unconfirmed.** Keep the first verified transfer per message, which is enough for the source's coverage.
- **Partial trust.** Each key commits on its own, and a retry continues from where it stopped, because already-approved keys are skipped on the server.

## Reuse decisions

- The `trust` action is reused with one key per request.
- `unverifiedFor()` is extended in place.
- Native `<details>` instead of a new disclosure component.
- `aria-busy` on the button, following `inbox_catch_up.js`.

## Concepts touched

[Local development import](../../concepts/local_development_import.md), "Messages pull" entry: trust progress and one confirmation per message.

## Verification plan

- PHP tests for the confirm dedupe and the `unverifiedFor()` change. Rerun the smoke set.
- Extend the two-instance browser check: give one message two donors (the old device and Bob's sender assistance), assert the grouped list and the progress text, and expect no "Invalid history item".
- Ilya's account after deploy.

## Study checkpoint

Intake 2026-11-27T19:20Z, scope v1, R1, P2 standing enrollment; parent `v3/export_own_key_transfers`. Claude, single exposed rater; diagnosed before rating. **0.20 projected total.**

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*
