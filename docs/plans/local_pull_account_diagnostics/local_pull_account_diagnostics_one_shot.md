# Messages pull: diagnose the account mismatch

Cycle v3/local_pull_account_diagnostics · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_pull_account_diagnostics`; host ledger [local_pull_account_diagnostics.md](../effort_ledger/local_pull_account_diagnostics.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.20**

Status: implemented, PR open; next permitted action: maintainer merge, then Ilya reloads `/local/` and sends the Details.

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

## Changes

Planning commit `bbd9a83b`.

- **New `ForumRewrite\Support\LocalPullFailure`:** a `RuntimeException` that carries public detail fields. `LocalMessagePull` throws it on an account mismatch, naming both accounts and the next step.
- **`LocalDevelopmentRemote::keyState()`** also returns `source_username`, read from the `Username:` line of `get_profile`. `key_status` adds `local_username`.
- **`LocalDevelopmentRemoteController`:** every error answer now has `step` and `details`:
  - `step` names the action;
  - `details` holds the step, the exception class and message, the HTTP status, how long it waited, the source, the key (when it is a well-formed public identity ID) and any `LocalPullFailure` fields.
- **`local_message_pull.js`:**
  - Every relayed step times out after 180 s, and the timeout names the step.
  - Unreachable-server and non-JSON responses each have their own message.
  - Every card error renders the short message, then a closed **Details** with a **Copy** button. Details add page, source, key, online state, browser and time, built from a fixed list.
  - `render()` stops before Pull when the source's and the clone's names for the key differ.
- **`site.css`:** `.local-pull-details pre` wraps.

## Verification

- `./v3 test --smoke LocalDevelopmentImportTest LocalMessagePullTest PrivateMessageExportTest`: 509 run, 509 passed. The full suite was not run.
  - The tests cover `source_username`, `local_username` and an error payload with step, key, error class and wait time.
  - They also cover the mismatch message and its details fields.
- `node tests/browser/local_message_pull.mjs`: passed. The new step 7 renames the key's profile in the local read model, then checks:
  - the warning, with no Pull button;
  - Details closed at first, then holding step, key, both usernames and browser;
  - no private key, session or message text in the Details;
  - Copy present.

  The screenshot was checked at 390 px.
- **Unverified:** Ilya's real mismatch. Reloading `/local/` should now show it before Pull, and its Details will name the cause.

## Enhancement requests (Ilya, 2026-11-27, during this cycle)

- **Local Messages decrypt again on every load, flashing placeholders over content already shown.** Not in this cycle. Cause: saved copies (`FORUM_PRIVATE_MESSAGE_CACHE_ENABLED`) are off by default, so nothing is kept between loads. Proposed for [local_pull_simple](../local_pull_simple/) Step 1.
- **The flow doesn't lead the user to open zenmemes.com in an already-primed browser so it can re-encrypt for the new key.** Ilya did that by hand. Not in this cycle. It is part of what [local_pull_simple](../local_pull_simple/) Step 1 removes, and is recorded there.

## Outcome

Delivered as planned. Completion commit pending; PR to follow.

## Study closeout

2026-11-27T21:25Z: retrospective **0.25** (the browser step and Details renderer took a little longer); acceptance pending.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*
