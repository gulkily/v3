# ZenMemes Rules Pinned Post Implementation Plan

## Context

The ZenMemes.com rules are saved in `zenmemes_rules.txt`. The product question is how to make them prominent enough to shape first-time participation without adding a persistent, intrusive onboarding panel.

The current app supports ordinary threads and thread labels. It does not appear to have first-class pinned or sticky thread behavior yet.

## Retained Direction

Use a normal rules post with a `pinned` thread label.

This keeps the rules in the forum's normal content model:

- the rules are linkable through `/threads/<id>`
- replies, reactions, and audit history continue to work
- the pin is derived from canonical thread-label records
- the board gains a small amount of ordering and presentation behavior instead of a separate announcement surface

## Scope

Implement:

- a canonical rules thread seeded from `zenmemes_rules.txt`
- a canonical `pinned` thread-label record for that thread
- board sorting that places pinned threads before normal threads
- a compact pinned marker on board cards
- focused test coverage for ordering, rendering, and canonical rebuild behavior

Do not implement yet:

- a first-visit rules panel
- a separate announcement region
- label-management UI
- thread-label removal or unpinning UI
- special rules content duplication outside the canonical post

## Canonical Content Plan

Create a normal root post in the writable canonical repository:

- `records/posts/<rules-thread-id>.txt`
- `Subject: The Rules of ZenMemes.com`
- body copied from `zenmemes_rules.txt`
- normal public board tags, unless an existing deployment convention requires a specific tag

Use a stable, readable post id if the canonical parser allows it. If current post-id validation only supports generated ids, use a generated id and document it in this plan after creation.

Create a separate thread-label record instead of adding `#pinned` to the rules body:

- `records/thread-labels/<record-id>.txt`
- `Thread-ID: <rules-thread-id>`
- `Operation: add`
- `Labels: pinned`
- optional `Reason: Pin ZenMemes rules on board`

Reason: `pinned` is display and ordering metadata. It should not be visible as authored rules text unless the community intentionally wants the body to contain that hashtag.

## Board Ordering Semantics

Pinned ordering should be applied before the active board sort.

For every board sort:

- pinned threads appear above non-pinned threads
- pinned threads are sorted among themselves using the active sort
- non-pinned threads keep the existing active sort behavior

Examples:

- `sort=newest`: newest pinned threads first, then newest normal threads
- `sort=oldest`: oldest pinned threads first, then oldest normal threads
- `sort=top`: highest-scoring pinned threads first, then highest-scoring normal threads

For board views:

- `view=all`: include pinned and non-pinned threads, with pinned first
- `view=liked`: continue applying the liked filter first, then pin-aware ordering to the filtered set

This avoids creating a separate board region and keeps the behavior easy to reason about: pinning changes priority, not membership.

## UI Plan

Update the board card template to render a small marker when `thread_labels` contains `pinned`.

Recommended display:

- text: `Pinned`
- location: near the title or metadata line
- style: compact metadata badge, not a large callout

Do not render all thread labels on board cards as part of this slice unless the implementation is already doing that cleanly. The only required board presentation change is the `Pinned` marker.

The thread page can continue showing normal labels through the existing thread-label display. If the current thread page renders `Labels: pinned`, that is acceptable for this slice.

## Implementation Slices

### Slice 1: Seed Canonical Rules Content

Status: completed in branch slice 1.

Files likely involved:

- `zenmemes_rules.txt`
- `tests/fixtures/parity_minimal_v1/records/posts/thread-zenmemes-rules.txt`
- `tests/fixtures/parity_minimal_v1/records/thread-labels/thread-label-20260530000001-zenrules.txt`

Checklist:

- [x] create the rules root post from `zenmemes_rules.txt`
- [x] create the `pinned` thread-label record targeting that root post
- [x] rebuild or refresh the read model
- [x] confirm the thread appears at `/threads/<rules-thread-id>`
- [x] record the final rules thread id in this plan or a small operational note

Rules thread id: `thread-zenmemes-rules`.

Slice 1 verification: `./v3 test` passed after making the hashtag-write smoke test independent of the number of seed thread-label records.

### Slice 2: Add Pin-Aware Board Sorting

Status: completed in branch slice 2.

Files likely involved:

- `src/ForumRewrite/Application.php`
- `tests/LocalAppSmokeTest.php` or a focused board-ordering test

Checklist:

- [x] add a helper such as `isPinnedThread(array $thread): bool`
- [x] update board comparison so pinned status is compared before the active sort
- [x] preserve existing `newest`, `oldest`, and `top` tie-break behavior within each pinned group
- [x] keep filtering behavior in `matchesBoardView()` unchanged
- [x] add tests proving pinned threads sort above normal threads for `newest`, `oldest`, and `top`
- [x] add a test proving non-pinned ordering remains unchanged relative to the active sort

Slice 2 verification: `./v3 test` passed. `WriteApiSmokeTest::testPinnedThreadsSortBeforeNormalThreadsForBoardSorts` covers pinned priority for `newest`, `oldest`, and `top`; existing board sort tests continue covering normal relative ordering.

### Slice 3: Render Board Marker

Files likely involved:

- `templates/pages/board.php`
- `public/assets/site.css`
- `tests/LocalAppSmokeTest.php`

Checklist:

- detect `pinned` from the hydrated `thread_labels` array
- render a compact `Pinned` marker on pinned board cards
- avoid rendering an empty marker for normal threads
- add smoke coverage for the marker on `/threads/` or `/`
- add smoke coverage proving the marker does not appear on an unpinned thread card

### Slice 4: Static Artifacts And Rebuild Verification

Files likely involved:

- `src/ForumRewrite/Host/StaticArtifactBuilder.php`
- existing build or smoke commands

Checklist:

- confirm static board artifacts include the pinned ordering and marker
- confirm the pinned rules thread artifact is generated
- confirm full read-model rebuild derives `thread_labels_json` with `pinned`
- confirm incremental refresh still works for ordinary thread-label writes

## Verification

Run the narrowest relevant tests first:

1. Canonical parser or read-model thread-label tests if seed records are added to fixtures.
2. Board ordering tests for `newest`, `oldest`, and `top`.
3. Local app smoke tests covering board and thread rendering.
4. Static artifact smoke tests if public HTML artifacts are part of the deployment path.

Manual checks:

- `/threads/` shows `The Rules of ZenMemes.com` above normal threads.
- `/threads/?sort=oldest` still shows pinned rules above normal threads.
- `/threads/?sort=top` still shows pinned rules above normal threads.
- the rules thread is reachable and replyable.
- the rules body matches `zenmemes_rules.txt`.

## Follow-Up Option

If first-time conversion still needs a stronger prompt later, layer a one-time first-visit panel on top as a pointer to the pinned rules thread. Do not duplicate the rules body into that panel.
