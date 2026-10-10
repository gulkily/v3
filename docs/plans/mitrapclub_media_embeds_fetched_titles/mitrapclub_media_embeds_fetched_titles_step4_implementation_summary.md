> **Feature plan:** [Step 1](./mitrapclub_media_embeds_fetched_titles_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_fetched_titles_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_fetched_titles_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_fetched_titles_step4_implementation_summary.md)

## Stage 1 - ThreadSubjectRecord canonical record type

- Changes:
  - Added `ForumRewrite\Canonical\ThreadSubjectRecord`/`ThreadSubjectRecordParser`, mirroring `ThreadLabelRecord`/`ThreadLabelRecordParser` exactly: required headers `Record-ID`, `Created-At`, `Thread-ID`, `Operation`, `Subject`; `Operation` must be `set` in V1; optional `Author-Identity-ID` (nullable, same OpenPGP-form validation), `Reason`, `Action-At`, `Intent-ID`.
  - Added `CanonicalPathResolver::threadSubject()` (`records/thread-subjects/{recordId}.txt`) and `CanonicalRecordRepository::loadThreadSubject()`, mirroring their thread-label counterparts.
  - Also extended `SourcePathValidator::isValidCanonicalRecordPath()` for the new `records/thread-subjects/` family - not explicitly named in Step 3's text, but the same canonical-path-shape plumbing `CanonicalPathResolver` belongs to, needed by the `/source/*` activity links once records of this type exist.
  - New fixture `tests/fixtures/parity_minimal_v1/records/thread-subjects/thread-subject-20260415153000-ab12cd34.txt` (unsigned, system-authored) and matching tests in `tests/CanonicalRecordParsersTest.php`: valid-record parse, unsupported-`Operation` rejection, missing-each-required-header rejection, repository family-load, repository path-mismatch rejection, path-resolver spec.
- Verification:
  - Full suite: 903 run, 901 passed (2 pre-existing long-standing failures, unrelated).
- Notes:
  - Directly modeled on an already-proven precedent - no new conventions invented.

## Stage 2 - Read-model projection (full rebuild + incremental)

- Changes:
  - `ReadModelBuilder::indexThreadSubjects()`: loads all thread-subject records, sorts by `(createdAt, recordId)`, and for each thread whose current `subject` is blank, writes the *earliest* record's subject into both `threads.subject` and the root post's own `posts.subject` (writing the post row too, not just the thread row, so the per-post title call site in `ForteContentAndUserDetailApiController` stays correct). Never overwrites a non-blank subject. Adds a parallel `thread_subject_invalid_count` metadata key.
  - `IncrementalReadModelUpdater::applyThreadSubjectWrite(string $threadId, string $commitSha): array`, mirroring `applyThreadLabelWrite()`'s shape but much simpler (no scoring/activity-event side effects - just the subject itself).
  - New `tests/ReadModelThreadSubjectsTest.php`: blank-subject thread picks up the earliest of several records and skips a record targeting an unknown thread; an existing non-blank subject is never overwritten; a parity test confirms the incremental path's result matches a fresh rebuild for the same fixture (both `threads.subject` and `posts.subject`).
- Verification:
  - Full suite: 906 run, 904 passed (same 2 pre-existing failures).
- Notes:
  - Built the full-rebuild path first as the "known correct" reference, then matched the incremental path to it, per Step 3's own risk mitigation ordering.

## Stage 3 - Narrow system-only write path

- Changes:
  - `LocalWriteService::setThreadSubjectIfEmpty(string $threadId, string $subject): bool` - reads the thread's current subject from the read model; no-ops (`false`) if non-blank; otherwise builds+writes a `ThreadSubjectRecord` (unsigned, `Operation: set`), commits it, syncs derived state (`synchronizeThreadSubjectDerivedState()`, mirroring the thread-label sync's incremental-with-rebuild-fallback shape), invalidates the board/thread static artifacts, and returns `true`. No public API endpoint - plain internal method, per Step 1's recommendation.
  - New `tests/LocalWriteServiceThreadSubjectTest.php`: a blank-subject thread gets the record written and the read model/rendered page updated; an already-subject'd thread is left untouched and the method returns `false`.
- Verification:
  - Full suite: 908 run, 906 passed (same 2 pre-existing failures).
