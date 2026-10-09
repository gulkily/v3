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
