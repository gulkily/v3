# Identity Bootstrap Signature Recovery: Step 2 Feature Description

## Problem

First-time browser-key setup can report an identity-bootstrap signature failure even when reloading lets the same key publish successfully. Users should not need to discover reload as the recovery path.

## User stories

- As a new member, I want browser-key setup to recover from one transient bootstrap failure so that I can enter the site without reloading.
- As a member taking a key-required action, I want identity recovery to continue that action once my key is ready so that I do not need to restart my work.
- As a member whose setup still fails, I want a clear, safe recovery message so that I know how to continue without guessing.
- As an operator, I want privacy-safe diagnostic evidence for failed bootstrap verification so that recurring production failures can be investigated.

## Core requirements

- Allow exactly one automatic recovery attempt for the known first-attempt bootstrap signature-verification failure.
- Resume the originating key-required action exactly once after successful recovery, without requiring the member to repeat it or duplicating a signed write or reaction.
- Preserve the current manual Account recovery path if recovery does not succeed.
- Keep browser private keys local; diagnostics must not expose private keys, raw signatures, or full public-key material.
- Preserve successful identity, authentication, and rejection-of-invalid-bootstrap behavior.

## Shared component inventory

- `public/assets/browser_signing.js`: reuse as the canonical browser-key setup, identity-readiness, signed-compose, approval, and bootstrap-recovery surface.
- `templates/pages/account_key.php`: reuse its existing setup status surface for user-facing recovery feedback.
- `public/assets/private_site_auth.js`: reuse its existing identity-publication entry point after reload or authentication resumption.
- `public/assets/thread_reactions.js`: reuse its existing identity-readiness caller; no reaction-specific recovery path.
- `public/assets/invite_issuance.js`: reuse the canonical signing surface; no invite-specific recovery path.
- Compose templates and reply forms: reuse their existing identity-status surfaces; no new action UI.
- `/api/prepare_identity` and `/api/create_identity`: extend the existing canonical bootstrap API flow; no new identity API surface.
- `LocalWriteService` and `OpenPgpSignatureVerifier`: extend the existing server verification and safe diagnostic boundary; no new persistence model.

## User flow

1. A user creates or restores a browser key, or starts a key-required action.
2. The site prepares the browser identity and attempts the normal bootstrap when needed.
3. If the known transient verification failure occurs, the site makes one fresh recovery attempt.
4. On success, the site continues the original action once; after a second failure, it shows the existing Account recovery guidance.

## Success criteria

- A simulated first-attempt verification failure followed by success completes setup without a reload.
- A simulated failure during a key-required action completes exactly one intended action after identity recovery.
- A repeated failure makes no more than one automatic recovery attempt and shows actionable recovery guidance.
- Diagnostics for failed verification are available to operators without recording secret key material or raw signature payloads.
- Existing successful setup, authentication, and identity-publication flows continue to pass regression coverage.
