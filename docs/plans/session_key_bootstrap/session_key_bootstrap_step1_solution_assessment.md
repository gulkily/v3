> **Feature plan:** [Step 1](./session_key_bootstrap_step1_solution_assessment.md) · [Step 2](./session_key_bootstrap_step2_feature_description.md) · [Step 3](./session_key_bootstrap_step3_development_plan.md) · [Step 4](./session_key_bootstrap_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/session_key_bootstrap`; [host ledger](../effort_estimation_trial.md#cycle-v3session_key_bootstrap). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.60

# Step 1: Solution Assessment — Session key bootstrap

## Original Query

(On hearing that Safari on iPhone deletes a site's script-written storage, where the browser key lives, after seven days of Safari use without a visit:) This is fucked up, we need to work around it somehow.

We need to use it [the session cookie] as bootstrap for a new approved key that ideally happens effortlessly or near-effortlessly. Does the server have a way of verifying that the cookie is reasonably probably not forged?

Can we also add fingerprinting into the mix?

(On the suggestion "session cookie as the proof, HTTPS as the requirement, and the fingerprint as a tie-breaker that downgrades suspicious requests to a manual step":) Agree on all, with a feature flag for HTTP.

## Understood Intent

A member's browser can lose its private key while keeping its session cookie, because the server sets that cookie and Safari's rule covers only storage that scripts write. Today such a browser quietly becomes a guest, and the member needs a backup or a fresh approval. The goal is that the member keeps working, with a new approved key, and does little or nothing.

## Agreed Direction

Ilya agreed to these points before this document was written:

- The live session is the proof. Its token is random and server-issued, so it cannot be forged; it can only be stolen.
- HTTPS sessions qualify. HTTP sessions qualify only when an operator turns on a feature flag, because a cookie sent over HTTP can be copied on the network.
- A coarse device fingerprint, stored with the session, is a tie-breaker. A clear mismatch sends the request to a manual step. A match never grants anything the session would not.

## Problem Statement

A browser that loses its key has no effortless way back to an approved key, even while it still holds a session that proves who it was.

## Concepts touched

- [Viewer session](../../concepts/viewer_session.md): changed in Options A, B and C (a session gains the power to bootstrap a key, and carries a fingerprint); used unchanged in Option D.
- [Approval acceptance](../../concepts/approval_acceptance.md): changed in Option A (a new approval source that no member key signs); used unchanged in B, C and D.
- [Key retirement](../../concepts/key_retirement.md): used unchanged in Option A (the lost key can be retired); not touched in B, C and D.
- Key custody (private keys stay on the member's devices): unchanged in A and D; changed in B and C, where the server keeps an encrypted copy.
- Feature flags and flag presets: changed in A, B and C (new flags); unchanged in D.

## Option A — Session-bootstrapped replacement key

**Estimate: 0.60**

A page that loads with a live session and no key makes a new key under the same username and sends its public half with the session. The server checks the session, the scheme rule and the fingerprint, then records an approval for the new key, marked as bootstrapped from that session. The member sees one notice and an offer to save a backup.

- Pro: nothing for the member to do, type or remember.
- Pro: no private key material reaches the server.
- Pro: the approval is an ordinary visible record that an operator or the member's other keys can retract.
- Con: a stolen session can mint a lasting key. HTTPS, the fingerprint, one bootstrap per session and member-only powers limit this; they do not remove it.
- Con: private messages encrypted to the lost key stay unreadable without a backup.
- Con: needs JavaScript to make the key. Script-free browsers gain nothing here.

Main driver: a new approval source and its replay through rebuilds. Main uncertainty: how a bootstrapped approval sits beside key retirement and the same-username rules.

## Option B — Encrypted key copy on the server, unlocked by passphrase

**Estimate: 0.80**

The browser stores a passphrase-encrypted copy of its key on the server, using the existing backup format. A browser with a session and no key fetches the copy, and the member types the passphrase.

- Pro: the same key comes back, so message history and existing approvals survive.
- Con: the member must set and remember a passphrase; this is not effortless.
- Con: the server holds ciphertext that a weak passphrase would not protect if the server were read. This bends key custody and needs its own flag.

Main driver: storage, retrieval limits and the restore flow. Main uncertainty: where the copies live so they stay out of the public repository and exports.

## Option C — Encrypted key copy on the server, unlocked by a passkey

**Estimate: 1.10**

As Option B, but a passkey in the phone's keychain unlocks the copy, so recovery is a Face ID or fingerprint tap.

- Pro: the same key comes back with one tap, and the copy is strongly protected.
- Con: the most work, HTTPS only, and it depends on a browser feature whose current Safari support is not verified.
- Con: also bends key custody.

Main driver: passkey registration and unlock flows. Main uncertainty: browser support.

## Option D — Prevention only

**Estimate: 0.20**

Tell the member when a browser has a session and no key, and prompt iPhone Safari users to add the site to the home screen and save a backup right after making a key.

- Pro: small, no new trust decisions.
- Con: helps only members who act on a prompt. The loss still happens.

Main driver: detection and prompts. Little uncertainty.

## Recommendation

Option A, as agreed, including the "lost key" notice from Option D. It is a workable vertical slice: one flow from a keyless page load to an approved key, behind a flag, with the HTTP flag and the fingerprint check inside it. Options B and C can follow as separate cycles for instances that care about message history.

Open questions for Step 2:

- Is a bootstrapped key a full equal of the lost key, or does it stay marked as a replacement?
- What is the manual step after a fingerprint mismatch?
- Does the lost key stay approved, or is it retired when its replacement is approved?
- Which traits go into the fingerprint, and how is it disclosed, given the site says its cookies are for sessions and not for tracking?

## Enhancement Requests

- 2026-11-15, Ilya: script-free browsers must not be blocked from access. Related, not in this scope: a plain sign-in form (sign a challenge with `gpg`, paste the signature). Disposition: proposed as its own cycle; not started.

## Study history

- Intake and Step 1 snapshot, 2026-11-16 UTC: recommended scope (Option A with the notice) **0.60**. Scope v1, reference R1, protocol P2, starting revision `9bdd4b3`. Rated after the design discussion, so there is no pristine intake. R1 source hashes not re-verified this session. Single exposed rater: Claude.

*— Claude · 2026-11-16 UTC · Model: claude-fable-5-2*
