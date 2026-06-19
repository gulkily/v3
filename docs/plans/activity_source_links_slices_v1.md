# Activity Source Links Slices Plan

## Goal
Make the activity page source metadata actionable:

- the source filename links to the canonical record content;
- when a real git commit SHA is available, the filename can link to that file as it existed at that commit;
- the commit hash links to a commit detail view.

This builds on `activity_source_filename_commit_plan_v1.md`, which added `activity.source_path` and `activity.source_commit_sha`.

## Current State
- `templates/pages/activity.php` renders a plain `Source:` line with the canonical source path and a shortened commit hash.
- `Application::fetchActivity()` already returns `source_path` and `source_commit_sha`.
- The app has no public route for viewing canonical files or commit details.
- The app already serves downloads for repository archives, but those are coarse-grained and not suitable for per-activity inspection.

## Route Design

### Source File Routes
- `GET /source/current/<path>`
  - Show the current repository file content for a validated canonical path.
  - Response type: `text/plain; charset=UTF-8`.

- `GET /source/blob/<sha>/<path>`
  - Show the repository file content at a specific commit.
  - Response type: `text/plain; charset=UTF-8`.
  - Uses `git show <sha>:<path>` after strict validation.

### Commit Route
- `GET /source/commits/<sha>`
  - Show commit metadata and changed files for a validated SHA.
  - Recommended first version: plain text.
  - Candidate command: `git -C REPOSITORY show --no-ext-diff --stat --name-only --format=... <sha>`.

## Validation Rules
- Accept only canonical record paths under known families:
  - `records/posts/<id>.txt`
  - `records/thread-labels/<record-id>.txt`
  - `records/post-reactions/<record-id>.txt`
  - `records/identity/<filename>.txt`
  - `records/approval-seeds/<filename>.txt`
  - `records/public-keys/<filename>.asc`
  - `records/instance/public.txt`
- Reject absolute paths, empty segments, `.` segments, `..` segments, URL-encoded traversal, and paths outside `records/`.
- Validate commit SHAs as lowercase or uppercase hex, 40 characters for the first slice. Short SHAs can be considered later.
- For current-file reads, resolve with `realpath()` and confirm the resolved file remains inside `$repositoryRoot`.
- For git blob reads, pass both SHA and path as escaped arguments and use a pathspec/object expression that cannot be interpreted as shell syntax.
- Return 404 for missing valid records; return 400 for invalid path or SHA shape.

## Slice 1: Current Source File Route
- Add route handling in `Application::handle()` for `/source/current/<path>`.
- Add helpers:
  - normalize and decode route path;
  - validate canonical source path;
  - load current source file as text.
- Return `text/plain` for valid files.
- Add smoke tests:
  - valid current post record returns canonical text;
  - valid current thread-label record returns canonical text;
  - traversal attempts are rejected;
  - unknown but well-shaped records return 404.
- Update this plan in the same commit.
- Status: Implemented in the Slice 1 commit. Current source files are served as text for validated canonical paths, with tests for post records, thread-label records, traversal rejection, absolute-path rejection, and missing records.

## Slice 2: Blob-at-Commit Source Route
- Add route handling for `/source/blob/<sha>/<path>`.
- Add helper to read a canonical file at a commit using git.
- In no-git repositories, return a clear 404 or 409-style text response. Recommendation: 404, because the blob resource is unavailable.
- Add smoke tests in a git-backed temp repository:
  - valid post source at commit returns canonical text;
  - invalid SHA is rejected;
  - valid SHA with missing path returns 404;
  - traversal is rejected before git is invoked.
- Update this plan in the same commit.
- Status: Implemented in the Slice 2 commit. Blob source files are served from validated 40-character SHAs with tests for valid records, invalid SHAs, missing paths, and traversal rejection.

## Slice 3: Commit Detail Route
- Add route handling for `/source/commits/<sha>`.
- Return plain text with:
  - full SHA;
  - author date or committer date;
  - subject;
  - changed file list.
- Keep this read-only and avoid rendering untrusted commit content as HTML in the first version.
- Add smoke tests:
  - real commit shows expected SHA and known changed canonical file;
  - invalid SHA is rejected;
  - missing commit returns 404.
- Update this plan in the same commit.
- Status: Implemented in the Slice 3 commit. Commit details are served as plain text for validated SHAs with tests for valid commit metadata, invalid SHAs, and missing commits.

## Slice 4: Activity Page Links
- Update `templates/pages/activity.php`:
  - source filename links to `/source/blob/<sha>/<path>` when `source_commit_sha` is a real SHA;
  - source filename links to `/source/current/<path>` when commit is unavailable;
  - visible commit hash links to `/source/commits/<sha>` when commit is real;
  - keep plain `commit unavailable` text for `no-git`, `git-error`, null, or empty values.
- Add a small helper in `Application` or prepare fields in `fetchActivity()` if keeping URL construction out of the template is cleaner.
- Add smoke tests:
  - no-git fixture activity links filename to `/source/current/...`;
  - git-backed activity links filename to `/source/blob/<sha>/...`;
  - git-backed activity links short hash to `/source/commits/<sha>`.
- Update this plan in the same commit.

## Slice 5: Final Verification
- Run:
  - `php -l src/ForumRewrite/Application.php`
  - `php -l templates/pages/activity.php`
  - `php -l tests/LocalAppSmokeTest.php`
  - `php tests/run.php`
- Document results here.
- Commit only this plan update if verification notes are not already included in Slice 4.

## Open Decisions
- First version should use plain text for source and commit views. HTML can come later if navigation or styling becomes important.
- Commit route can start with 40-character SHAs only. Short hash support is convenient but adds ambiguity handling.
- Static artifact generation should not include `/source/...` routes in the first version; source views should remain dynamic fallback routes.

## Approval Gate
Pause here until this plan is approved. After approval, implement one slice per commit and update this file in every slice commit.
