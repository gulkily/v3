# PHP Forum Rewrite Repo Self-Sufficiency TODO

This file captures what `v3` still needs before it is self-sufficient and no longer depends on `~/v2` as the authoritative source for core contracts.

## Current State

Status update on 2026-04-08:

- `docs/specs/canonical_post_record_v1.md` now exists in `v3`.
- `docs/specs/identity_bootstrap_record_v1.md` now defines the retained canonical bootstrap format in `v3`.
- `docs/specs/public_key_storage_v1.md` now defines canonical public-key storage in `v3`.
- `docs/plans/php_forum_rewrite_trimmed_parity_suite_v1.md` now records the reduced parity scope in `v3`.
- `tests/fixtures/parity_minimal_v1/` now provides a local seed fixture tree for the retained record families.
- `docs/plans/php_forum_rewrite_answered_questions.md` has been updated so the migrated `v3` docs, not `~/v2`, are the authority set.

- `v3` now contains planning/spec docs plus a local seed parity fixture tree.
- `v3` still does not contain an executable parity test runner, but the retained contracts and fixture inventory now live locally.
- The active project scope now excludes:
  - merge features
  - moderation
  - profile updates
  - task-planning/task-status features
  - thread-title updates

## Conclusion

- `v3` is now self-sufficient at the planning/specification layer for the retained scope.
- `v3` still needs implementation-time executable tests later, but it no longer needs `~/v2` as the authoritative source for the core retained contracts documented here.

## Resolved Items

These self-sufficiency items are now complete inside `v3`:

1. Canonical post record spec.
2. Identity/bootstrap record spec.
3. Public-key storage spec.
4. Profile read contract spec.
5. Trimmed parity/regression scope document.
6. Trimmed local fixture/sample record tree.

## Tests/Behavior To Bring Over From `v2`

Keep only coverage for:

- board index reads
- thread reads
- post permalink reads
- profile reads
- username profile route
- activity page and RSS behavior
- instance page
- compose thread page
- compose reply page
- account key page
- identity hint behavior
- `llms.txt`
- PHP static HTML / PHP fallback behavior
- core write submissions for thread/reply/bootstrap flows

## Do Not Migrate

Do not bring over `v2` content for:

- task pages or task write flows
- merge features
- moderation features
- profile update features
- thread-title update features

## Ready For Implementation

Implementation should treat these local documents as the authority set:

1. [php_forum_rewrite_spec_v1.md](/home/wsl/v3/docs/specs/php_forum_rewrite_spec_v1.md)
2. [canonical_post_record_v1.md](/home/wsl/v3/docs/specs/canonical_post_record_v1.md)
3. [identity_bootstrap_record_v1.md](/home/wsl/v3/docs/specs/identity_bootstrap_record_v1.md)
4. [profile_read_contract_v1.md](/home/wsl/v3/docs/specs/profile_read_contract_v1.md)
5. [public_key_storage_v1.md](/home/wsl/v3/docs/specs/public_key_storage_v1.md)
6. [php_forum_rewrite_trimmed_parity_suite_v1.md](/home/wsl/v3/docs/plans/php_forum_rewrite_trimmed_parity_suite_v1.md)
7. `tests/fixtures/parity_minimal_v1/`

## Relevant `v3` Docs

- [php_forum_rewrite_spec_v1.md](/home/wsl/v3/docs/specs/php_forum_rewrite_spec_v1.md)
- [profile_read_contract_v1.md](/home/wsl/v3/docs/specs/profile_read_contract_v1.md)
- [php_forum_rewrite_answered_questions.md](/home/wsl/v3/docs/plans/php_forum_rewrite_answered_questions.md)
- [php_forum_rewrite_fdp_loop_recommendation.md](/home/wsl/v3/docs/plans/php_forum_rewrite_fdp_loop_recommendation.md)
