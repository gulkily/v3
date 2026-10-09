# Private Messaging Step 4 Implementation Summary

> **Feature plan:** [Step 1](./private_messaging_step1_solution_assessment.md) · [Step 2](./private_messaging_step2_feature_description.md) · [Step 3](./private_messaging_step3_development_plan.md) · [Step 4](./private_messaging_step4_implementation_summary.md)

## Stage 1 - Resolve approved composite-user keys

- Changes:
  - Added `ApprovedUserKeyResolver`, which returns every distinct public key from approved profiles sharing a normalized username token.
  - Added resolver tests for multi-profile inclusion, pending-profile exclusion, and duplicate-key suppression.
  - Corrected the approved plan artifacts to use the documented composite-user model rather than an unsupported multi-key single-identity model.
- Verification:
  - `php -l src/ForumRewrite/Messaging/ApprovedUserKeyResolver.php`
  - `php -l tests/ApprovedUserKeyResolverTest.php`
  - `php tests/run.php ApprovedUserKeyResolverTest` — 3 passed.
  - `git diff --check` — passed.
  - Reviewed `docs/specs/profile_read_contract_v1.md` and the `/user/<username>` controller/template path; the existing documentation already defines approved same-username aggregation, so no competing documentation was added.
- Notes:
  - Stage 2 has not started. Per user direction, implementation stops here for review.

## Stage 2 - Persist encrypted envelopes privately

- Changes:
  - Added `PrivateMessageDatabaseConfig` with an application-private default path and override contract.
  - Added `PrivateMessageStore`, a standalone SQLite store containing routing metadata and `encrypted_envelope` only.
  - Added private-store/configuration tests and registered them with the test runner.
- Verification:
  - `php -l src/ForumRewrite/Messaging/PrivateMessageDatabaseConfig.php`
  - `php -l src/ForumRewrite/Messaging/PrivateMessageStore.php`
  - `php -l tests/PrivateMessageDatabaseConfigTest.php`
  - `php -l tests/PrivateMessageStoreTest.php`
  - `php tests/run.php PrivateMessageDatabaseConfigTest PrivateMessageStoreTest` — 4 passed.
  - `git diff --check` — passed.
  - Public-output inspection is not applicable yet: this stage has no application route, canonical write, read-model, static, or offline integration. Stage 8 owns that end-to-end check.
- Notes:
  - Stage 3 has not started. Per user direction, implementation stops here for review.

## Stage 3 - Add authenticated mailbox APIs

- Changes:
  - Added authenticated send, Inbox, and Sent API routes with no-store responses.
  - Added a mailbox service that derives sender and mailbox ownership solely from the approved session profile; client identity fields are not accepted.
  - Added recipient eligibility and armored-envelope validation, plus private-message path support in private configuration.
- Verification:
  - `php -l src/ForumRewrite/Messaging/PrivateMessageMailboxService.php`
  - `php -l src/ForumRewrite/Http/PrivateMessageApiController.php`
  - `php -l src/ForumRewrite/Application.php`
  - `php -l tests/PrivateMessageMailboxServiceTest.php`
  - `php -l tests/PrivateMessageApiRoutingTest.php`
  - `php tests/run.php PrivateMessageMailboxServiceTest PrivateMessageApiRoutingTest PrivateMessageDatabaseConfigTest PrivateMessageStoreTest PrivateConfigSchemaTest` — 11 passed.
  - `git diff --check` — passed.
- Notes:
  - The API retains ciphertext and routing metadata only; browser encryption/signing and recipient-key coverage remain Stage 4.
  - Stage 4 has not started. Per user direction, implementation stops here for review.

## Stage 4 - Encrypt and sign envelopes in the browser

- Changes:
  - Added an authenticated recipient-key endpoint that resolves every approved, distinct profile key for a composite username group.
  - Added `ForumPrivateMessages.prepareEnvelope`, which obtains the sender and recipient groups' public keys, encrypts to their union, and signs with the browser's matching local private key.
  - Added browser-helper coverage for recipient-key requests, all-key encryption coverage, armored output, and sender signing.
- Verification:
  - `node --check public/assets/private_messages.js`
  - `php -l src/ForumRewrite/Messaging/PrivateMessageMailboxService.php`
  - `php -l src/ForumRewrite/Http/PrivateMessageApiController.php`
  - `php -l tests/PrivateMessageEnvelopeTest.php`
  - `php -l tests/PrivateMessageMailboxServiceTest.php`
  - `php tests/run.php PrivateMessageEnvelopeTest PrivateMessageMailboxServiceTest PrivateMessageApiRoutingTest PrivateMessageDatabaseConfigTest PrivateMessageStoreTest PrivateConfigSchemaTest` — 13 passed.
  - `git diff --check` — passed.
- Notes:
  - This helper only prepares an encrypted envelope; it does not submit or render messages. The profile composer integration is Stage 5.
  - Stage 5 has not started. Per user direction, implementation stops here for review.

## Stage 5 - Compose and send from a profile

- Changes:
  - Added a private-message composer to approved users' profile pages when the authenticated viewer is approved and belongs to a different username group.
  - Added browser submission behavior that verifies the browser identity, prepares the Stage 4 envelope, and sends only the armored envelope and routing fields to the private-message API.
  - Added per-recipient local drafts containing a stable message ID; drafts survive encryption or network failures and clear only after a successful response.
- Verification:
  - `node --check public/assets/private_message_compose.js`
  - `php -l src/ForumRewrite/Application.php`
  - `php -l templates/pages/profile.php`
  - `php -l tests/PrivateMessageComposerTest.php`
  - `php tests/run.php PrivateMessageComposerTest PrivateMessageEnvelopeTest PrivateMessageMailboxServiceTest PrivateMessageApiRoutingTest` — 7 passed.
  - The composer test verifies a failed request retains the draft and submission ID, a successful retry clears it, and neither request body contains plaintext.
- Notes:
  - The composer targets the approved username group represented by the viewed profile, so all of that composite user's approved keys receive the envelope.
  - Stage 6 has not started. Per user direction, implementation stops here for review.

## Stage 6 - List Inbox and Sent

- Changes:
  - Added authenticated, no-store Inbox and Sent routes at `/messages/inbox` and `/messages/sent`.
  - Added authenticated Messages navigation and mailbox presentation that exposes only counterpart username, timestamp, and message ID; envelopes are not rendered into HTML.
  - Reused the ownership-scoped mailbox service and existing authenticated list APIs for the mailbox contract.
- Verification:
  - `php -l src/ForumRewrite/Application.php`
  - `php -l src/ForumRewrite/Http/PrivateMessagePageController.php`
  - `php -l templates/pages/private_messages.php`
  - `php -l tests/PrivateMessagePageControllerTest.php`
  - `php tests/run.php PrivateMessagePageControllerTest PrivateMessageMailboxServiceTest PrivateMessageApiRoutingTest PrivateMessageComposerTest` — 7 passed.
  - The page-controller test confirms recipient Inbox and sender Sent views see the fixture metadata, a third approved user sees neither, and ciphertext does not render.
- Notes:
  - Message bodies remain intentionally unreadable at this stage; Stage 7 adds local envelope decryption and signature verification.
  - Stage 7 has not started. Per user direction, implementation stops here for review.
