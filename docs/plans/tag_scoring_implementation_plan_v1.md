# Tag Scoring Implementation Plan v1

## Purpose

This plan turns the agreed product outline into an implementation plan for the current codebase.

Confirmed product decisions:
- score threads only
- scored tags count once per approved identity per thread per scored tag
- initial scored tags:
  - `like => +1`
  - `flag => -100`
- unapproved users can still apply the same tags, but their scored tags do not affect score
- one-click scored-tag buttons appear first on thread pages only
- score is shown as a total only at first
- button behavior should update in place with no full page reload

## Existing Code Surfaces

Current relevant paths:
- tag writes:
  - [src/ForumRewrite/Write/LocalWriteService.php](/home/wsl/v3/src/ForumRewrite/Write/LocalWriteService.php)
- thread-label canonical parsing:
  - [src/ForumRewrite/Canonical/ThreadLabelRecord.php](/home/wsl/v3/src/ForumRewrite/Canonical/ThreadLabelRecord.php)
  - [src/ForumRewrite/Canonical/ThreadLabelRecordParser.php](/home/wsl/v3/src/ForumRewrite/Canonical/ThreadLabelRecordParser.php)
- full rebuild:
  - [src/ForumRewrite/ReadModel/ReadModelBuilder.php](/home/wsl/v3/src/ForumRewrite/ReadModel/ReadModelBuilder.php)
- incremental update:
  - [src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php](/home/wsl/v3/src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php)
- routing/rendering:
  - [src/ForumRewrite/Application.php](/home/wsl/v3/src/ForumRewrite/Application.php)
- current pages:
  - [templates/pages/board.php](/home/wsl/v3/templates/pages/board.php)
  - [templates/pages/thread.php](/home/wsl/v3/templates/pages/thread.php)
  - [templates/pages/tag.php](/home/wsl/v3/templates/pages/tag.php)
  - [templates/pages/activity.php](/home/wsl/v3/templates/pages/activity.php)

## High-Level Approach

1. Keep canonical tag writes centered on thread-label records.
2. Add score as derived read-model state, not as user-authored numeric input.
3. Count scored tags only when applied by approved identities.
4. Add a dedicated apply-tag endpoint for one-click actions.
5. Add thread-page scored-tag buttons that call the endpoint asynchronously and update score inline.

## Planned Slices

### Slice 1: Scored tag derivation

Goal:
- support scoring from existing tag records and hashtag-based label writes

Scope:
- introduce scored-tag config
- add thread score to read model
- derive score during rebuild
- derive score during incremental refresh path, or explicitly fall back to rebuild for scored-tag changes if needed
- show score on thread/board/tag pages

Not in scope:
- one-click buttons
- new write endpoint

### Slice 2: Explicit apply-tag endpoint

Goal:
- let the UI apply a scored tag to a thread without compose-body hashtags

Scope:
- add a dedicated API/POST route for applying a tag to a thread
- reuse canonical thread-label record writing
- return machine-readable result including updated score

Not in scope:
- in-place button UI

### Slice 3: In-place thread-page buttons

Goal:
- add `Like` button on thread page with no reload

Scope:
- render score and button
- add browser JS handler
- call explicit apply-tag endpoint
- update score/button state inline

Not in scope:
- board-level buttons
- `Flag` button unless the `Like` slice is already smooth

### Slice 4: Expansion and polish

Possible follow-up:
- add `Flag` button
- stronger duplicate-click protection in UI
- richer viewer-state rendering
- activity wording improvements for scored tags

## Shared Design Decisions

### Scored tag config

Add a single source of truth in PHP, likely in `Application` or a small dedicated helper/class.

Recommended shape:

```php
[
    'like' => 1,
    'flag' => -100,
]
```

Recommended follow-up helper methods:
- `isScoredTag(string $tag): bool`
- `scoreValueForTag(string $tag): int`
- `scoredTags(): array`

### Score semantics

Scoring rule:
- for each thread
- for each scored tag
- for each approved identity
- count at most one application of that tag from that identity

Examples:
- approved user applies `#like` twice: total contribution `+1`
- unapproved user applies `#like`: contribution `0`
- approved user applies `#flag`: contribution `-100`

### Canonical record strategy

No score-specific canonical record is needed in v1.

Continue writing standard thread-label records:
- tags remain tags
- score remains derived

This keeps backward compatibility and minimizes schema churn.