- Notes:
  - **Bug found and fixed during this stage:** the read-only pre-check (`SELECT subject FROM threads ...`) left its `PDOStatement`/connection open for the rest of the method's lifetime (the variables stayed in scope), which blocked the incremental updater's own write connection to the same SQLite file - "database is locked" after the full ~2-minute busy-timeout. Fixed by explicitly calling `closeCursor()` and `unset()`-ing the statement/connection right after reading the one value needed, before any further write work. First test run took 120s; after the fix, the same test takes ~0.1s.

## Stage 4 - YouTube oEmbed title fetcher

- Changes:
  - Added `ForumRewrite\View\YoutubeOembedTitleFetcher::fetch(string $url): ?array{title, thumbnailUrl}`, mirroring `InstagramPagePreviewFetcher`'s injectable-transport pattern. Calls `https://www.youtube.com/oembed?format=json&url={encoded}`; any failure (transport failure, empty response, malformed JSON, missing/empty `title`) returns `null`.
  - New `tests/YoutubeOembedTitleFetcherTest.php`: success parse (with and without `thumbnail_url`), missing-title failure, malformed-JSON failure, transport failure, empty-response failure, and a check that the requested URL is built correctly (`format=json` + `rawurlencode`d target).
- Verification:
  - Full suite: 915 run, 913 passed (same 2 pre-existing failures).
- Notes:
  - Not yet wired into anything - independently testable only, same as Cycle 6's Stage 3 fetcher. Live availability of the endpoint was unverified at the time this stage was written (no outbound network from this sandbox); **confirmed live and working during Stage 8's end-to-end check** - see that stage's notes.

## Stage 5 - "Untitled" title + shared bare-URL detection

