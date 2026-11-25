# Key Publish First Try

[Plans index](../README.md)

Cycle: v3/key_publish_first_try. Mode: one-shot.
Effort study: railspike-effort P2, R1, scope v1.

**Cycle estimate: 0.25**

Status: implemented and verified locally. Production acceptance follows review and rollout.

Goal: Continue on the Account page registers a new key without showing "Couldn't publish your key." when the failure would have cleared on its own.

Original request: [could not publish key during a user study](https://zenmemes.com/threads/thread-20261125011746-cb08ff65) (PF-20261125-B01): "during a user study, a user on the account page received 'could not publish key' the first time. this is very very bad and we need to eliminate the possibility of it happening."
Authorization: On 2026-11-25 UTC the maintainer pasted the thread link into a Claude session. Under docs/agents/thread_to_pr.md this authorizes a one-shot cycle through an open PR and a thread reply; not merge or deploy.
Dependency inventory: Zero new or updated dependencies.

## What could fail the first time

The Account page's Continue (`publishNewKey` in public/assets/browser_signing.js) registers the key with prepare_identity then create_identity. Before this change, `publishPublicKeyWithRetry` retried once, and only for three specific messages (key inspection and signature verification). Every other failure went straight to "Couldn't publish your key." Failures that clear on their own and were not retried:

- The 5-second execution lock wait timing out while another write (an agent post, a rebuild, a deploy task) holds it: "Timed out waiting for execution lock".
- A create_identity that commits the key and then loses its response: a FastCGI timeout during a slow read-model update (a full rebuild when the read model is not current), or a post-commit failure ("Canonical write committed at ... but read-model refresh failed"). The key is saved; the browser reports failure.
- A non-JSON response (gateway error page) or a dropped connection on a phone.

The user's second press worked, which fits all three. The production cause for this report is not known: the error row showed the friendly message in place of the technical cause, so the cause was not recorded.

## Approach and risks

Considered: (A) retry every failure that is not a final refusal, a few times with spacing; (B) also show the member card on failure and publish on the next action; (C) make the server's post-commit work non-fatal. Chose A for this cycle: it covers all three causes from the client, and retrying is already safe because each attempt first checks whether the server knows the key (get_profile, then prepare_identity's "Identity already exists" file check). B changes what the page claims and is a product choice. C helps only the lost-response case and touches every write path; listed as a follow-up.

- Retries: one attempt plus four, waiting 1, 2, 4 and 8 seconds (about 15 seconds of waiting). The status stays "Publishing your public key in the background..." throughout.
- Final refusals stop at once: retired key, missing or non-ASCII key, write APIs disabled or no git checkout, browser OpenPGP unavailable, identity changed in another tab. Retries also stop if another tab replaces the browser key.
- Each attempt sends its own `bootstrap_retry_index`, so server diagnostics can tell retries apart.
- The error row now carries the technical cause under the friendly message, so a future report can be traced.

Reuse: `publishPublicKey`, `serverKnowsCurrentIdentity`, the prepare/create identity contract and its diagnostic context, and `setStatus` technical details. The restore and first-action paths share `publishPublicKeyWithRetry` and get the same retries.

## Outcome and verification

- New tests in tests/BrowserSigningNormalizationTest.php: Continue where create_identity commits but returns a 504 page, the next prepare times out on the lock, and the third finds the key; no "Couldn't publish" status ever shows and the page ends at "Public key linked." A retired key is reported with no retries. Updated the repeated signature-failure and restore-rollback tests for the longer retry run (timers stubbed).
- Passed `./v3 test BrowserSigningNormalizationTest AccountKeyPageHooksTest` and the smoke set with those tests. The full suite was not run.
- Not verified: the production cause of the reported failure; a real phone against zenmemes.com.

Failure-mode reference: [Key registration failure modes](../../reference/key_registration_failure_modes.md) (2026-11-25).

Follow-ups: make post-commit work in create_identity non-fatal on the server (C); on members-only sites, a sign-in failure after a successful publish still reports "Couldn't publish your key."

Commit status: this record accompanies the implementation commit. Merge and deployment are pending.

*— Claude · 2026-11-25 UTC*
