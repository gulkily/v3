# Codebase Page Source Details Step 3 Development Plan

## Scope
- Update `/tools/codebase/` so the page title is more precise than `Codebase`.
- Add source commands or SQLite queries for page items where exposing the source is useful and appropriate.
- Implement on a dedicated branch after approval, with one request-scoped commit per stage and this plan updated in each commit.

## Stage 1
- Goal: Rename the page to a more appropriate operator-facing title.
- Dependencies: Approved plan; clean enough worktree to create the feature branch without touching unrelated untracked files.
- Expected changes: Update the browser title, page heading, tools-page label/description if needed, tool nav label, and smoke-test expectations from `Codebase` to a clearer title such as `System State`.
- Verification approach: Run focused PHP syntax checks for changed files and the local smoke suite assertion path for `/tools/codebase/`.
- Risks or open questions: Final title choice can be adjusted during review; route remains `/tools/codebase/` for compatibility.
- Canonical components/API contracts touched: `src/ForumRewrite/Application.php`, `templates/pages/codebase_state.php`, `tests/LocalAppSmokeTest.php`; no canonical record or database contract changes.

## Stage 2
- Goal: Show source commands or database queries for appropriate facts on the page.
- Dependencies: Stage 1 committed.
- Expected changes: Extend the page model rows to carry optional `source` text; render a compact source line/cell for values derived from git commands, filesystem checks, lock/stale marker checks, metadata reads, and read-model counts. Planned helper shape: `codebaseFactRow(string $label, string $value, ?string $source = null): array`.
- Verification approach: Assert representative source strings render for repository head, latest commit, schema metadata, lock status, and row counts; run PHP syntax checks and focused smoke tests.
- Risks or open questions: Avoid showing absolute private filesystem paths or implementation-only PHP method names as sources unless they help an operator reproduce the value; prefer commands like `git -C <repository> rev-parse HEAD` and SQL like `SELECT COUNT(*) FROM posts`.
- Canonical components/API contracts touched: `src/ForumRewrite/Application.php`, `templates/pages/codebase_state.php`, `public/assets/site.css`, `tests/LocalAppSmokeTest.php`; no canonical record changes.

## Stage 3
- Goal: Complete verification and commit the per-stage plan update.
- Dependencies: Stages 1 and 2 committed.
- Expected changes: Update this document with the branch name, commit hashes, title selected, source categories rendered, and verification results.
- Verification approach: Run `php -l` on changed PHP files and `php tests/run.php`, then document pass/fail status and any pre-existing failures.
- Risks or open questions: If the full suite exposes an unrelated pre-existing failure, capture it in the plan and final response rather than broadening this feature.
- Canonical components/API contracts touched: `docs/plans/codebase_page_source_details_step3_development_plan.md`; no runtime contract changes.

## Planned Commit Cadence
- Commit 1: Approved plan document on the feature branch.
- Commit 2: Rename the Codebase page title and update tests.
- Commit 3: Add source commands/database queries and update tests.
- Commit 4: Record final verification and implementation notes in this plan.

## Approval Gate
- Pause here until the user explicitly approves this plan.
- After approval, create the feature branch, commit the approved plan first, then execute the stages above.
