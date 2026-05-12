# PHP Thread Labels After Creation Proposal V1

This document proposes a retained design for adding labels to an existing thread after the thread has already been created.

## Goal

Support thread-level labels such as `bug`, `answered`, or `needs-review` without:

- overloading `Board-Tags`
- rewriting canonical root-post files in place
- hiding label history inside freeform replies

The retained direction should fit the current repository model:

- canonical records are ASCII files committed into git
- writes are append-only in normal operation
- SQLite is a derived read model rebuilt from canonical state

## Why This Needs A Separate Design

The current codebase can create:

- thread roots in `records/posts/`
- replies in `records/posts/`
- identity/bootstrap-related canonical records in their own families

But it does not currently support a safe "edit existing thread metadata" flow.

That matters because post-creation labels imply mutable thread state. If labels were stored only on the root post, later edits would require one of these bad options:

- rewriting canonical history in place
- inventing ad hoc patch semantics for post files
- treating label changes as magic replies and then scraping the reply body later

None of those fits the existing append-only, canonical-first architecture well.

## Product Direction

Thread labels should be a separate thread-metadata concept.

They should not be treated as:

- board taxonomy
- reply body conventions
- a subtype of `Thread-Type`

The first retained version should support:

- adding labels after thread creation
- extracting labels from hashtags in new thread roots and replies
- rendering the current label set on board and thread pages
- deriving current label state from append-only canonical records

The first retained version does not need:

- arbitrary label colors or per-label styling metadata
- label descriptions
- nested label hierarchies
- moderation rules beyond normal write permissions
- historical rollback UI

## Recommended Canonical Shape

Introduce a new canonical record family:

- `records/thread-labels/`

Each file in this family represents one append-only label mutation event against one thread.

This keeps:

- thread roots authoritative for content
- labels editable without mutating old post files
- canonical history explicit and inspectable in git

## Record Semantics

Each thread-label record should:

- target exactly one thread root
- declare one operation kind
- declare one or more labels affected by that operation
- include canonical time
- optionally attribute the change to an identity

The simplest useful operation model is:

- `set`
- `add`
- `remove`

Recommended retained V1 rule:

- support `add`
- do not support `remove` or `set` in the first implementation

Reason:

- `add` is naturally append-only
- it composes cleanly across multiple writes
- it keeps hashtag-driven labeling simple

## Proposed File Structure

Thread-label records should follow the same general structure as other canonical files:

1. contiguous header block
2. blank line
3. optional body

Recommended retained V1 rule:

- the body should be optional and usually empty
- machine-readable semantics should live in headers

## Proposed Headers

Required headers:

- `Record-ID`: unique identifier for the label mutation record
- `Created-At`: RFC 3339 UTC timestamp
- `Thread-ID`: target root thread ID
- `Operation`: `add`
- `Labels`: space-separated label tokens

Optional headers:

- `Author-Identity-ID`: identity responsible for the label change
- `Reason`: short ASCII explanation for operator or UI display

Example add record:

```text
Record-ID: thread-label-20260415153000-ab12cd34
Created-At: 2026-04-15T15:30:00Z
Thread-ID: root-001
Operation: add
Labels: bug needs-review
Author-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
Reason: Triage labels applied
```

## Hashtag Extraction

The retained V1 input model should allow labels to be introduced through hashtags in post bodies.

Recommended retained V1 rule:

- hashtags in new thread roots can add labels to that thread
- hashtags in new replies can add labels to the target thread
- hashtag extraction creates normal append-only thread-label records
- hashtags do not mutate the original post or thread-root record

Recommended hashtag form:

- `#bug`
- `#needs-review`
- `#help-wanted`

Recommended retained V1 exclusions:

- quoted lines should not contribute labels
- duplicate hashtags in one post should collapse to one label
- hashtags do not remove labels in V1

## Label Token Rules

Labels should use a stricter token format than freeform text.

Recommended rule:

- ASCII only
- lowercase
- characters allowed: `a-z`, `0-9`, and single internal `-`
- no spaces inside a label
- no leading or trailing `-`

Examples of valid labels:

- `bug`
- `needs-review`
- `help-wanted`
- `v1`

Examples that should be rejected:

- `Needs-Review`
- `needs_review`
- `customer bug`
- `-urgent`

Reason:

- labels need stable URL/filter/UI behavior later
- a normalized token form avoids duplicate semantic labels with different casing or separators

