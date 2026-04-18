# PHP Thread Labels After Creation Implementation Checklist V1

This document turns the retained proposal in `docs/plans/php_thread_labels_after_creation_proposal_v1.md` into a concrete implementation checklist for the current PHP codebase.

## Goal

Implement append-only thread labels in a way that keeps canonical parsing, read-model behavior, UI rendering, and write orchestration reviewable in small slices.

## Scope

Retained V1 scope:

- add a new canonical family in `records/thread-labels/`
- support additive label events only
- derive current thread labels into the SQLite read model
- render current labels on board and thread pages
- create labels from hashtags in thread-root and reply compose flows
- emit compact activity items for hashtag-driven label additions

Out of scope for V1:

- direct `POST /api/update_thread_labels`
- filtering UI
- remove or set operations
- dedicated label-management controls

## Slice 1: Canonical Contract

Focus:

- define and validate the new canonical record family before touching reads or writes

Checklist:

- add a spec document for the canonical record family under `docs/specs/`
- create `src/ForumRewrite/Canonical/ThreadLabelRecord.php`
- create `src/ForumRewrite/Canonical/ThreadLabelRecordParser.php`
- extend `src/ForumRewrite/Canonical/CanonicalPathResolver.php` with `threadLabel()`
- extend `src/ForumRewrite/Canonical/CanonicalRecordRepository.php` with `loadThreadLabel()`
- enforce `records/thread-labels/<record-id>.txt`
- enforce filename equality with `Record-ID`
- validate required headers: `Record-ID`, `Created-At`, `Thread-ID`, `Operation`, `Labels`
- validate `Operation: add` only
- validate label token normalization and rejection rules
- validate optional `Author-Identity-ID`
- decide whether `Reason` parsing lives as a nullable property on `ThreadLabelRecord`
- add fixture records under `tests/fixtures/` for valid and invalid thread-label files
- extend `tests/CanonicalRecordParsersTest.php` with parser and repository coverage
- extend `tests/CanonicalRecordParsersTest.php` with `CanonicalPathResolver` assertions for the new family

Expected outcome:

- thread-label records have a stable parser and path contract
- invalid records fail early at canonical-parse time
- other layers can depend on a typed `ThreadLabelRecord`

## Slice 2: Full Rebuild Read Model

Focus:

- materialize current thread labels during a full rebuild before wiring incremental writes

Likely files:

- `src/ForumRewrite/ReadModel/ReadModelBuilder.php`
- `src/ForumRewrite/ReadModel/ReadModelMetadata.php`
- `src/ForumRewrite/Canonical/CanonicalRecordRepository.php`

Checklist:

- extend `threads` schema in `ReadModelBuilder` with `thread_labels_json TEXT NOT NULL`
- bump the read-model schema version if required by current metadata conventions
- load `records/thread-labels/` during rebuild
- validate each label record target against known root threads
- skip invalid committed label records and surface them in metadata or debug output
- sort label records by `Created-At` ascending and `Record-ID` ascending
- reduce to the final per-thread label set
- store reduced labels in lexical ascending order in `thread_labels_json`
- ensure threads with no label records persist `[]`
- keep hidden/internal threads label-compatible in the reducer
- add rebuild coverage for multiple label records against one thread
- add rebuild coverage for duplicate adds
- add rebuild coverage for same-timestamp tie-breaking
- add rebuild coverage for missing-thread label records being skipped without killing the rebuild

Expected outcome:

- a clean rebuild produces stable label state for every thread
- label state is deterministic and reproducible from canonical inputs

## Slice 3: Read Queries And Rendering

Focus:

- expose the materialized labels in board and thread reads

Likely files:

- `src/ForumRewrite/Application.php`
- templates used by board and thread rendering
- `tests/LocalAppSmokeTest.php`

Checklist:

- extend thread fetch queries in `Application.php` to select `thread_labels_json`
- decode `thread_labels_json` in the board-page data path
- decode `thread_labels_json` in the single-thread data path
- render labels on the board thread list
- render labels on the thread page near the title or metadata line
- keep empty-label rendering unobtrusive
- keep post permalinks unchanged unless thread-context rendering already exists there
- add smoke coverage proving labels appear on `/`
- add smoke coverage proving labels appear on `/threads/<id>`
- add smoke coverage proving threads with no labels still render cleanly

