# Site Profile and Instance State Decoupling: Step 4 Implementation Summary

## Stage 1 - Shared instance state path contract
- Changes:
  - Removed site-profile inputs and suffixes from default canonical repository and read-model database resolution.
  - Updated web bootstrap defaults so `FORUM_SITE_ID` no longer selects canonical data while explicit repository/database overrides retain precedence.
  - Kept the static HTML cache profile-specific so generated presentation cannot cross profiles.
  - Replaced the site-scoped sandbox regression with a single-instance-state contract test.
- Verification:
  - PHP syntax and scoped whitespace checks passed for all changed runtime and test files.
  - Focused repository-bootstrap and site-profile rendering tests passed.
  - The active Chouse private server returned HTTP 200 for an authenticated Board request, rendered Chouse branding/default theme, and resolved D0EE as approved from the default read model.
- Notes:
  - The existing Chouse repository/database remain untouched as inactive legacy state until Stage 3 preserves them as a recoverable backup.
  - Approval CLI cleanup and operator documentation are intentionally deferred to Stage 2.
