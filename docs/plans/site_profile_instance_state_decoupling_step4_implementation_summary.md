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

## Stage 2 - Operator command and documentation alignment
- Changes:
  - Removed site-profile-based default path selection from approval tooling.
  - Preserved explicit repository/database arguments and environment overrides as the instance-selection mechanism.
  - Updated local and production guidance to distinguish canonical instance state from site presentation and disposable static caches.
  - Extended approval-command coverage to run under the Chouse profile while targeting isolated instance overrides.
- Verification:
  - PHP syntax and scoped whitespace checks passed for the approval script and changed tests.
  - Focused approval shortcut, explicit instance override, and shared default-state tests passed.
  - With `FORUM_SITE_ID=chouse`, the live CLI found an existing 5FE approval in the default repository even though that seed is absent from the legacy Chouse repository.
- Notes:
  - Production deployments that already set explicit repository/database paths are behaviorally unchanged.
  - No canonical records or derived databases were moved or merged in this stage.
