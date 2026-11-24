Cycle: `v3/dm_verify_reason`; Mode: one-shot; Record/index: [plans index](../README.md).

Debug mode: disabled

Effort study: `railspike-effort` / `v3/dm_verify_reason`; host ledger [dm_verify_reason.md](../effort_ledger/dm_verify_reason.md). Standing P2 enrollment.

Extended estimates: disabled (no alternatives or work units in this cycle)

**Cycle estimate: 0.15**

Status: implemented; PR open; next permitted action: maintainer review.

Original request (2026-11-24): "can you please make two one-shot fixes for the things we've uncovered so far? (the unclear message, and the history sync spam)". This cycle is the unclear message; history sync is separate and waits for the maintainer's go-ahead on the approach.

Background: a DM from `claude` showed "Couldn’t verify this message" with the Details text "The sender signature could not be verified." Running the reader's decrypt by hand in the maintainer's console gave OpenPGP.js's real error: "Signature creation time is in the future". The signature was made at 16:54:09 UTC (the server's `created_at` agrees), so the reading device's clock or time zone put "now" earlier than that. The reader threw that reason away.

Authorization: direct request; one-shot per AGENTS.md. Exclusions: no change to what counts as verified (a future-dated signature still fails, no clock tolerance), no change to message kinds that other code reads, no history-sync changes, no server changes.

Outcome: when a received message's signature can't be verified, Details says why in plain words:
- Signed after this device's "now": names the signing time and the device's time, and says to check the date, time and time zone, then Retry. The short line reads "Dated ahead of this device's clock" instead of "Couldn’t verify this message".
- Signed by a key that isn't one of the sender's approved keys: says so, with the key ID.
- Any other verification failure: keeps the existing sentence and adds OpenPGP's own error text.

Approach and risks: in `public/assets/private_message_reader.js`, when `signature.verified` rejects, look at the error and the signature's key ID against the verification keys already parsed, and return `kind: "bad-signature"` with a `reason` (`clock`, `unknown-key`, `invalid`) and a specific `message`. `problemLabel` uses the reason for the short line. Keeping the kind means the conversation list preview (`private_message_list.js`), the cache rule (only `verified` is cached) and the history reader tests keep working. Risk: OpenPGP's error wording changes between versions; the clock check also compares the signature's creation time with `Date.now()`, so it doesn't rely on the wording alone.

Reuse decisions: existing reader result shape, `problemLabel`, `presentState` Details and Retry; existing `PrivateMessageReaderTest` node harness. No new component. Concepts touched: none.

Completion conditions: a future-dated signature gives the clock reason and message; a wrong-key signature gives the unknown-key reason; a valid one still verifies; nothing else changes kind.

Verification and recovery plan: extend `PrivateMessageReaderTest` with a future-dated signature (OpenPGP `date` option) and keep the wrong-key case; run `./v3 test --smoke PrivateMessageReaderTest` plus the browser reader tests that already exist for the reader. Recovery for the user: fix the clock, press Retry (unchanged control).

Starting revision and branch: `fix/dm-verify-reason` from `origin/main` `2d7805c`.

Planning commit: `10fe328` (plan and index only; first made as `35f35ff`, then rebased onto `origin/main` `b4a4eec`).

Dated execution history:
- 2026-11-24: `signatureProblem` added to `private_message_reader.js`; `problemLabel` takes the reason. Verification now settles every signature with `Promise.allSettled` before choosing the first failure (the old `Promise.all` gave the same pass/fail, but a sequential loop would have left later rejections unhandled).
- 2026-11-24: the clock check uses the signature packet's `created` time against `Date.now()`, with OpenPGP's "in the future" wording as a fallback.

Outcome: delivered as planned. A future-dated signature shows "Dated ahead of this device’s clock" with both times in Details; a signature from a key outside the sender's approved set names that key; other failures keep the old sentence plus OpenPGP's error text. Nothing verifies that didn't before.

Verification: `./v3 test --smoke PrivateMessageReaderTest`: 492 run, 492 passed (full suite not run). New assertions cover the clock case (signature dated an hour ahead, key dated a day back) and the unknown-key reason on the existing wrong-key case. `node tests/browser/private_message_history_reader.cjs` and `node tests/browser/private_message_list.cjs` pass, so the history reader and list preview still treat these as `bad-signature`. The maintainer's own browser case was not replayed here; the test reproduces the same OpenPGP error ("Signature creation time is in the future") that his console showed.

Final review: diff is the reader, its test, this record, the index line and the ledger; no duplicated helper (the reader had no time formatter or key-ID matcher to reuse).

Commit/merge/publication status: completion commit `b654047` (first `9dc73a2`, rebased onto `b4a4eec`; reader tests rerun after the rebase, 492/492 and both browser tests pass). Pushed to `fix/dm-verify-reason`; PR open; not merged.

Study history:
- 2026-11-24 UTC, intake: **0.15** whole cycle. Claude, single exposed rater (reader code read before the estimate). Measured time unknown.
- 2026-11-24 UTC, execution entry: reaffirmed **0.15**.
- 2026-11-24 UTC, closeout: delivered scope v1 in full; retrospective **0.15**.

*Claude · 2026-11-24 UTC · Model: claude-opus-5-5*
