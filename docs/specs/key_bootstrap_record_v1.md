# Key bootstrap record v1

A key bootstrap record lets a browser that lost its key, but kept a signed-in session, get a new approved key under the same username. See [the plan](../plans/session_key_bootstrap/session_key_bootstrap_step2_feature_description.md).

## Record

An ordinary canonical reply on the new key's bootstrap thread, with its bootstrap post as parent, signed by the new key (detached `.asc`).

```text
Post-ID: reply-…
Created-At: 2026-11-16T12:00:00Z
Board-Tags: identity key-bootstrap
Thread-ID: <new key's bootstrap thread>
Parent-ID: <new key's bootstrap post>
Author-Identity-ID: openpgp:<new key>

Key-Bootstrap-From-Identity-ID: openpgp:<session's key>
```

## Acceptance

The record grants nothing by itself. The server commits it together with its signature and a `session` [acceptance receipt](approval_acceptance_record_v1.md) in one commit. The receipt names the session's key as the actor. The server writes it only when:

- the request carries a live, signed-in session for the named key, over HTTPS, or over HTTP when `FORUM_SESSION_KEY_BOOTSTRAP_HTTP_ENABLED` is on;
- the session has not bootstrapped a key before;
- the browser's coarse description matches the one the session recorded at sign-in;
- approval policy classifies the grant as `own_account_key`: the session's key is approved, active, and has the new key's username.

A mismatched description leaves the new key pending; no record is written.

## Replay

Full and incremental rebuilds read bootstrap records through `KeyBootstrapReplay`, alongside invitation redemptions. A record with a valid `session` receipt approves the new key when the session's key is approved at that point of the replay. A record without a receipt is only a post. An invalid receipt stops the rebuild, like any other invalid acceptance evidence.

## Undo

- Ordinary path: retire the bootstrapped key with the existing [key retirement](key_retirement_record_v1.md). An operator, or another approved key with the same username, can do it.
- Operators only: retract the record. This is the one retractable identity record. Retraction deletes the record, its signature and its receipt, and the next approval refresh drops the grant. Its author cannot retract it.

A bootstrapped key never gets an approval seed, so it is never an operator.
