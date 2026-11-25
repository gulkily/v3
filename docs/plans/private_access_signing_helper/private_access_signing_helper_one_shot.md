Cycle: `v3/private_access_signing_helper`; Mode: one-shot; Record/index: [plans index](../README.md). Fourth of five one-shots from the "9 keys for ilyag want to read your messages" review (2026-11-25); `private_access_card_settle` is stacked on this one.

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/private_access_signing_helper`; host ledger [private_access_signing_helper.md](../effort_ledger/private_access_signing_helper.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.10**

Status: implemented and committed; next permitted action: maintainer review (PR).

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

Planning commit: `d6838d5` (plan and index entry only, before any implementation).

Study history:
- 2026-11-25 UTC, intake and execution entry: **0.10**. Recorded after inspecting both scripts, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

Dated execution history:
- 2026-11-25: `submitSignedPrivateAccessConfirmation()` added to `browser_signing.js` beside `submitSignedApproval()` and exported on `window.ForumBrowserSigning`. Addition.
- 2026-11-25: `private_access_confirm.js` drops its own `post()` and localStorage read and calls the helper; button text, `Confirmed`, `Not me` hiding and Retry are unchanged. Correction of a duplication.
- 2026-11-25: the browser now sends form fields rather than JSON to both endpoints, as approvals do; `requestData()` reads either.
- 2026-11-25: tests: `BrowserSigningNormalizationTest::testPrivateAccessConfirmPreparesSignsSubmitsAndRetriesTheSameRequest` (fields of both requests; a failed submit returns its signed request; Retry sends the same body without preparing again; `Server-Timing` is read); `PrivateAccessConfirmationTest::testConfirmEndpointsAcceptTheFormFieldsTheBrowserSends` (prepare and confirm through the application with form fields end with `has_access: true`).
- Discovery: `key_retirement.js` has the same hand-rolled prepare/sign/finalize loop, which `private_access_confirm.js` was evidently copied from. Not changed (outside authorization); listed below.

Study history, closeout:
- 2026-11-25 UTC: retrospective scope assessment **0.10**. Delivered as planned. Claude, single exposed rater. Maintainer judgment pending; measured time unknown.

Outcome: delivered. Confirm goes through `ForumBrowserSigning` like Approve; the card's script is button handling only. No visible change.

Verification:
- Passed: `./v3 test --smoke BrowserSigningNormalizationTest PrivateAccessConfirmationTest`: 597 run, 597 passed. Full suite not run.
- Passed: `node --check` on both scripts; `php -l` on both tests; `git diff --check`.
- Unverified: a real browser Confirm with OpenPGP.js (needs a stored key in a headless browser); the Node test stubs `openpgp`, as the approval test does. Try one Confirm after deploying.
- Inapplicable: markup, CSS, themes, icons, schema, flags.

Final review: the diff touches `browser_signing.js`, `private_access_confirm.js`, two tests, this record, the plans index, the ledger and the debug log. The helper reuses the approval flow's building blocks.

Commit/merge/publication status: completion commit `fa4219d` on `fix/private_access_signing_helper`; PR https://github.com/gulkily/v3/pull/424 opened 2026-11-25; not merged. `private_access_card_settle` (#425) is stacked on this branch.

Debug: [debug log](./private_access_signing_helper_debug_log.md).

Enhancement requests:
- Move `key_retirement.js` onto a shared helper too. Addition, proposed by Claude. Not approved. Not done.

Attribution: Claude · 2026-11-25 UTC · Model: claude-opus-5-5
