# PHP Unicode Risk Detection Step 4 Implementation Summary

## Stage 1 - Deterministic Unicode Inspection Helper
- Changes:
  - Added `UnicodeRiskInspector` for subject/body inspection without provider calls.
  - Reports scripts, broad Unicode class counts, right-to-left presence, normalization availability, policy rejection status, suspicious code points, and stable risk labels.
  - Added focused scanner tests for ASCII, Cyrillic, mixed-script identifier-like text, right-to-left text, invisible characters, and combining marks.
- Verification:
  - `php tests/run.php` passed.
- Notes:
  - The local PHP runtime does not provide `intl`, so NFC normalization is reported as unavailable and code point names are deferred to a later environment or UI/storage slice.

## Stage 2 - Operational Unicode Risk Storage
- Changes:
  - Added `UnicodeRiskStore` and `SqliteUnicodeRiskStore`.
  - Created sibling `post_unicode_risks` storage keyed by `post_id` and `content_hash`.
  - Persisted deterministic facts separately from nullable LLM review data.
  - Preserved deterministic facts when LLM review storage records a failure.
- Verification:
  - `php tests/run.php` passed.
- Notes:
  - The store is intentionally separate from canonical records and from the existing `post_analyses` row shape.

## Stage 3 - Deterministic Detection In Post Analysis
- Changes:
  - Wired `UnicodeRiskInspector` and `SqliteUnicodeRiskStore` into `PostAnalysisService`.
  - Ensured deterministic Unicode facts are computed and stored before provider analysis or config-missing return.
  - Returned `unicode_risk` only inside approved-viewer analysis details.
  - Kept anonymous `/api/analyze_post` responses compact and unchanged except existing public status fields.
- Verification:
  - `php tests/run.php` passed.
- Notes:
  - Unicode risk findings do not affect agent reply generation gates.

## Stage 4 - LLM-Assisted Unicode Risk Review
- Changes:
  - Extended the existing post-analysis prompt and JSON schema with `unicode_risk_review`.
  - Passed deterministic Unicode facts into the existing analyzer context only when deterministic risk labels are present.
  - Stored model review output separately in `post_unicode_risks.llm_review_json`.
  - Recorded LLM failures without erasing deterministic facts.
  - Updated stub analysis mode to return deterministic Unicode review output.
- Verification:
  - `php tests/run.php` passed.
- Notes:
  - This uses the existing post-analysis model call; no extra provider request path was added.

## Stage 5 - Approved Analysis UI
- Changes:
  - Attached sibling Unicode risk rows when loading post analyses for rendered pages.
  - Added compact Unicode risk, scripts, review summary, and escaped code point output to approved analysis panels.
  - Kept anonymous pages from rendering post-analysis or Unicode-risk details.
- Verification:
  - `php tests/run.php` passed.
- Notes:
  - Suspicious characters are displayed by escaped code point notation such as `U+200B`, not raw glyph output.

## Stage 6 - Backfill And Recheck Tooling
- Changes:
  - Added `scripts/backfill_unicode_risk.php`.
  - Added `./v3 unicode-risk-backfill` wrapper command.
  - Scans existing read-model posts and writes deterministic Unicode risk rows by default.
  - Supports explicit `--with-llm` mode that reuses the existing post-analysis service for provider-enabled review.
  - Prints scanned, changed, priority bucket, and provider failure counts.
- Verification:
  - `php tests/run.php` passed.
- Notes:
  - The default mode is deterministic-only and does not modify canonical records.
