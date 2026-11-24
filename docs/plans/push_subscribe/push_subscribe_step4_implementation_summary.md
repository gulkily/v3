> **Feature plan:** [Step 1](./push_subscribe_step1_solution_assessment.md) · [Step 2](./push_subscribe_step2_feature_description.md) · [Step 3](./push_subscribe_step3_development_plan.md) · [Step 4](./push_subscribe_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/push_subscribe`; host ledger [push_subscribe.md](../effort_ledger/push_subscribe.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.75**

Branch: `feature/push-subscribe`, from `origin/main` at `9f73088e`. Planning commit `80cadf01`.

Step 4 entry, 2026-11-24 UTC: **0.75** reaffirmed from Step 3. Claude, single exposed rater.

Dependency delta so far: zero. No package, manifest or lock is added; the sender uses PHP's bundled `openssl`.

## Stage 1 - Encryption and VAPID signing

**Original estimate: 0.15**
**Retrospective: 0.10**

- Changes:
  - `src/ForumRewrite/Push/P256Key.php`: P-256 keys moved in and out of OpenSSL as DER (public point to SPKI, scalar plus point to SEC 1), and ECDSA signatures between DER and 64-byte form.
  - `src/ForumRewrite/Push/WebPushCrypto.php`: `encrypt()` (RFC 8291 aes128gcm, one 4096-byte record, plaintext capped at 3000 bytes), `vapidToken()`, `verifyVapidToken()`, `vapidAuthorization()`, base64url helpers.
  - `tests/WebPushCryptoTest.php`, listed in `tests/run.php`.
- Verification:
  - Passed: `./v3 test WebPushCryptoTest`, 5 of 5. The Appendix A body matches byte for byte; the RFC 8292 example token verifies and a changed one fails; a fresh message decrypts the way a browser would.
  - Passed: `./v3 test --smoke WebPushCryptoTest`, 492 of 492.
  - PHP 8.1: no local binary, so pending. Every OpenSSL and hash function used dates from PHP 7.3 or older (`openssl_pkey_derive` 7.3, `hash_hkdf` 7.1.2, `aes-128-gcm` with tag 7.1, `openssl_pkey_new` with `curve_name` 7.1). Typed `OpenSSLAsymmetricKey` needs PHP 8.0.
- Notes: smaller than planned, because the probe had already settled the byte formats.
- Estimate details: original scope as in Step 3; no revisions; driver was the DER handling.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*