- Changes:
  - `ThreadTitle::bareMediaEmbedMatch(string $subject, string $body): ?array{provider, embedId, url}` - returns a match only when `$subject` is blank and the whole trimmed `$body` is exactly one `MediaEmbedDetector`-recognized URL (checked via `detect()`'s offset/length, not just `classify()`, so trailing/leading text correctly excludes a match).
  - `ThreadTitle::displayTitle()` gained a `bool $mediaEmbedsEnabled = false` parameter (default keeps every untouched call site byte-identical); when `true` and `bareMediaEmbedMatch()` matches, returns the literal string `"Untitled"` instead of falling through to the body-excerpt branch. (Distinct from the pre-existing `'Untitled thread'` empty-body fallback - no collision.)
  - Threaded the flag through all 5 production call sites: `Application::displayThreadTitle()`, `BoardPageController::displayThreadTitle()`, `ForteBoardController`'s inline call, `ForteContentAndUserDetailApiController::forteContentSummary()`, `TemplateRenderer::renderFile()`'s `$threadTitle` closure. Added `RouteServices::featureFlags(): FeatureFlagEvaluator` since three of those controllers had no existing access to the flag evaluator at all.
  - Extended `tests/ThreadTitleTest.php`: flag off is byte-identical for a bare-URL body (regression); flag on shows `"Untitled"` only for a bare-URL/no-subject body; flag on leaves an ordinary text excerpt, an existing subject, and a URL-with-extra-text body unchanged; direct `bareMediaEmbedMatch()` coverage (match, subject present, extra text before/after, ordinary text, unrecognized URL, empty body).
- Verification:
  - Full suite: 924 run, 922 passed (same 2 pre-existing failures).
- Notes:
  - Grepped for every `ThreadTitle::displayTitle` call site before closing this stage, per Step 3's explicit mitigation for the "missed call site" risk - all 5 production sites found and updated, none skipped.

## Stage 6 - Extend warm-cache endpoint for YouTube + subject backfill

- Changes:
  - `MediaEmbedPreviewController::warmPreview()` now dispatches on `provider` of either `instagram` or `youtube` (new `warmYoutubePreview()`, same cache-check-first/backoff shape as the existing Instagram path, reusing `MediaEmbedPreviewCacheStore` unchanged since it's already generic by `(provider, embedId)`).
  - Accepts an optional `thread_id` query parameter; a shared `backfillThreadSubject()` calls `LocalWriteService::setThreadSubjectIfEmpty($threadId, $title)` whenever a fetch (fresh *or* already-cached-warm) produces a non-null title - covering the cross-thread case where two different threads link the same video/post and the second thread's beacon hits an already-warm cache.
  - Extended `tests/MediaEmbedPreviewControllerTest.php`: YouTube cold/warm/backoff cases mirroring the existing Instagram ones; a same-`embedId`-across-providers test confirming the cache key includes provider; `thread_id` backfill on a successful fetch, no backfill when the fetch fails, no backfill when `thread_id` is absent from the request (regression).
- Verification:
  - Full suite: 931 run, 929 passed (same 2 pre-existing failures).
- Notes:
  - No new trust boundary: `thread_id` only ever reaches `setThreadSubjectIfEmpty()`'s already-safe-by-construction (empty-subject-only) write path, never arbitrary data.

## Stage 7 - Wire the beacon into the title-rendering templates

- Changes:
  - `thread_card.php` and `thread_root_card.php` both call `ThreadTitle::bareMediaEmbedMatch()` (shared with Stage 5) when the media-embeds flag is on and the computed title is exactly `"Untitled"`; on a match, emit the same kind of hidden, eager-loading `<img>` beacon Cycle 6 already uses, pointed at Stage 6's endpoint with `provider`, `url`, and `thread_id`.
  - `TemplateRenderer::renderFile()` now exposes `mediaEmbedsEnabled` to every template via its existing data-merge (alongside `unicodeAuthoredTextEnabled` etc.), since three of the templates needing it had no other route to the flag.
  - New `tests/MediaEmbedBeaconTemplatesTest.php`, rendering both partials directly via `TemplateRenderer::renderFragment()`: flag off emits no beacon; flag on with an ordinary subject or ordinary text emits no beacon; flag on with a bare-URL/no-subject thread emits exactly one beacon with the correct `provider`/`url`/`thread_id`.
- Verification:
  - Full suite: 939 run, 937 passed (same 2 pre-existing failures).
- Notes:
  - First test run caught a real gap: `thread_root_card.php`'s `$title` comes from page-level data the test must supply itself (it isn't recomputed inside the partial) - fixed by computing it via `ThreadTitle::displayTitle()` in the test harness, the same way the real page controller does.

## Stage 8 - End-to-end verification and checklist close-out

- Verification:
  - Full suite: 939 run, 937 passed (same 2 pre-existing, unrelated long-standing failures: `testFeatureFlagsPageShowsLockedBadgeWithReasonForNonMutableFlags`, `testTaskQueueProcessesQueuedAgentReplyOnce`).
  - Manual scripted round-trip against a real git-backed temp repository, driving the actual `Application::handle()` routes (not just unit-level calls): created a no-subject thread whose body is a single bare YouTube URL, plus two controls (an ordinary no-subject text thread, and `root-001`'s existing `Subject: Hello world`). Confirmed:
    - First view: bare-URL thread shows literal `"Untitled"` (never the raw URL), the embed still renders, and the title-fetch beacon is emitted with the correct `provider`/`url`/`thread_id`.
    - Both controls show their normal title throughout and never emit a beacon.
    - Driving the extracted beacon URL through `Application::handle()` directly (exactly as the browser-loaded `<img>` would) and reloading the thread page: the real fetched title now shows, the embed still renders, and the beacon stops appearing (since the title is no longer `"Untitled"`).
    - The board listing (`/`) resolves the same way - no raw-URL leak on the board card either.
  - **Resolved risk:** this sandbox turned out to have outbound network access after all, so the manual round-trip exercised a real fetch against YouTube's live oEmbed endpoint - it succeeded and returned the real video title. This closes the "YouTube oEmbed's continued keyless availability is unverified" caveat carried since Step 1; both this feature's fetcher and the graceful-degradation path (exercised separately by the injected-transport unit tests) are now confirmed correct.
- Checklist: updated `docs/plans/mitrapclub_theme_and_features_checklist.md`'s "Media embeds" entry with a Cycle 7 paragraph describing the fetched-title mechanism, the new write path, and its scope relative to a possible future manual-retitle feature.
- Release condition: flag (`FORUM_MEDIA_EMBEDS_ENABLED`) stays off everywhere per existing posture - this cycle ships the mechanism only; enabling it anywhere remains a manual operator action, unchanged from Cycles 5/6.
