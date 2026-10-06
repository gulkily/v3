# Multi-Site Refactor P1 — Step 4: Implementation Summary

> **Feature plan:** [Step 1](./multi_site_refactor_p1_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p1_step2_feature_description.md) · [Step 3](./multi_site_refactor_p1_step3_development_plan.md) · [Step 4](./multi_site_refactor_p1_step4_implementation_summary.md)

## Stage 1 - Authoritative QDB quote numbers

- Changes:
  - Added `QdbQuoteNumbers` as the single owner of QDB thread-ID minting, suffix parsing, numeric lookup, and card permalink data.
  - Migrated the write service, numeric route lookup, and quote-card partial; removed the duplicated read-model lookup and write-service allocator.
  - Added direct contract coverage for QDB IDs, non-QDB IDs, invalid allocation, read-model allocation, and numeric recovery.
- Verification:
  - `php -l` passed for the new component and every changed PHP source/test file.
  - `php tests/run.php QdbQuoteNumbersTest QuoteCardDisplayNumberTest WriteApiSmokeTest` — 126 passed.
  - `git diff --check` passed.
- Notes:
  - Existing QDB ID shape and legacy full-ID fallback are preserved; route ownership moves in Stage 2.

## Stage 2 - Classic QDB route boundary

- Changes:
  - Added `QdbExperience` and its typed route result as the owner of classic QDB route matching, dispatch, redirects, and non-QDB route rejection.
  - Replaced QDB-specific routing branches in `Application`; unmatched numeric shortcuts remain at the final fallback position.
  - Added a QDB/Zenmemes/Chouse route matrix for direct and legacy query URLs.
- Verification:
  - `php -l` passed for the new route module, result type, application wiring, and route test.
  - `php tests/run.php QdbExperienceRoutingTest QuoteCardDisplayNumberTest` — 11 passed.
  - `git diff --check` passed.
- Notes:
  - Board and presentation policy remain in their current shared components until Stages 3-5; this stage changes route ownership only.
