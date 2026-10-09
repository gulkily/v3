> **Feature plan:** [Step 1](./mitrapclub_media_embeds_inline_player_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_inline_player_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_inline_player_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_inline_player_step4_implementation_summary.md)

## Stage 1 - Feature flag

- Changes:
  - Added `FeatureFlagRegistry::MEDIA_EMBEDS_INLINE_PLAYER_ENABLED` (`FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED`) and its `FeatureFlagDefinition` entry (`siteMutable: true`, default `false`, `requiresEnabledFlag: self::MEDIA_EMBEDS_ENABLED`), positioned after `MEDIA_EMBEDS_ENABLED`.
  - Updated `tests/FeatureFlagEvaluatorTest.php`'s hardcoded public-flag canary list and env-cleanup key list to include the new flag.
  - Added `testMediaEmbedsInlinePlayerDependsOnMediaEmbedsEnabled`, mirroring the existing `testEmojiAuthoredTextDependsOnUnicodeAuthoredText` dependency test.
- Verification:
  - `php tests/run.php FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest` — 17 run, 17 passed.
- Notes:
  - Same mechanism already proven by the `EMOJI_AUTHORED_TEXT`/`UNICODE_AUTHORED_TEXT` pair — no new flag-evaluation logic needed.

## Stage 2 - Expose embed identifier

- Changes:
  - `MediaEmbedDetector::classify()` now captures and returns the raw embed identifier (YouTube video ID / Instagram shortcode) alongside the provider, via a regex capture group instead of a non-capturing match.
  - `detect()`'s return shape gained an `embedId` field per match.
  - Extended `tests/MediaEmbedDetectorTest.php` with `embedId` assertions for every known YouTube/Instagram shape already covered.
- Verification:
  - `php tests/run.php MediaEmbedDetectorTest MediaEmbedRendererTest` — 10 run, 10 passed. `MediaEmbedRendererTest` (Cycle 5, untouched) still passes, confirming the additive field doesn't disturb Cycle 5's existing consumer.
- Notes:
  - Purely additive to an already pure, already-tested function — no behavior change for any existing caller.

## Stage 3 - Preview cache store and Instagram page fetcher

- Changes:
  - Added `ForumRewrite\View\MediaEmbedPreviewDatabaseConfig::path()` returning `state/cache/media_embed_previews.sqlite3`, following `LlmExchangeDatabaseConfig`/`FastScoreDatabaseConfig`'s pattern.
  - Added `ForumRewrite\View\MediaEmbedPreviewCacheStore` with `get(provider, embedId)`/`put(provider, embedId, ?title, ?thumbnailUrl)` against a lazily-created `media_embed_previews` table (`PRIMARY KEY (provider, embed_id)`). A failed fetch is stored with null `title`/`thumbnailUrl` but a real `fetchedAt`, so "recently attempted and failed" is distinguishable from "never attempted."
  - Added `ForumRewrite\View\InstagramPagePreviewFetcher::fetch(string $url)`, using the same minimal `file_get_contents`/`stream_context_create` pattern as `AnthropicStructuredChatProvider` (3s timeout), parsing only `og:title`/`og:image` `<meta>` tags (handling both attribute orders, decoding HTML entities); any failure, non-200, or missing tag returns `null`. Takes an injectable transport closure for testing without a real network call.
  - **Design refinement from Step 3's text:** rather than inventing a second, separate "shortcode shape" validator for Stage 4's endpoint to check an `id` parameter, `MediaEmbedDetector::classify()` was changed from `private` to `public` (Stage 2) so the endpoint can validate a full candidate URL and extract its `embedId` through the exact same rules the detector already uses — one validator, not two. This also means the Instagram fetch target preserves whether the original link was a `/p/` or `/reel/` post, which a bare `id`-only reconstruction couldn't have. Stage 4 now takes a `url` parameter instead of a bare `id`, validated via this reused `classify()`. Behavior and risk posture are unchanged — this is a "how," not a change to the Completion Contract.
  - Added `tests/MediaEmbedPreviewCacheStoreTest.php` and `tests/InstagramPagePreviewFetcherTest.php`; registered both in `tests/run.php`. Added `MediaEmbedDetectorTest::testClassifyValidatesAndExtractsFromAStandaloneUrl` to lock in the now-public `classify()` contract Stage 4 depends on.
- Verification:
  - `php tests/run.php MediaEmbedPreviewCacheStoreTest InstagramPagePreviewFetcherTest MediaEmbedDetectorTest` — 22 run, 22 passed. Covers: cache miss/hit/overwrite/no-collision; fetcher success-parse, both meta-tag attribute orders, entity decoding, transport failure, missing tags, partial tags; `classify()` validating/rejecting a standalone URL.
- Notes:
  - Hit a PHP 8.1 compatibility snag in the fetcher test (`false` as a standalone closure return type is PHP 8.2+); fixed by dropping the explicit return type on that one test closure.

## Stage 4 - Warm-cache endpoint

- Changes:
  - Added `ForumRewrite\Http\MediaEmbedPreviewController::warmPreview(string $method, array $query)`. Rejects non-GET (405) and anything where `provider !== 'instagram'` or `MediaEmbedDetector::classify($url)` doesn't return an `instagram` match — all before any cache read or fetch. On a cache hit (successful or a recent failed attempt within a 1-hour backoff), returns 204 without fetching. Otherwise calls `InstagramPagePreviewFetcher::fetch()` and writes the result (success or failure) to `MediaEmbedPreviewCacheStore`, then returns 204.
  - Wired into `Application.php`: new route `GET /internal/media-embeds/warm-preview`, dispatched early (alongside `/api/version`, before `ensureReadModel()` — this endpoint needs neither the read model nor a viewer session) via a new `mediaEmbedPreviewController()` lazy-getter, matching the existing `tagApiController()`/`identityHintController()` pattern.
  - Added `tests/MediaEmbedPreviewControllerTest.php`, constructing `RouteServices` directly (mirroring the existing direct-construction pattern already used in `tests/LocalAppSmokeTest.php` for `InstancePageController`), with an injected `InstagramPagePreviewFetcher` transport to spy on whether a fetch was attempted.
- Verification:
  - `php tests/run.php MediaEmbedPreviewControllerTest` — 5 run, 5 passed: cold cache triggers exactly one fetch and writes the cache; a non-Instagram provider and a URL `classify()` rejects both skip the fetch entirely; a warm cache short-circuits; a recent failure short-circuits but an attempt older than the 1-hour backoff retries.
  - `php tests/run.php` (full suite) — 890 run, 888 passed; same two pre-existing failures as every prior stage, no new ones.
- Notes:
  - The endpoint is deliberately unauthenticated (any viewer's browser is expected to hit it via a beacon) but bounded: the fetch target always comes from a URL `MediaEmbedDetector` itself validated, never an arbitrary one, and repeat hits on the same ID cost a cheap cache lookup, not a repeated fetch.
