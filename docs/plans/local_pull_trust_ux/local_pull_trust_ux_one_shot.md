# Messages pull: confirm once per message, responsive trust, grouped key list

Cycle v3/local_pull_trust_ux · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_pull_trust_ux`; host ledger [local_pull_trust_ux.md](../effort_ledger/local_pull_trust_ux.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.20**

Status: implemented, PR open; next permitted action: maintainer merge, deploy, then pull again.

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

## Changes

Planning commit `56bfc61e`.

- **Confirm once per message.**
  - `verifyPage()` keeps one verified transfer per message.
  - The `confirm` action deduplicates by message as well, keeping the first.
  - `unverifiedFor()` treats a message as done once any transfer covers it with the same digest.
- **Trust progress.**
  - The page sends one `trust` request per key, this browser's key first.
  - The button reads "Trusting N of M…" with `aria-busy`, and the status line follows. A failure keeps what was trusted and says "Press Trust to continue."
  - The candidate and trust limits went from 50 to 200 keys, because Ilya's account alone has about 60.
- **Key list.**
  - One `<details>` per username, for example "alice: 2 keys, including this browser". It opens to the last 16 hex digits of each fingerprint (the full one in `title`), with a `chip` labelled "this browser".
  - New `.local-pull-keys` rules in `site.css` remove the bullets and keep fingerprints on one line.
  - The intro text is shorter.
- The concept entry "Messages pull" is updated.

## Verification

- `./v3 test --smoke LocalMessagePullTest LocalDevelopmentImportTest PrivateMessageExportTest PrivateMessageHistorySyncTest`: 536 run, 536 passed. It includes the new `testAMessageCoveredByOneDonorIsNotOfferedAgainFromAnother`. The full suite was not run.
- `node tests/browser/local_message_pull.mjs`: passed. The check now has Bob's device donate as well, so his messages carry two history keys for the new key, and the pull still confirms without "Invalid history item". It asserts the two person rows, and the button showing "Trusting 1/2/3 of 3…" while busy. Screenshots of the key list were checked at 390 px and 1100 px, with no overflow.
- The same check on the code before this fix fails at the new key-list assertion, before it reaches the confirmation, so it doesn't show the old failure on its own. The new store test covers the local side, and the server deduplicates as well.
- Only the default theme was checked. The list uses plain text, `details` and the existing `chip`.
- **Unverified:** Ilya's account. That needs a pull after deploy.

## Discovered, not in scope

On Messages, list previews of messages readable only through history show "Couldn't decrypt preview" about half the time after a reload. The conversation page always reads them, and visiting it makes the list previews appear. The list uses only history keys already loaded on the page, and it doesn't appear to keep them cached. This is a separate cycle in the Messages list, proposed to Ilya.

## Outcome

Delivered as planned. Completion commit pending; PR to follow.

## Study closeout

2026-11-27T19:55Z: completion, acceptance pending. Retrospective **0.25**: the key-list styling took another round after the first screenshot.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*