## Why Not Use Board Tags

`Board-Tags` already carry routing and visibility meaning in the current app.

Today they are used for:

- thread grouping intent
- hidden internal surfaces such as `identity`
- feed inclusion/exclusion behavior

That is not the same as user-visible per-thread labels.

If labels were folded into `Board-Tags`, the app would blur:

- browse taxonomy
- internal routing metadata
- issue-tracking or status labels

That would make future filtering and moderation logic harder, not simpler.

## Why Not Store Labels On The Root Post

Storing labels on the root post only works cleanly if labels are fixed at thread creation time.

Once labels must change later, root-post storage conflicts with the current append-only model because it would require:

- rewriting canonical files
- managing concurrent metadata edits to the same file
- rebuilding git history around mutable documents rather than append-only events

This proposal keeps mutable thread metadata append-only instead.

## Read-Model Design

The SQLite `threads` table should materialize current label state.

Recommended addition:

- `thread_labels_json TEXT NOT NULL`

Optionally later:

- `last_label_change_at TEXT NULL`

The read-model rebuild should:

1. index root posts and replies as it does now
2. load all `records/thread-labels/` records
3. validate each target `Thread-ID` against known root threads
4. sort label records deterministically
5. reduce operations into a final label set per thread
6. store the reduced labels as JSON on the thread row

Recommended reduction rules:

- start from an empty label set
- `add` inserts each listed label
- duplicate adds are harmless

This makes rebuilds deterministic and idempotent.

## Ordering Rules

The reducer needs a stable order.

Recommended rule:

- sort by `Created-At` ascending
- break ties by `Record-ID` ascending

Reason:

- canonical timestamps should remain the semantic ordering key
- deterministic tie-breaking is still required for reproducible rebuilds

## Target Validation Rules

A thread-label record should be rejected if:

- `Thread-ID` does not resolve to an existing root thread
- `Thread-ID` points to a reply instead of a root
- `Operation` is unsupported
- any label token is invalid
- `Labels` is empty after normalization
- `Author-Identity-ID` is present but invalid

Recommended retained V1 rule:

- label records may target hidden/internal threads too
- whether the UI exposes that is a separate product decision

The canonical parser should enforce shape. The read-model builder should enforce referential validity against known thread roots.

## Query And Rendering Changes

Board and thread queries should start returning `thread_labels_json`.

Board page:

- show current labels near the thread subject or metadata line

Thread page:

- show current labels near the title
- no dedicated label-management control is required for V1

Post permalinks do not need separate label rendering unless the post page already shows thread context nearby.

## Write API Direction

This feature should not rely on mutating existing post records.

Retained V1 write scope:

- V1 does not require a dedicated `POST /api/update_thread_labels` endpoint
- V1 label creation happens through hashtag extraction in thread-root and reply compose flows
- a direct label-management endpoint can remain a later enhancement

Recommended future endpoint, out of scope for V1:

- `POST /api/update_thread_labels`

Potential future inputs:

- `thread_id`
- `labels`
- `author_identity_id` when available
- `reason` optional

The V1 write service should:

1. validate the target thread
2. normalize label tokens
3. build a thread-label canonical record
4. parse it through a dedicated parser
5. write `records/thread-labels/<record-id>.txt`
6. commit the record
7. rebuild derived state
8. invalidate the affected thread and board artifacts

## Canonical Parser And Repository Additions

This proposal implies new canonical classes similar to the existing record families:

- `ThreadLabelRecord`
- `ThreadLabelRecordParser`
- `CanonicalRecordRepository::loadThreadLabel()`
- likely `CanonicalPathResolver::threadLabel()` or equivalent

This should remain a separate parser rather than widening `PostRecordParser`.

Reason:

- a label mutation event is not a post
- keeping families separate preserves canonical clarity

## Permissions And Attribution

The repo does not yet have a broad moderation/authorization system for metadata edits.

Retained V1 policy:

- approved users may add labels to threads
- hashtag-derived label additions from ordinary posting are allowed, including anonymous posting flows, but only as part of creating a thread or reply
- V1 does not provide a separate direct label-management flow for anonymous users

This proposal preserves room for stricter or broader policy later by carrying optional `Author-Identity-ID`.

If the product later adds permission checks, the canonical record shape remains valid.

## Compose Flow Implications

