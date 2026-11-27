# Messages pull: diagnose the account mismatch

Cycle v3/local_pull_account_diagnostics · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_pull_account_diagnostics`; host ledger [local_pull_account_diagnostics.md](../effort_ledger/local_pull_account_diagnostics.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.20**

Status: planned; next permitted action: implement.

## Original request

Ilya, 2026-11-27, about "The source signed this key in as a different account. Nothing was pulled. Press Pull to resume.": "how do i diagnose this… is this part of this cycle". After being told it is not part of [local_pull_simple](../local_pull_simple/) Step 1: "please do a one-shot to help diagnose and resolve this issue".

## Authorization

A one-shot on a new branch from `main` with its own PR, limited to the **Your messages** card's account check and its error display. Not authorized:
- The [local_pull_simple](../local_pull_simple/) redesign, which is still waiting for Step 1 approval.
- Changing usernames or identities on either side.

## Problem

`LocalMessagePull` refuses a pull when zenmemes.com's export reports a different account than this clone has for the signing key. The message names neither account, so the cause can't be told apart. The two possible causes have different fixes:
- the browser's key changed since registration;
- the two sides hold different usernames for the same key.

The card's errors are also single lines, without the Details layer that [CONTRIBUTING](../../../CONTRIBUTING.md#error-messages-and-diagnostics) requires.

## Outcome and work type

Application change.
- **Check before pulling.** `key_status` reports this key's username on zenmemes.com (from `get_profile`) and in this clone. When the key is registered on both but the names differ, the card stops before Pull. It says so in one sentence with the next step, and the Pull button stays hidden.
- **Pull mismatch names both sides.** The message reads: "zenmemes.com has this key as "A", this clone as "B". Nothing was pulled." The next step depends on the case:
  - **Key changed** (zenmemes.com doesn't know this key): register it.
  - **Names differ:** the clone reads mail only for its own name, so sign in locally with a key named as on zenmemes.com.
- **Every card error has Details.** Each relay error comes back with `step` and `details`; network failures in the browser get a `step` and the browser's error. The card shows the short message, then a closed **Details** disclosure with a **Copy** button holding:
  - the step;
  - the error (HTTP status and the server's text);
  - the time it waited;
  - the source;
  - the key fingerprint (public);
  - local and source usernames, when known;
  - the browser.

  No private key, cookie or message content appears.

## Risks

- **Leaking secrets into Details.** Build the fields from a fixed list. Test that the private key and session never appear.
- **Blocking a pull that would work.** Only block when both names are known and differ.

## Reuse decisions

There is no shared error-details component yet: other pages build their own (`outbox.js`, `private_access_confirm.js`). A small renderer local to `local_message_pull.js`, using native `<details>`. The Copy pattern follows `public_key.js`. The username comes from the `Username:` line of the existing `get_profile`.

## Concepts touched

[Local development import](../../concepts/local_development_import.md), messages pull: the account check.

## Verification plan

- PHP: `LocalDevelopmentImportTest` (key_status usernames, error payload `step`/`details`) and `LocalMessagePullTest` (the mismatch names both). Run them with the smoke set.
- Browser: extend `local_message_pull.mjs`. Make the mismatch happen by giving the local key's profile a different username in the local read model. Check the warning, the hidden Pull, and the Details content, including that no private key appears.
- **Unverified until Ilya runs it:** his real mismatch. The new Details will name it.

## Study checkpoint

Intake 2026-11-27T21:10Z, scope v1, R1, P2 standing enrollment; parent `v3/local_pull_trust_ux`. Claude, single exposed rater. **0.20 projected total.**

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*