## Slice 1 Detailed Plan

### 1. Add read-model schema fields

Target table:
- `threads`

Add:
- `score_total INTEGER NOT NULL DEFAULT 0`

Likely affected files:
- [src/ForumRewrite/ReadModel/ReadModelBuilder.php](/home/wsl/v3/src/ForumRewrite/ReadModel/ReadModelBuilder.php)
- [src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php](/home/wsl/v3/src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php)

Changes:
- create schema with `score_total`
- initialize new threads with `0`
- populate selected thread rows with score in fetch queries

### 2. Extend full rebuild score derivation

Current label indexing already:
- loads all thread-label records
- validates root thread existence
- aggregates visible label set
- records label activity

Extend this phase or add an adjacent phase to also:
- resolve approval state for each label record author
- track per-thread scored-tag applications
- dedupe by:
  - thread id
  - tag
  - approved identity id
- sum score contributions into `score_total`

Recommended implementation shape:
- keep a per-thread accumulator:
  - `labelsByThread`
  - `scoreByThread`
  - `countedScoredTagsByThreadIdentity`

Important detail:
- only identities that resolve to approved profiles contribute score
- anonymous/no-identity tag records contribute `0`

### 3. Extend incremental update path

There are two viable approaches.

Preferred if low risk:
- update incremental path to recompute score for the touched thread after a thread-label write

Safer first version if incremental complexity is awkward:
- on scored-tag writes, mark/read-model refresh through full rebuild path

Recommendation:
- implement the simpler safe path first if needed
- keep parity with rebuild as the priority

### 4. Fetch and render score

Extend fetch queries for threads to include `score_total`.

Likely affected methods:
- `fetchThreads()`
- `fetchThread()`
- any other thread-list fetch that should surface score later

Render targets:
- [templates/pages/board.php](/home/wsl/v3/templates/pages/board.php)
- [templates/pages/thread.php](/home/wsl/v3/templates/pages/thread.php)
- [templates/pages/tag.php](/home/wsl/v3/templates/pages/tag.php)

Suggested display:
- `Score: N`

Keep the first version simple:
- one total integer
- no breakdown

### 5. Tests for Slice 1

Add/extend tests for:
- approved `#like` adds `+1`
- duplicate approved `#like` does not add additional score
- unapproved `#like` does not affect score
- approved `#flag` subtracts `100`
- mixed approved/unapproved applications derive correct total
- rebuild and incremental views match
- score renders on board/thread/tag pages

Likely test files:
- [tests/WriteApiSmokeTest.php](/home/wsl/v3/tests/WriteApiSmokeTest.php)
- [tests/LocalAppSmokeTest.php](/home/wsl/v3/tests/LocalAppSmokeTest.php)
- possibly a new focused read-model test similar to existing thread-label tests

## Slice 2 Detailed Plan

### 1. Add apply-tag route

Recommended route:
- `POST /api/apply_thread_tag`

Alternative:
- `POST /threads/{thread_id}/tags`

Recommendation:
- use `/api/apply_thread_tag` first to match current app style

Inputs:
- `thread_id`
- `tag`
- identity inferred from current viewer/browser identity hint, or explicitly passed if that is already how write auth works in the current app

Server validation:
- thread exists
- tag matches canonical tag token rules
- tag is allowed for this action
- likely restrict explicit button path to scored tags first

### 2. Reuse write service

Add a dedicated writer method in `LocalWriteService`, for example:
- `applyThreadTag(array $input): array`

Responsibilities:
- validate thread id and tag
- resolve author identity
- write canonical thread-label record for the single tag
- refresh derived state
- return enough data for UI refresh

Suggested response payload:
- `status=ok`
- `thread_id=<id>`
- `tag=<tag>`
- `score_total=<int>`
- optionally:
  - `viewer_applied=yes|no`
  - `author_is_approved=yes|no`

### 3. Endpoint behavior for duplicates

Product rule says duplicate scored tags should not stack.

Implementation options:
- always write the record, but score dedupe ignores extras
- short-circuit and do not write if same identity already applied same scored tag to same thread

Recommendation:
- prefer short-circuiting for explicit button endpoint
- this keeps canonical history less noisy
- hashtag-based legacy path can remain simpler if needed initially

### 4. Tests for Slice 2

Add/extend tests for:
- approved user can apply `like` via API
- repeated API calls by same approved user do not raise score again
- unapproved user can apply `like` via API but score remains unchanged
- `flag` API path works with negative score
- invalid tag rejects cleanly
- non-thread target rejects cleanly