Expected outcome:

- the read model exposes labels to the main user-facing surfaces
- label display is visible without needing write-path work

## Slice 4: Write Flow For Hashtags

Focus:

- emit canonical thread-label records from normal posting flows

Likely files:

- `src/ForumRewrite/Write/LocalWriteService.php`
- `src/ForumRewrite/Canonical/ThreadLabelRecordParser.php`
- `src/ForumRewrite/Canonical/CanonicalPathResolver.php`
- `tests/WriteApiSmokeTest.php`

Checklist:

- add hashtag extraction helpers in `LocalWriteService`
- ignore quoted lines during hashtag extraction
- normalize and de-duplicate labels per post before write
- keep extracted labels free-form within the canonical token rules
- after `createThread()` writes a root post, emit one thread-label record if hashtags are present
- after `createReply()` writes a reply, emit one thread-label record targeting the thread if hashtags are present
- include `Author-Identity-ID` on label records when available
- omit `Reason` for normal hashtag-derived writes in V1 unless there is already a strong UI reason to populate it
- parse each generated label record before writing it
- commit the post file plus any label record file together in one git commit
- update artifact invalidation so the board and target thread refresh when label records are written
- ensure no label record is written when no valid hashtags are present
- add smoke coverage for thread creation with hashtags
- add smoke coverage for reply creation with hashtags
- add smoke coverage for quoted-line hashtags being ignored
- add smoke coverage for duplicate hashtags collapsing to one stored label

Expected outcome:

- normal posting flows can create labels without mutating posts
- canonical history contains explicit label events tied to the posting action

## Slice 5: Incremental Derived State And Activity

Focus:

- avoid forcing a full rebuild after every labeled write and make activity reflect label additions

Likely files:

- `src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php`
- `src/ForumRewrite/Write/LocalWriteService.php`
- `src/ForumRewrite/Application.php`
- `src/ForumRewrite/ReadModel/ReadModelBuilder.php`
- `tests/WriteApiSmokeTest.php`
- `tests/LocalAppSmokeTest.php`

Checklist:

- decide whether to add a dedicated incremental updater path for `ThreadLabelRecord` or deliberately fall back to full rebuild for V1
- if adding an incremental path, teach `IncrementalReadModelUpdater` how to update `threads.thread_labels_json`
- keep incremental label reduction behavior consistent with full rebuild behavior
- add activity rows for label additions using the retained V1 activity policy
- decide whether label activity needs a new `kind` value such as `thread_label_add`
- ensure activity rendering in `Application.php` can present label updates cleanly
- ensure hidden/internal thread labels do not leak via activity if the thread is not otherwise visible
- add write smoke coverage for labeled writes becoming visible immediately
- add write smoke coverage for incremental refresh failure behavior if this path is incrementalized

Expected outcome:

- labeled writes show up immediately in derived state
- activity behavior matches the retained V1 product decision

## Cross-Cutting Notes

Watch for these repo-specific integration points:

- `CanonicalRecordRepository` currently has one loader per record family; follow that pattern rather than widening `PostRecordParser`
- `ReadModelBuilder` currently derives `threads` entirely from posts; thread labels will be the first separate thread-metadata family, so keep that reducer isolated and explicit
- `LocalWriteService` currently commits one or more canonical files and then refreshes derived state; label-record writes should fit the same transaction boundary
- `Application.php` currently reads thread rows directly from SQLite; prefer extending existing queries rather than inventing a second label lookup path
- `WriteApiSmokeTest.php` and `LocalAppSmokeTest.php` already cover thread/reply and activity behavior, so extend those before inventing brand-new smoke harnesses

## Suggested Verification Order

1. Run parser tests after Slice 1.
2. Run rebuild-focused tests after Slice 2.
3. Run local app smoke tests after Slice 3.
4. Run write smoke tests after Slice 4.
5. Re-run the full relevant test set after Slice 5.

## Minimum V1 Merge Bar

Before merging, confirm all of the following:

- canonical thread-label records parse and round-trip through the repository
- full rebuild stores deterministic `thread_labels_json`
- board and thread pages render labels correctly
- thread creation and reply creation emit label records from hashtags
- quoted hashtags do not create labels
- duplicate hashtags do not create duplicate stored labels
- labeled writes refresh derived state correctly
- activity behavior for label additions matches the retained proposal
