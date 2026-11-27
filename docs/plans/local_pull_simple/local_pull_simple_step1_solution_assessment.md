> **Feature plan:** [Step 1](./local_pull_simple_step1_solution_assessment.md) · [Step 2](./local_pull_simple_step2_feature_description.md) · [Step 3](./local_pull_simple_step3_development_plan.md) · [Step 4](./local_pull_simple_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_pull_simple`; [cycle ledger](../effort_ledger/local_pull_simple.md). Standing P2 enrollment; parent `v3/private_message_local_pull`.

Extended estimates: enabled

**Cycle estimate: 0.65**

## Original Query

"this whole thing is great as a poc so far, but is incredibly flaky, we need to make a plan to remove all extra steps and make it just work" (Ilya, 2026-11-27), right after "The source signed this key in as a different account. Nothing was pulled."

## Understood Intent

Reading your zenmemes.com DMs in a local clone should be one action that works every time, with nothing to register, approve, donate, import or trust by hand, and no dependence on your other devices.

What the proof of concept taught us. Today's flow has eight moving parts:
1. Register a second key.
2. Approve it from another device.
3. Have old devices donate history keys.
4. Import correspondents' public keys.
5. Pull.
6. Trust keys locally.
7. Verify.
8. Confirm back to zenmemes.com.

Every reported failure so far came from parts 2–8 (confirmations are part 8):
- Export pages of 8 MiB or more.
- Duplicate confirmations.
- A 50-key trust limit.
- Hanging trust.
- Flaky list previews.
- Today's account mismatch, cause not yet diagnosed. PR #454 names both sides.
- A confirmation that zenmemes.com rejected with "History changed. Retry synchronization.": its digest or message check failed for at least one item.

All of them exist only because the local key is a *new* key that can't read your history. A key that is already a recipient of your mail needs none of parts 2–4 or 6–8.

## Problem Statement

The pull depends on a second key gaining access to history through approval, other devices' donations and local trust. That makes it many-step and fragile, especially for an account with about 60 keys.

## Concepts touched

- All options: [local development import](../../concepts/local_development_import.md), messages pull (changed); [participant mailbox export](../../concepts/participant_mailbox_export.md) (changed).
- Options A and B: [browser key backups](../../reference/key_backups.md) (reused).
- Option C: [private-message history](../../concepts/private_message_history.md) (reused, unchanged).

## Solution Options

### Option A: Use your zenmemes.com key on localhost, from a backup file
**Estimate: 0.45**
Driver: folding import and trust into the pull. Uncertainty: low; the key restore already exists.

You restore a [key backup](../../reference/key_backups.md) on the local Account page (**Use a key from another device**). Because the local browser then holds a key your mail is already encrypted to, the pull just copies envelopes and decrypts them directly. The pull also brings the public keys of everyone in your mail and trusts zenmemes.com's approval of them, inside the one consent.

- Pros: works today for the key part. No history sync, no transfers, no confirmations. Nothing else needs doing on zenmemes.com.
- Cons: the backup file plus passphrase is a manual round trip. The private key now lives in two browsers, which is the thing the original plan avoided.

### Option B: "Connect to zenmemes.com" hands the key over in one click
**Estimate: 0.60**
Driver: a safe cross-origin handoff between a zenmemes.com tab and the loopback page. Uncertainty: browser popup and `postMessage` behavior on iPhone versus desktop.

`/local/` shows one button. It opens a small zenmemes.com page in a popup, where your browser already has your key. That page asks "Use this key on 127.0.0.1:8000?". One click there sends the key to the local page through `postMessage`, which only goes to a loopback address. The local page then runs the same one-step pull as in Option A.

- Pros: one button and one confirmation, with no files or passphrases. Same reliability as Option A, because the key is already a recipient of all your mail.
- Cons: the same key-copy trade-off as Option A. A new zenmemes.com page that must refuse anything but loopback targets, and needs a careful look at what a malicious local page could do.

### Option C: Keep the second key and automate the steps
**Estimate: 1.20**
Driver: still depending on approval and donations. Uncertainty: high; history for about 60 keys stays slow and partial.

Same flow as today, with each step automated: register automatically, let a zenmemes.com tab approve and donate in one visit, import keys during the pull, and trust automatically.

- Pros: no private key is copied anywhere.
- Cons: it keeps every moving part that has failed so far. History still waits on donations, which is slow with many keys. It is the most work and still won't "just work".

## Recommendation

**Option B**, built on Option A's one-step pull. Option A's pull (envelopes, correspondents' keys and trust in one pass, decrypting directly) is the core, and it is also usable alone with a backup file. Option B removes the file. Together they cut the flow to: press Connect, confirm once on zenmemes.com, read.

It fits [PRINCIPLES.md](../../../PRINCIPLES.md): the key passes from one page to another in your own browser on your own device, never through a server ("Private keys stay in the member's browser"; "Your secret keys stay on your devices"). Every failure along the way follows "Say what failed simply; keep the diagnosis one tap away": a short message and next step, a closed Details disclosure, and a named time limit on any step that can stall.

This reverses the Step 1 choice of `private_message_local_pull`, which was to keep the private key in one browser. Your call: the key goes only to a loopback page in the same browser profile on your own machine, and only after you confirm on zenmemes.com.

**What retires:** the local key registration, transfer verification, confirmations and the trust list. The export keeps its transfers field, but the pull stops using it.

**Also in scope, whichever option:** local Messages must not decrypt everything again on every load. Saved copies (`FORUM_PRIVATE_MESSAGE_CACHE_ENABLED`) are off by default, so each load decrypts again and placeholders flash over text that was already shown. Recommendation: turn saved copies on for a local clone once its pull succeeds. Step 2 settles whether that is automatic or a visible switch.

**Vertical-slice viability:** on a fresh clone, press Connect, confirm in the zenmemes.com popup, and wait. The local Messages list then shows every conversation with verified previews, on every reload. A second press adds only new mail.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

## Enhancement Requests

From Ilya, 2026-11-27, while using the current pull:

1. "the messages are getting re-decrypted on local every time, which slows the page and shows placeholders again after the decrypted content is already shown". Disposition: in scope, under the recommendation above (saved copies on for local clones).
2. "we are not leading the user to open the live site in an already primed browser so that it can re-encrypt for the new client (i did it manually)", and "after approving the key on prod, i need to go to messages to do the re-encryption". Disposition: with Options A or B this step disappears, because the local key is already a recipient and nothing needs re-encrypting. With Option C it would become a guided step that opens zenmemes.com Messages in a primed browser.
3. "it should also re-check prod automatically after i approve the key on prod from following the form". Disposition: approval also disappears with Options A and B. With Option C the card would poll zenmemes.com after the approve link opens.

Items 2 and 3 are what Option C would still need. They are why A and B are simpler: both steps go away instead of being automated.

## Study Checkpoint

2026-11-27T20:50Z; scope v1; starting revision `1fc53a90`; R1 (hashes not re-verified); P2 standing enrollment, parent `v3/private_message_local_pull`. Claude, single exposed rater. Not a pristine intake: written after building and debugging the proof of concept.

- Intake and Step 1 handoff: **0.60 projected total**, Option B (Option A 0.45, Option C 1.20; not additive). Not hours or measured labor; no approval to advance.
- Revision 2026-11-27T21:30Z, scope v1 → v2: **0.65 projected total**, up from 0.60. Reason: changed scope, adding saved copies on local clones from Ilya's enhancement request 1. Same rater and exposure.
- Ledger update pending, with the planning commit.
