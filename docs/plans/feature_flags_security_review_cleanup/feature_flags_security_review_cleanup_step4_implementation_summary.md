> **Feature plan:** [Step 1](./feature_flags_security_review_cleanup_step1_solution_assessment.md) · [Step 2](./feature_flags_security_review_cleanup_step2_feature_description.md) · [Step 3](./feature_flags_security_review_cleanup_step3_development_plan.md) · [Step 4](./feature_flags_security_review_cleanup_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/feature_flags_security_review_cleanup` · [Ledger](../effort_estimation_trial.md#enrollment-index-after-p2)

# Access history — implementation

## Entry checkpoint
- Step 3 approved. Planning commit `e563a057`; runtime starting revision `0b9f5f8baba58b31251b6a6a6b228412784328fe`. Four stages; no pending requests or changed scope.
- Step 4 entry, 2026-10-21 UTC, scope v2, R1/P2, Codex exposed single rater (verified model identifier unavailable): **0.45 total reaffirmed**, including prior planning/mockup, implementation, verification, documentation and correction. Earlier frozen references and scores preserved; no independent rating or measured labor.

## Stage 1 - Protected entry and recovery
- Changes: replaced HTML-only security denials with a no-store access-history recovery page using existing signed PrivateSiteAuth and fixed internal return targets; retained plain API/RSS denials and non-operator restrictions. Removed Feature Flags paragraph. Added reusable authentication prompt and retry binding.
- Passed: `php tests/run.php WriteApiSmokeTest::testOperatorSecurityReviewRequiresAuthenticatedRootAndCorrelatesPolicyChanges` (1/1); `node tests/browser/access_history.mjs`; `git diff --check`.
- Evidence: isolated browser recreated hint-only HTTP 403 without private evidence, failed authentication and successful retry, actual signed session establishment, repeated expiry/resume, keyless recovery and ignored external return targets. This reproduces the source-supported reported one-line condition; the user's original browser session itself was not inspected.
- Notes: authenticated non-operators remain denied; no hint grants audit authority. Existing audit history can also be sparse; empty presentation is handled in later stages. No schema or endpoint contract change.

## Stage 2 - Recent history and evidence
- Changes: added five-event bounded history to User Access using existing query/enrichment methods; View all appears only with older events. Shared presenter/event partial uses existing receipt facts, canonical source links, concise summaries and expandable evidence, preserving unknown historical classifications. Hint-only operators receive recovery without protected data; authenticated non-operators do not see history. Existing account/pending controls retained.
- Passed: `php tests/run.php AccessHistoryPresenterTest WriteApiSmokeTest::testOperatorSecurityReviewRequiresAuthenticatedRootAndCorrelatesPolicyChanges` (4/4); `node tests/browser/access_history.mjs`; `git diff --check`.
- Evidence: PHP covers zero/four/five-of-seven events, conditional full-history link, receipt precedence, legacy/unknown facts, invitation/policy summaries and protected roles. Browser covers empty/five/six events from actual signed policy writes, expanded source links, authentication failure/retry and expiry.
- Notes: no SQL/schema changes; reused limit/has-more and five bounded detail enrichments. Stage 1 commit `3df2e156`. Full-history rendering/paging and broader theme acceptance remain later stages.
