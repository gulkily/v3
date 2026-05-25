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
