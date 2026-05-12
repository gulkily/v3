# PHP Forum Rewrite Next Step: Git Writes and Static Invalidation

This document defines the next implementation step after Apache-friendly static artifact generation.

## Goal

Close the largest remaining gap between the current local slice and a production-ready deployment model:

- canonical writes must create git history, not just files on disk
- successful writes must invalidate only the affected static HTML artifacts
- read-after-write behavior must stay immediate and deterministic
- failure handling must preserve git as canonical truth

## Why This Is Next

The current repo already has:

- PHP-owned reads for the retained route set
- local canonical write APIs for thread, reply, and bootstrap flows
- Apache `.htaccess` rules for direct static HTML serving
- a static artifact builder that can materialize the anonymous route allowlist

What is still missing is the production write contract:

- no `git add` / `git commit`
- no targeted artifact invalidation after writes
- no explicit write failure step boundaries

That makes this the next highest-value production step.

## Scope

Implement all of the following in one slice:

### 1. Git-backed write orchestration

After canonical files are written successfully:

- stage only the affected canonical files
- create a deterministic non-interactive commit
- surface the commit SHA in success responses

Apply this to:

- `create_thread`
- `create_reply`
- `link_identity`

### 2. Targeted static artifact invalidation

After a successful git commit and successful read-model refresh:

- remove only the affected public artifacts under `public/`
- keep unrelated artifacts intact

Minimum invalidation rules:

- create thread:
  - `public/index.html`
  - `public/threads/<thread-id>.html`
  - `public/posts/<post-id>.html`

- create reply:
  - `public/index.html`
  - `public/threads/<thread-id>.html`
  - `public/posts/<post-id>.html`

- identity bootstrap:
  - `public/profiles/<slug>.html`

If the affected username route later becomes statically materialized, include that route then. Do not invent username-route artifacts yet unless that route is actually added to the static allowlist.

### 3. Response contract updates

Successful text write responses should include:

- `status=ok`
- existing ids/slugs
- `commit_sha=<sha>`

HTML form success notices should mention the created route and the commit SHA.

### 4. Failure boundaries

Keep the write contract explicit:

- if canonical file write fails: do not stage or commit
- if git stage/commit fails: report failure and do not invalidate artifacts
- if read-model refresh fails after a successful commit: report a deterministic error and leave a stale-state hook for the next step

For this slice, it is acceptable to stop short of a full stale-state registry as long as the failure is surfaced cleanly and no further derived invalidation occurs after the failed refresh.

## Non-Goals

Do not include these in this step:

- full stale/read-model status pages
- repo HEAD mismatch detection on startup
- write/rebuild locking
- automatic next-read artifact regeneration
- pagination or extended activity caching
- username-route static artifact generation

Those belong in later production-hardening steps.

## Implementation Shape

### Write service

Extend [LocalWriteService.php](/home/wsl/v3/src/ForumRewrite/Write/LocalWriteService.php) so each write:

1. validates input
2. writes canonical file(s)
3. stages affected paths with non-interactive git
4. commits with a deterministic message
5. rebuilds the read model
6. invalidates affected static artifacts
7. returns ids plus commit SHA

The git command layer should be isolated behind small helper methods rather than scattered shell calls.

### Static invalidation helper

Add a small helper under `src/ForumRewrite/Host/` or `src/ForumRewrite/Write/` that:

- maps write results to artifact paths
- deletes only those files if they exist
- stays compatible with the current Apache/public artifact layout

### Configuration

Use explicit paths rather than hidden assumptions:

- repository root: existing configured repo path
- database path: existing configured DB path
- artifact root: default to `projectRoot/public`, overridable if needed

## Tests Required

Add smoke coverage for:

1. create thread:
   - canonical file created
   - git commit created
   - response contains `commit_sha`
   - `public/index.html` and the new thread/post artifacts are invalidated

2. create reply:
   - canonical file created
   - git commit created
   - response contains `commit_sha`
   - affected thread/post artifacts are invalidated

3. link identity:
   - identity/public-key files created
   - git commit created
   - response contains `commit_sha`
   - affected profile artifact is invalidated

4. git failure path:
   - failure is surfaced deterministically
   - no artifact invalidation happens after the failure

Use temp git repositories in tests rather than mutating the committed fixture tree.

## Acceptance Criteria

This step is complete when:

- all three retained write flows create git commits in a writable temp repo
- success responses expose `commit_sha`
- Apache/public static artifacts are invalidated only for affected routes
- current write/read smoke behavior still passes
- the implementation remains non-interactive and compatible with shared-host PHP assumptions

## Likely Follow-Up Step

After this slice, the next step should be:

- stale derived-state tracking
- repo HEAD/read-model mismatch detection
- operator-visible recovery status
- simple write/rebuild locking

That is the remaining production-hardening step after git-backed writes are real.