The retained V1 write path should support hashtag-driven label creation.

Recommended retained V1 rule:

- `createThread()` writes the root post as usual, extracts hashtags from the post body, and emits one additive thread-label record when labels are present
- `createReply()` writes the reply post as usual, extracts hashtags from the reply body, and emits one additive thread-label record for the target thread when labels are present
- if no valid hashtags are present, no thread-label record is written

This keeps posts as the user-facing input surface while preserving a separate canonical label-event family for derived thread metadata.

## History And Auditability

An append-only label-record family gives several useful properties immediately:

- label history is visible in git
- current thread state is reproducible from canonical inputs
- who changed labels can be attributed when identity is present
- future UI can show label history without changing the storage model

This is a major advantage over overwriting root-post files.

## Static Artifact Implications

Changing labels affects at least:

- `/threads/<id>`
- `/`

Potentially later:

- filtered board views if they exist
- activity surfaces if label changes become visible activity items

Retained V1 rule:

- emit a compact label-update activity item when hashtags add new labels to a thread

This should use the same canonical thread-label record as the source of truth rather than inventing a separate storage path.

## Backward Compatibility

This proposal is additive.

It does not require:

- changing existing `records/posts/` files
- changing old thread roots
- changing the current meaning of `Board-Tags`

Threads with no label records simply resolve to an empty label set.

## Testing Expectations

The first implementation should add coverage for:

- parser acceptance of valid additive label records
- parser rejection of invalid operations and label tokens
- read-model reduction across multiple label records
- deterministic ordering when timestamps tie
- rejection of label records targeting missing threads
- board/thread rendering of current labels
- write-path creation of canonical thread-label records
- hashtag extraction from thread roots and replies
- quoted-line hashtag exclusion

## Suggested Implementation Slices

### Slice 1: Canonical Contract

- write a spec for `records/thread-labels/`
- implement parser and repository loading
- add parser tests

### Slice 2: Read-Model Reduction

- index thread-label records during rebuild
- extend `threads` table with `thread_labels_json`
- expose labels in fetch queries
- add rebuild tests

### Slice 3: Read UI

- render labels on board and thread pages
- keep empty-state rendering unobtrusive
- add smoke coverage

### Slice 4: Write Flow

- add hashtag extraction to thread and reply write flows
- commit and invalidate artifacts
- add smoke tests

### Slice 5: Policy And UX Refinement

- add direct label-management controls or endpoint later if still wanted
- add filtering UI later if still wanted
- refine activity/history presentation if needed

## Retained Product Decisions

The following product decisions are retained for V1:

- approved users may add labels to threads
- anonymous users may add labels only indirectly through hashtags when they are creating a thread or reply
- `Reason` is optional and not required for V1
- labels appear on both board cards and thread pages
- label vocabulary is free-form within the validated token rules in this document
- label changes emit compact activity items in V1

Filtering by label is intentionally out of scope for this plan.

## Retained Implementation Decisions

The following implementation details are retained for V1:

- invalid committed thread-label records are skipped during read-model rebuild and surfaced separately in metadata or debug output
- rebuild remains soft-failing for invalid thread-label records so the rest of derived state stays usable
- canonical files use `records/thread-labels/<record-id>.txt`
- filename must equal `Record-ID`
- record ID generation should be hidden behind helpers rather than built manually by callers
- the reduced final label list stored in `thread_labels_json` uses lexical ascending order by normalized label token
- rendered labels use that same lexical order in V1
- if two thread-label records share the same `Created-At`, `Record-ID` lexical order is the tie-breaker
- duplicate labels in one write are normalized and collapsed before writing the canonical record
- parser and reducer behavior should also tolerate duplicate labels in committed input
- hidden and internal threads are label-compatible at the canonical and read-model level
- hashtags are extracted from both thread roots and replies
- hashtag extraction only counts authored lines and ignores quoted lines

## Recommendation

Implement post-creation thread labels as a new append-only canonical record family in `records/thread-labels/`, not as:

- extra `Board-Tags`
- mutable root-post headers
- magic reply-body conventions

That direction matches the existing architecture best and leaves room for:

- current-state rendering
- audit history
- future permissions
- future metadata update families that follow the same pattern

The core idea is simple:

- content stays in posts
- hashtag-bearing posts can emit append-only thread-label events
- the read model reduces those events into current thread state
