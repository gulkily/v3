> **Feature plan:** [Step 1](./qdb_quote_id_authoring_scope_step1_solution_assessment.md) · [Step 2](./qdb_quote_id_authoring_scope_step2_feature_description.md) · [Step 3](./qdb_quote_id_authoring_scope_step3_development_plan.md) · [Step 4](./qdb_quote_id_authoring_scope_step4_implementation_summary.md)

## Stage 1 - Server-side authoring contracts

- Changes:
  - Made generic thread creation and preparation allocate regular IDs regardless of site profile.
  - Added QDB-only quote create and prepare operations, plus their HTTP/API routes, to allocate numbered quote IDs explicitly.
  - Added direct API coverage for quote sequencing, generic QDB IDs, and non-QDB quote-operation rejection.
- Verification:
  - `php -l src/ForumRewrite/Write/LocalWriteService.php src/ForumRewrite/Http/WritePostAndIdentityApiController.php src/ForumRewrite/Application.php tests/WriteApiSmokeTest.php` — passed.
  - `php tests/run.php WriteApiSmokeTest::testQdbQuoteApiAssignsSequentialQuoteNumbersAcrossDigitBoundary WriteApiSmokeTest::testQdbQuoteApiStartsQuoteNumberingAtOneWithNoExistingQuotes WriteApiSmokeTest::testQdbPrepareQuoteContinuesSameQuoteNumberSequenceAsCreateQuote WriteApiSmokeTest::testQdbGenericCreateAndPrepareThreadUseRegularIds WriteApiSmokeTest::testQuoteApiRejectsNonQdbSiteProfiles` — 5 passed, 0 failed.
  - Migration and deployment checks — not applicable; no schema or deployment configuration changed.
- Notes:
  - Existing record parsing, numeric permalink resolution, and quote-list eligibility are unchanged.

## Stage 2 - Add Quote form and browser routing

- Changes:
  - Marked the shared Add Quote form as quote authoring and made its non-JavaScript submission target `/add`.
  - Added the matching QDB quote form submission handler while retaining `/compose/thread` as the regular-thread handler.
  - Routed browser-signed preparation, retry, and unsigned submission from the form's authoring operation; generic thread forms retain their existing endpoints.
- Verification:
  - `php -l` passed for the changed PHP source, templates, and focused test files; `node --check public/assets/browser_signing.js` passed.
  - `php tests/run.php WriteApiSmokeTest::testQdbAddAndComposeThreadFormsSelectDifferentIdTypes QdbExperienceRoutingTest::testQdbAddFormUsesTheQuoteAuthoringOperation BrowserSigningNormalizationTest::testThreadSubmitTransportPostsUrlEncodedPayload BrowserSigningNormalizationTest::testQuoteThreadTransportSelectsQuoteCreateAndPrepareEndpoints` — 4 passed, 0 failed.
  - Migration and deployment checks — not applicable; no schema or deployment configuration changed.
- Notes:
  - The shared compose form is extended by configuration rather than duplicated; the quote-only listing policy remains unchanged.
