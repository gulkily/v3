> **Feature plan:** [Step 2](./qdb_regular_thread_presentation_step2_feature_description.md) · [Step 3](./qdb_regular_thread_presentation_step3_development_plan.md) · [Step 4](./qdb_regular_thread_presentation_step4_implementation_summary.md)

## Stage 1 - Classify detail roots by quote ID

- Changes:
  - Changed the shared QDB root-card decision from site-wide to canonical quote-ID-specific.
  - Restored regular root title/body rendering and withheld quote permalink, score, and action controls from legacy/non-quote QDB roots.
  - Extended direct legacy-root coverage to assert the title and absence of quote controls, while retaining numbered-quote regression coverage.
- Verification:
  - `php -l templates/partials/thread_root_card.php tests/QuoteCardDisplayNumberTest.php` — passed.
  - `php tests/run.php QuoteCardDisplayNumberTest::testQdbDirectLegacyThreadPermalinkRemainsAvailable QuoteCardDisplayNumberTest::testQdbPermalinkRootCardMatchesListingCardWithoutLike QuoteCardDisplayNumberTest::testFollowingTheShortNumericPermalinkReachesTheQuotePage QdbQuoteNumbersTest` — 6 passed, 0 failed.
  - Migration and deployment checks — not applicable; no schema or deployment configuration changed.
- Notes:
  - Classification reuses `QdbQuoteNumbers`; existing quote-only listing policy remains unchanged.

## Stage 2 - Static detail regression coverage

- Changes:
  - Extended the QDB static-release contract to require a generated regular-thread detail page with its title and no quote controls.
  - Retained the numbered quote's generated detail page and numeric alias equivalence checks.
- Verification:
  - `php -l tests/QuoteCardDisplayNumberTest.php` — passed.
  - `php tests/run.php QuoteCardDisplayNumberTest::testQdbStaticReleaseIncludesPublicListingsAndNumericQuoteAlias QuoteCardDisplayNumberTest::testQdbDirectLegacyThreadPermalinkRemainsAvailable QuoteCardDisplayNumberTest::testQdbPermalinkRootCardMatchesListingCardWithoutLike QdbQuoteNumbersTest` — 6 passed, 0 failed.
  - Migration and deployment checks — not applicable; no schema or deployment configuration changed.
- Notes:
  - Static detail pages reuse the corrected root-card template; no static-builder change was needed.
