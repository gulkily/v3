Cycle: `v3/private_access_signing_helper`; Mode: one-shot; Record/index: [plans index](../README.md). Fourth of five one-shots from the "9 keys for ilyag want to read your messages" review (2026-11-25); `private_access_card_settle` is stacked on this one.

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/private_access_signing_helper`; host ledger [private_access_signing_helper.md](../effort_ledger/private_access_signing_helper.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.10**

Status: planned; next permitted action: implement.

Feedback item: none matches (searched `docs/feedback/items/`).

Original request (maintainer, 2026-11-25): "i get the sense that for this module we developed some copies of components we could have reused?" Claude's review: `private_access_confirm.js` has its own fetch, prepare, sign and submit loop where approvals go through `ForumBrowserSigning.submitSignedApproval`.

Authorization: "can you do it in 5 one-shots please" (2026-11-25), on Claude's list naming "move the signing flow into a shared `ForumBrowserSigning` helper". Exclusions: what the card does after a Confirm (`private_access_card_settle`); `key_retirement.js`, which has the same hand-rolled loop (noted as a follow-up, not changed); merging and deploying are the maintainer's.

Bounded outcome and work type: application change (refactor, front end).
1. `ForumBrowserSigning.submitSignedPrivateAccessConfirmation(targetIdentityId, { signed })` in `browser_signing.js` prepares through `/api/prepare_private_access_confirmation`, signs with `signCanonicalRecord()`, submits to `/api/confirm_private_access`, and returns `{ ok, hasAccess, error, signed, serverTiming }`. A caller that passes back `signed` repeats the exact signed request, as today's retry does.
2. `private_access_confirm.js` keeps only its button handling and calls the helper.

Approach and reuse decisions:
- Built like `submitSignedApproval()`: `submitUrlEncoded()` (which also reads `Server-Timing`), `parsePreparedPostJsonResponse()` and its failure classification for prepare, `currentAuthorIdentityId()` for the signer, `signCanonicalRecord()` with the expected identity.
- The signer is resolved exactly as the old script did (the stored fingerprint). `ensureActionIdentity()` is not called: without `existingIdentityOnly` it would create a key, and with publishing it adds a request; the card shows only to a device that already has a key with private access.
- The endpoints accept form-encoded bodies through `requestData()`, as `/api/prepare_approval` does.

Risks:
- **A changed request body could break Confirm.** Mitigation: a Node test like `testApprovalSigningPreparesSignsAndFinalizesCanonicalApproval` checks both requests' fields and the retry; a PHP test drives the two endpoints with form-encoded fields through the application.

Completion conditions: Confirm on Messages, Unread, User Access and the confirm page gives the same result as before (Confirmed, or the error with Retry), and Retry repeats the same signed request. Deploy with Deploy now; no schema or flag change.

Concepts touched: [Private key access](../../concepts/private_key_access.md) (used unchanged).

Verification and recovery plan: `./v3 test --smoke BrowserSigningNormalizationTest PrivateAccessConfirmationTest`; `node --check` on both scripts; `git diff --check`. Full suite not run. No browser run of the real page: OpenPGP signing in a headless browser needs a stored key; noted as a limitation. Recovery: revert the commit.

Starting revisions and branch: `fix/private_access_signing_helper` from `origin/main` `797971a`.

Planning commit: pending.

Study history:
- 2026-11-25 UTC, intake and execution entry: **0.10**. Recorded after inspecting both scripts, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

*Claude · 2026-11-25 UTC*
