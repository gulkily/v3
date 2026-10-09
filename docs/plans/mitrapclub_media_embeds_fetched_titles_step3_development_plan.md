> **Feature plan:** [Step 1](./mitrapclub_media_embeds_fetched_titles_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_fetched_titles_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_fetched_titles_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_fetched_titles_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** A `mitrapclub` member posts a thread with no subject whose body is a single recognized YouTube or Instagram URL, with `FORUM_MEDIA_EMBEDS_ENABLED` on.
- **End-to-end outcome:** The thread shows `"Untitled"` immediately; a later view's background beacon fetches the linked content's real title and writes it as the thread's subject; a subsequent view shows that real title. The embed renders in the body on every view, from the first one.
- **Required recovery:** A fetch that never succeeds leaves the title at `"Untitled"` indefinitely — never broken, never reverting to the raw URL as a title. The flag off, or any thread with an existing subject, is completely unaffected.
- **Deployment/external verification:** No deploy-time step beyond the flag itself (already off everywhere); YouTube oEmbed's live behavior cannot be verified from this sandbox (no outbound network) — recorded as a known gap, verified for real once deployed somewhere with connectivity.
- **Release condition:** Flag stays off everywhere per existing posture; full test suite green; Stage 8's manual verification (title Untitled → real, embed rendering throughout, write path round-trip) complete.

## Key Risks

- **New write path touching an already-published thread.** Early validation: the new record type mirrors `ThreadLabelRecord`'s already-proven shape exactly (Stage 1), and the write (Stage 3) only ever fires when the thread's current subject is empty. Mitigation: no new write semantics are invented — this is the existing append-only-record-plus-read-model-projection pattern, applied to a new field.
- **Read-model projection must stay correct in both the incremental updater and the full rebuilder**, which already maintain thread labels as two separate code paths — a common source of drift in this codebase if only one is updated. Early validation: Stage 2 explicitly touches both, with a shared test asserting incremental and full-rebuild results match, mirroring existing read-model-parity tests. Mitigation: build the full-rebuild path first to use as the "known correct" reference, then make the incremental path match it, same order existing parity tests use elsewhere.
- **Concurrent beacon hits could write two subject-set records for the same thread** (rare: only possible between the first successful fetch and the read model reflecting it). Early validation: Stage 2's loader test covers "more than one record exists for a thread." Mitigation: the loader deterministically picks the earliest one — harmless redundancy, not a correctness bug, and the existing thread-label loader already handles "more than one record for the same thread" the same way.
- **YouTube oEmbed's continued keyless availability is unverified against live traffic.** Early validation: Stage 4's fetcher is tested only against synthetic responses, same caveat as Instagram's page-scraper in Cycle 6. Mitigation: identical graceful-degradation posture as every other fetch in this feature — a failure just leaves `"Untitled"`.

## Stage 1
- Goal: Add the new append-only canonical record type for setting a thread's subject after creation.
- Dependencies: None.
- Expected changes: New `ThreadSubjectRecord` (readonly properties: `recordId`, `createdAt`, `threadId`, `operation`, `subject`, `authorIdentityId` (nullable), `reason` (nullable), `body`, `actionAt`/`intentId` (nullable)) and `ThreadSubjectRecordParser`, mirroring `ThreadLabelRecord`/`ThreadLabelRecordParser` exactly — required headers `Record-ID`, `Created-At`, `Thread-ID`, `Operation`, `Subject`; `Operation` must be `set` in V1 (parallel to labels' `add`-only V1 constraint). New `CanonicalPathResolver::threadSubject(string $recordId): string`, mirroring `::threadLabel()`.
- Verification approach: New unit tests mirroring the existing `ThreadLabelRecordParser` test coverage — valid record parses, each missing required header rejected, wrong `Operation` rejected.
- Risks or open questions: None — directly modeled on a proven, already-tested precedent.
- Canonical components/API contracts touched: New `ThreadSubjectRecord`, `ThreadSubjectRecordParser`; extends `CanonicalPathResolver`.

## Stage 2
- Goal: Project thread-subject records into the read model's existing `threads.subject` column, in both the full rebuild and the incremental updater.
- Dependencies: Stage 1.
- Expected changes: `ReadModelBuilder` gains a thread-subject record loading/application step alongside its existing thread-label handling, writing into `subject` only when the thread's own subject is currently blank and at least one thread-subject record exists (earliest one, if more than one). `IncrementalReadModelUpdater` gains a parallel `applyThreadSubjectWrite(string $threadId, string $commitSha): array`, mirroring `applyThreadLabelWrite()`.
- Verification approach: New tests mirroring existing thread-label read-model tests — a thread-subject record updates a blank subject; a thread with an existing non-blank subject is left untouched even if a thread-subject record exists; multiple records for one thread resolve deterministically; a parity test confirms the incremental path's result matches a full rebuild for the same fixture.
- Risks or open questions:
  - Impact: incremental/full-rebuild drift (see Key Risks).
  - Early warning: the parity test.
  - Mitigation: build the full-rebuild path first, make the incremental path match it.
- Canonical components/API contracts touched: `ReadModelBuilder`, `IncrementalReadModelUpdater` (both extended in place, mirroring their existing thread-label handling).

## Stage 3
- Goal: Add the narrow, system-only write path that sets a thread's subject when it's still empty.
- Dependencies: Stage 1, Stage 2.
- Expected changes: `LocalWriteService` gains `setThreadSubjectIfEmpty(string $threadId, string $subject): bool` — reads the thread's current subject from the read model; if non-empty, returns `false` without writing anything; otherwise builds and commits a `ThreadSubjectRecord` (`Operation: set`, no `Author-Identity-ID` — system-authored, same as an unsigned thread-label precedent), triggers `applyThreadSubjectWrite()`, and returns `true`. Per Step 1's recommendation, this stays a plain internal method — no new public API endpoint, no authorization model beyond "only this feature's own background step calls it."
- Verification approach: New tests — a blank-subject thread gets the record written and the read model updated; a thread that already has a subject is left untouched and the method returns `false`; the written record round-trips through Stage 1's parser.
- Risks or open questions: None beyond Key Risks already covering the write path generally.
- Canonical components/API contracts touched: `LocalWriteService` (new method, same commit/write conventions as existing record-building methods).

## Stage 4
- Goal: Add a YouTube oEmbed title fetcher, independently testable, not yet wired into anything.
- Dependencies: None.
- Expected changes: New `YoutubeOembedTitleFetcher::fetch(string $url): ?array{title: string, thumbnailUrl: ?string}`, mirroring `InstagramPagePreviewFetcher`'s injectable-transport pattern, calling `https://www.youtube.com/oembed?url={url}&format=json` and parsing `title`/`thumbnail_url` from the JSON response; any failure (timeout, non-200, malformed JSON, missing `title`) returns `null`.
- Verification approach: New unit tests with an injected fake transport — success parse, missing-title failure, malformed-JSON failure, transport failure.
- Risks or open questions:
  - Impact: YouTube oEmbed's live availability is unverified (see Key Risks).
  - Early warning: none available from this sandbox.
  - Mitigation: graceful-degradation posture, same as every other fetch in this feature.
- Canonical components/API contracts touched: New `YoutubeOembedTitleFetcher` — net-new, following `InstagramPagePreviewFetcher`'s existing convention.

## Stage 5
- Goal: Teach title generation to show `"Untitled"` for a bare recognized media URL instead of the raw link, gated behind the media-embeds flag, and expose the shared detection it and the beacon (Stage 7) both need.
- Dependencies: None (reuses the already-public `MediaEmbedDetector::classify()`).
- Expected changes: New `ThreadTitle::bareMediaEmbedMatch(string $subject, string $body): ?array{provider: string, embedId: string, url: string}` — returns a match only when `$subject` is blank and the whole (trimmed) body is exactly one recognized media URL, `null` otherwise. `ThreadTitle::displayTitle()` gains a `bool $mediaEmbedsEnabled = false` parameter; when true and `bareMediaEmbedMatch()` finds a match, returns `"Untitled"` instead of falling through to the body-excerpt branch. Default `false` keeps every existing call site byte-identical until updated. The 6 existing call sites (`Application.php`, `BoardPageController.php`, `ForteBoardController.php`, `ForteContentAndUserDetailApiController.php`, `TemplateRenderer.php`) are updated to pass the flag's current value through.
- Verification approach: Extend `tests/ThreadTitleTest.php` — flag off is byte-identical to today for every existing case, including a bare-URL body; flag on shows `"Untitled"` only for a bare-URL, no-subject body, and is unchanged for ordinary text excerpts and any thread with a subject.
- Risks or open questions:
  - Impact: a missed call site keeps showing the raw URL as a title in that one spot while the rest of the app shows `"Untitled"` — inconsistent, not broken.
  - Early warning: grepping for every `ThreadTitle::displayTitle` call site before closing this stage (the same 6 files found during Step 3 planning).
  - Mitigation: update all 6 in this stage, not a subset.
- Canonical components/API contracts touched: `ThreadTitle` (extended in place); the 6 existing call sites of `displayTitle()`.

## Stage 6
- Goal: Extend the existing warm-cache endpoint to fetch a YouTube title too, and to back-fill a thread's subject when asked.
- Dependencies: Stage 3, Stage 4.
- Expected changes: `MediaEmbedPreviewController::warmPreview()` accepts `provider` of either `instagram` or `youtube` (dispatching to `InstagramPagePreviewFetcher` or the new `YoutubeOembedTitleFetcher` respectively, same cache-check-first/backoff logic for both, reusing `MediaEmbedPreviewCacheStore` unchanged since it's already generic by provider). Accepts an optional `threadId`; when present and a fetch succeeds, calls `LocalWriteService::setThreadSubjectIfEmpty($threadId, $fetchedTitle)`.
- Verification approach: Extend `tests/MediaEmbedPreviewControllerTest.php` — a YouTube URL behaves like the existing Instagram cases (cold/warm/backoff, cache keyed correctly by provider); a request with `threadId` and a successful fetch calls the subject write path; a request without `threadId` behaves exactly as before (no write-path call).
- Risks or open questions:
  - Impact: this endpoint already had to be abuse-bounded (Cycle 6); adding a second provider and a write side-effect widens that surface slightly.
  - Early warning: the existing strict-validation tests (`classify()` rejection, cache-check-before-fetch) extended to cover `youtube` the same way.
  - Mitigation: no new trust boundary — `threadId` only ever triggers a call into Stage 3's already-safe-by-construction (empty-check first) write path, never arbitrary data.
- Canonical components/API contracts touched: `MediaEmbedPreviewController` (extended in place).

## Stage 7
- Goal: Emit the title-fetch beacon from the two render sites that show a thread's title, only when it would matter.
- Dependencies: Stage 5, Stage 6.
- Expected changes: `thread_card.php` and `thread_root_card.php` call `ThreadTitle::bareMediaEmbedMatch()` (already shared with Stage 5) when the media-embeds flag is on and the computed title is `"Untitled"`; if it returns a match, emit the same kind of hidden, eager-loading beacon `<img>` Cycle 6 already uses, pointed at Stage 6's endpoint with `provider`, `url`, and this thread's `threadId`.
- Verification approach: Extend `tests/MediaEmbedRendererTest.php`-style direct `renderFragment()` checks (or new focused tests) for both templates — flag off emits no beacon; flag on with an ordinary subject or ordinary text emits no beacon; flag on with a bare-URL, no-subject thread emits exactly one beacon with the correct `threadId`.
- Risks or open questions: None beyond Key Risks already covering the endpoint and the write path.
- Canonical components/API contracts touched: `thread_card.php`, `thread_root_card.php` (both extended in place, no new templates).

## Stage 8
- Goal: End-to-end verification of the full title → fetch → write → re-render loop, then close out the checklist.
- Dependencies: Stage 7.
- Expected changes: Verification only; update the Cycle 7 entry in `mitrapclub_theme_and_features_checklist.md` once verified.
- Verification approach: Full test suite. A manual, scripted round-trip (mirroring Cycle 6's Stage 7 approach): create a no-subject thread whose body is a bare recognized URL, confirm `"Untitled"` and a rendering embed on first view; drive the beacon's endpoint directly; confirm the thread's subject is now set in the read model and the title updates on the next render; confirm a thread with an existing subject, and a thread with ordinary text, are both unaffected throughout.
- Risks or open questions:
  - Impact: a gap between the individually-tested stages and the real request-path wiring (beacon → endpoint → write path → read model → next render) going unnoticed.
  - Early warning: this stage's manual pass specifically exercises that full loop, not just each piece in isolation.
  - Mitigation: none needed beyond running the checks.
- Canonical components/API contracts touched: None (verification stage).