## Slice 3 Detailed Plan

### 1. Thread-page score block

Add a dedicated score UI block to the thread page.

Suggested content:
- `Score: N`
- `Like` button

Possible markup hooks:
- `data-role="thread-score"`
- `data-role="apply-thread-tag"`
- `data-thread-id="<id>"`
- `data-tag="like"`

### 2. Browser-side JS

Add a small dedicated script, for example:
- `/assets/thread_tag_actions.js`

Responsibilities:
- find scored-tag buttons on thread page
- submit background request
- disable button while pending
- update score text on success
- update button state on success
- render compact inline error on failure

Recommended first-pass button states:
- default
- pending
- applied
- error

### 3. No-reload behavior

Explicit requirement:
- do not redirect
- do not reload current page

Success flow:
1. click `Like`
2. POST background request
3. receive updated score
4. update score text inline
5. update button appearance inline

Failure flow:
1. restore enabled state
2. show compact inline error message

### 4. Viewer-state rendering

Possible first version:
- server returns whether the clicked action is now considered applied by the viewer

That avoids needing to re-derive button state purely client-side.

Possible response field:
- `viewer_applied=yes`

### 5. Tests for Slice 3

Add browser-ish or smoke coverage for:
- thread page contains score block and button
- button JS asset is included
- endpoint response format supports inline update

If test harness is too server-side for full browser behavior:
- verify rendered hooks and endpoint contract first
- deeper browser simulation can come later

## Slice 4 Detailed Plan

### Candidate follow-ups

- add `Flag` button to thread page
- suppress duplicate canonical writes for hashtag path too
- show clearer viewer-applied state on initial render
- optionally expose score in more places such as board cards if desired
- refine activity text for scored tags

## File-by-File Change Map

### Read model

- [src/ForumRewrite/ReadModel/ReadModelBuilder.php](/home/wsl/v3/src/ForumRewrite/ReadModel/ReadModelBuilder.php)
  - add `score_total` schema field
  - derive score during rebuild

- [src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php](/home/wsl/v3/src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php)
  - initialize/update `score_total`
  - or trigger safe rebuild path for scored-tag writes

### Writes

- [src/ForumRewrite/Write/LocalWriteService.php](/home/wsl/v3/src/ForumRewrite/Write/LocalWriteService.php)
  - add explicit `applyThreadTag()` path
  - possibly add scored-tag validation helper
  - possibly add duplicate-check helper for explicit endpoint

### Routing/rendering

- [src/ForumRewrite/Application.php](/home/wsl/v3/src/ForumRewrite/Application.php)
  - add scored-tag config helper(s)
  - add apply-tag route
  - fetch/render score fields
  - include new JS asset on thread page when ready

### Templates

- [templates/pages/board.php](/home/wsl/v3/templates/pages/board.php)
  - show score total

- [templates/pages/thread.php](/home/wsl/v3/templates/pages/thread.php)
  - show score total
  - add `Like` button and hooks

- [templates/pages/tag.php](/home/wsl/v3/templates/pages/tag.php)
  - show score total

### Assets

- `public/assets/thread_tag_actions.js`
  - in-place tag button behavior

### Tests

- [tests/WriteApiSmokeTest.php](/home/wsl/v3/tests/WriteApiSmokeTest.php)
- [tests/LocalAppSmokeTest.php](/home/wsl/v3/tests/LocalAppSmokeTest.php)
- possibly new focused read-model tests

## Recommended Implementation Order

1. Slice 1:
   - scored-tag config
   - `score_total` in read model
   - rebuild derivation
   - score rendering

2. Slice 1b:
   - incremental parity or safe rebuild fallback for scored-tag writes

3. Slice 2:
   - explicit apply-tag endpoint
   - duplicate-safe API behavior

4. Slice 3:
   - thread-page `Like` button
   - no-reload UI update

5. Slice 4:
   - `Flag` button
   - polish

## Review Questions

Please answer inline if you want to lock these before implementation starts.

### Q1. Endpoint shape
- `POST /api/apply_thread_tag`

### Q2. Duplicate canonical writes for explicit button endpoint
- short-circuit and do not write duplicate scored-tag record for same identity/thread/tag

### Q3. `Flag` button timing
- `Like` first, `Flag` after the interaction is stable
