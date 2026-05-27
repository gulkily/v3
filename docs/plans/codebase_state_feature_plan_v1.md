# Codebase State Feature Plan

## Status

Implementation in progress on `feature/codebase-state`. This captures the lightweight feature sketch from the chat discussion and is not yet an approved FDP Step 2 or Step 3 artifact.

## Goal

Add a user/operator-facing surface that shows the current codebase and derived-state health of the running forum. A user or operator should be able to answer: "What version/state is this forum running from, and is the read model healthy?" without shell access.

## Proposed Surface

- Add `GET /tools/codebase/`.
- Link the page from `/tools/`.
- Keep `/api/read_model_status` as the machine-oriented recovery endpoint.
- Reuse existing read-model status logic where possible instead of duplicating health checks.

## Information To Show

- Overall state: ready, stale, locked, or configuration issue.
- Current app version, using the existing repository-head based version.
- Current canonical repository HEAD.
- Read-model repository HEAD.
- Read-model schema version.
- Last rebuild time.
- Rebuild reason.
- Execution-lock status.
- Stale-marker status and reason, if present.
- Basic repository checks:
  - `.git` exists.
  - `records/` exists.
  - short commit SHA.
  - optional latest commit subject/date if available through `git log -1`.
- Basic read-model checks:
  - SQLite database exists.
  - metadata is readable.
  - safe row counts for core tables such as posts, profiles/users, tags, approvals, reactions, and analyses where those tables exist.
- Existing backup links:
  - `/downloads/repository.tar.gz`
  - `/downloads/repository.zip`
  - `/downloads/read_model.sqlite3`

## Implementation Slices

### Slice 1 - Status Collection

- Status: Completed.
- Add a private collector method or small service to gather repository, read-model, lock, and stale-marker facts.
- Reuse `ReadModelMetadata::repositoryHead()`, existing metadata reads, stale-marker access, and execution-lock checks.
- Return explicit `missing`, `unreadable`, or `unknown` values for optional or unavailable facts instead of throwing from the page.

### Slice 2 - Route And Template

- Status: Completed.
- Add `/tools/codebase/` handling in `Application::handle()`.
- Add `renderCodebaseState()`.
- Create `templates/pages/codebase_state.php`.
- Add a Codebase entry to `renderTools()`.

### Slice 3 - Presentation

- Status: Pending.
- Use existing `stack` and `card` conventions.
- Put a terse status summary first.
- Use compact fact rows or tables for operator details.
- Avoid prose-heavy explanatory content on the operational surface.

### Slice 4 - Verification

- Status: Pending.
- Extend `LocalAppSmokeTest::testApplicationRendersCoreRoutes()` to render `/tools/codebase/`.
- Assert stable page strings such as:
  - `Codebase`
  - `Repository head`
  - `Read model`
  - `Schema version`
  - `Lock status`
- Add focused stale/lock assertions by adapting the existing read-model status tests.

## Open Question

Decide whether full filesystem paths should be shown publicly. Full paths are useful for operators, but they may disclose host layout. A conservative first version should show status and basename-level path hints unless an operator-only access model is introduced.

## Recommended FDP Follow-Up

If this feature proceeds through the formal feature-development process, convert this draft into:

```text
docs/plans/codebase_state_step2_feature_description.md
```

Then follow with:

```text
docs/plans/codebase_state_step3_development_plan.md
```
