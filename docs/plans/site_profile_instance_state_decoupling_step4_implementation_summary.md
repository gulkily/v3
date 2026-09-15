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

## Stage 3 - Local transition and complete Chouse private-site verification
- Changes:
  - Audited the inactive Chouse repository before archival and found one unique, validly signed D0EE thread created on 2026-09-15.
  - Copied only that thread and its detached signature byte-for-byte into the canonical repository and committed them there as `e0cf97d` (`Migrate signed Chouse thread to shared instance state`).
  - Deliberately did not merge the duplicate Chouse bootstrap, approval seed, identity copy, or stale fixture records.
  - Rebuilt the canonical read model after migration.
  - Moved the complete former Chouse repository and database to the recoverable archive at `state/legacy_site_state/chouse-20260915/local_repository` and `state/legacy_site_state/chouse-20260915/post_index.sqlite3`.
  - Left `state/static_html_chouse` in place because presentation caches remain profile-specific.
  - Removed the temporary CLI-server private-session diagnostics from the request path after live validation.
- Verification:
  - Source, canonical copy, and archived copy SHA-256 hashes match for both the migrated post and its detached signature.
  - The rebuilt canonical read model contains 1,372 posts, 716 threads, 139 profiles, and 15 approval seeds.
  - The canonical signature audit reports 205 valid signatures, zero invalid signatures, and zero unknown author keys.
  - A controlled authenticated request against the running `FORUM_SITE_ID=chouse` and `FORUM_APPROVED_MEMBERS_ONLY=true` server returned HTTP 200 for the Board, D0EE's own profile, and the migrated thread; the profile rendered `Approved: yes` and the pages retained Chouse branding and the Chouse default theme.
  - An anonymous Board request returned HTTP 303 to `/lobby/`.
  - Live requests did not recreate `state/local_repository_chouse` or `state/cache/post_index_chouse.sqlite3`.
  - PHP syntax, scoped whitespace checks, focused private-site/path/approval regressions, and the complete `php tests/run.php` suite passed.
- Notes:
  - `FORUM_SITE_ID` now changes presentation only. Explicit `FORUM_REPOSITORY_ROOT` and `FORUM_DATABASE_PATH` values remain the supported way to select a genuinely separate instance.
  - The archived Chouse state retains its original Git history and derived database for recovery or later inspection.
