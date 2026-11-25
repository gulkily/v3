# Key registration failure modes

As of 2026-11-25. Follows [Key publish first try](../plans/key_publish_first_try/key_publish_first_try_one_shot.md) (PR #417, PF-20261125-B01).

## Overview

Registration has about two dozen known ways to fail. Since PR #417, the passing ones are retried for about 15 seconds, so a person sees "Couldn't publish your key." only when the server refuses the key for good or stays unreachable past that window.

Pressing Continue on the Account page (`/account/key/`) runs these steps in `public/assets/browser_signing.js`:

1. Load OpenPGP.js and generate an Ed25519 key, backdated 60 seconds, saved to localStorage.
2. Ask `/api/get_profile` whether the server already knows the fingerprint. If it does, stop: success.
3. `POST /api/prepare_identity` with the public key. The server takes the write lock, inspects the key with gpg and returns a prepare token and a canonical record. If the prepare request itself fails to connect, the browser falls back to the older `/api/link_identity`.
4. Sign the record in the browser, then `POST /api/create_identity`. The server takes the lock again, verifies the signature, commits the identity file to git, then updates the read model.
5. On members-only sites, sign in (`authenticatePrivateSiteIfAvailable`).

Steps 2–4 form one attempt in `publishPublicKeyWithRetry`. The same function serves key restore and the first signed action (`ensureActionIdentity`), so everything below applies there too. Code references are to commit 836ca59 (PR #417).

## Client-side failure modes

Most client failures happen before publishing starts and show their own message, not "Couldn't publish your key."

| Failure | Step | Shown before #417 | Since #417 |
| --- | --- | --- | --- |
| OpenPGP.js fails to load (blocked or slow script) | 1 | "OpenPGP.js failed to load." | Same; final, no retry |
| localStorage unavailable or full | 1 | The raw storage error | Same |
| Connection drops during `get_profile` | 2 | Nothing: counted as "not known", flow goes on | Same |
| Connection drops during `prepare_identity`, then `link_identity` fails too | 3 | "Couldn't publish your key." | Retried |
| Page suspended mid-request (phone switches apps) | 3–4 | "Couldn't publish your key." | Retried when the page resumes |
| Phone clock more than 60 s fast, so the key looks created in the future (likely; not replayed) | 3 | "Couldn't publish your key." after one retry | Retried, but won't clear within 15 s |
| Another tab replaces the browser key mid-publish | 2–4 | Varied | Retries stop; the other tab publishes its own key |
| Members-only sign-in fails after a successful publish | 5 | "Couldn't publish your key." (wrong: the key is published) | Same; open follow-up |

## Server-side failure modes

The likeliest causes of the user-study failure are near the top: lock contention and a save whose response never came back. Messages are from `src/ForumRewrite/Write/LocalWriteService.php` unless noted.

| Failure | Step | Server message or response | Key saved? | Since #417 |
| --- | --- | --- | --- | --- |
| Another write holds the execution lock past 5 s (agent post, rebuild, task) | 3 or 4 | "Timed out waiting for execution lock" (`Support/ExecutionLock.php`) | No | Retried |
| Read model not current, so create does a full rebuild and the host times out (DreamHost FastCGI) | 4 | HTML 5xx page, not JSON | Yes | Retried; next attempt finds the key |
| Post-commit read-model refresh fails | 4 | "Canonical write committed at … but read-model refresh failed" | Yes | Retried; next attempt finds the key |
| Uncaught PHP error (not a `RuntimeException`) | 3 or 4 | HTML 500 | Maybe | Retried |
| Host busy page ("Meme Oven Is Busy") | 3 or 4 | HTML | No | Retried |
| gpg cannot inspect the key | 3 | "Unable to inspect OpenPGP public key." | No | Retried (was once) |
| Signature does not verify | 4 | "Identity bootstrap signature verification failed" | No | Retried (was once) |
| Prepare token expired or record mismatch | 4 | "Prepared identity bootstrap has expired." and related mismatches | No | Retried with a fresh prepare |
| Identity file already exists at prepare | 3 | "Identity already exists for this fingerprint." | Yes | Treated as success |
| Identity created by another tab between prepare and create | 4 | Same message | Yes | Next attempt succeeds |
| Key retired | 3 | "This key is retired." | No | Final, no retry |
| Non-ASCII public key | 3 | "public_key must be ASCII only." | No | Final |
| Write APIs disabled, or no git checkout | 3 | "Write APIs are disabled…" / "Writable repository must be a git checkout…" | No | Final |
| Server unreachable for longer than the retry window | any | No response | — | "Couldn't publish your key." after about 15 s |

## What the person sees now

Every attempt starts by asking whether the server already has the key, so a retry can't create a duplicate. That leaves three outcomes:

- **Passing failure:** the status stays on "Publishing your public key in the background..." through one attempt and four retries, 1, 2, 4 and 8 s apart, then ends on "Public key linked."
- **Key already saved:** a later attempt finds it through `get_profile` or prepare's file check and ends as a success, with no second write.
- **Final refusal or long outage:** "Couldn't publish your key." with a Retry button. The technical cause now sits under the message (before #417 it repeated the friendly text), so the next report can be traced.

## Gaps and follow-ups

- [ ] Make post-commit work in `create_identity` non-fatal on the server. A saved key should return success and leave the read-model catch-up to the background.
- [ ] Report a members-only sign-in failure as a sign-in problem, not "Couldn't publish your key."
- [ ] Decide whether a key whose publishing failed should still show the member card and publish quietly on the next action. That is a product call, so #417 left it out.
- [ ] Check what gpg does with a key dated in the future, and widen the 60 s backdate if gpg rejects it.
- [ ] Clean up the final-refusal list in `isFinalIdentityPublishFailure`: "public_key is required." matches no server message found here.

Not verified: the production cause of the user-study failure (the error row didn't record it), a real phone against zenmemes.com, and the full test suite.
