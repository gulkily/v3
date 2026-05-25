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
