> **Feature plan:** [Step 1](./mitrapclub_media_embeds_inline_player_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_inline_player_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_inline_player_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_inline_player_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** A `mitrapclub` member posts or replies to a thread containing a YouTube or Instagram URL, with both the Cycle 5 flag and this cycle's new flag enabled.
- **End-to-end outcome:** The YouTube card's collapsed expando plays the video inline once opened. The Instagram card shows a cached thumbnail/title once warmed; before that, it shows Cycle 5's plain card plus an invisible beacon that warms the cache in the background for the next viewer.
- **Required recovery:** Either flag off reproduces Cycle 5's behavior exactly. A fetch failure, cache miss, malformed/unrecognized ID, or blocked iframe all degrade to Cycle 5's plain card — never a broken page. Repeated hits against the new warm-cache endpoint for the same ID cost at most one bounded outbound fetch per backoff window, never an unbounded fan-out.
- **Deployment/external verification:** The new internal warm-cache route must be reachable the same way every other route is (no new auth/deploy step); verified manually in Stage 7.
- **Release condition:** Both flags ship defaulted off; full test suite green; Stage 7's manual verification (expand-to-play, beacon-triggered warm, abuse-bound spot check) complete.

## Key Risks

- **High risk:** First iframe anywhere in post rendering — a sandboxing mistake is a clickjacking/script-injection-adjacent surface. Early validation: a test asserting the exact, fixed `sandbox`/`referrerpolicy`/`loading` attribute set, before Step 4 ships. Mitigation: hardcoded attributes; `src` built only from the video ID the existing detector already isolates, never from raw user text.
- New unauthenticated endpoint triggers outbound server-side fetches (abuse / resource-exhaustion / SSRF-flavored surface). Early validation: Stage 4's endpoint design review before implementation. Mitigation: the endpoint's own fetch target is always `instagram.com/p/{id}/` or `/reel/{id}/`, built from the validated embed ID — it never accepts or fetches an attacker-supplied URL; the `id` parameter must match the exact shortcode shape the detector already validates; a cache-check (including a short failure-retry backoff) runs before any outbound call, so repeat hits on the same ID cost a cheap DB read, not a repeated fetch.
- Page-scraping fragility — parsing `og:title`/`og:image` meta tags from Instagram's public page (Meta's own suggested replacement, since oEmbed dropped these fields in late 2025) is inherently less stable than a documented API: markup can change without notice, and unauthenticated page fetches may be rate-limited or blocked more readily than API calls were. Early validation: Stage 3's fetcher test covers the "page fetched but tags missing/changed" parse-failure path explicitly, not just network failure. Mitigation: identical blast radius to any other fetch failure — a parse miss just leaves Cycle 5's plain card, nothing more.
- Cache staleness or unbounded growth. Early validation: Stage 3 defines the store as fully disposable. Mitigation: same posture as this app's existing `state/cache/*.sqlite3` stores — safe to delete/rebuild anytime.
- Two-flag dependency confusion for operators. Early validation: review the feature-flags admin page's dependency display for this pair before Stage 7 close-out. Mitigation: the new flag's `requiresEnabledFlag` points at `FORUM_MEDIA_EMBEDS_ENABLED`, the same mechanism already proven by `EMOJI_AUTHORED_TEXT`.

## Stage 1
- Goal: Register the new inline-player feature flag, dependent on Cycle 5's flag, defaulted off.
- Dependencies: None.
- Expected changes: Add `MEDIA_EMBEDS_INLINE_PLAYER_ENABLED` constant and `FeatureFlagDefinition` entry (`siteMutable: true`, default `false`, `requiresEnabledFlag: self::MEDIA_EMBEDS_ENABLED`) to `FeatureFlagRegistry::all()`, matching the existing `EMOJI_AUTHORED_TEXT`/`UNICODE_AUTHORED_TEXT` dependency pattern.
- Verification approach: Run `tests/FeatureFlagEvaluatorTest.php`/`tests/FeatureFlagsBehaviorTest.php`; confirm the new flag defaults `false` and is blocked when `MEDIA_EMBEDS_ENABLED` is off, mirroring the existing emoji/unicode dependency test.
- Risks or open questions: None expected — same mechanism as an existing dependent flag pair.
- Canonical components/API contracts touched: `FeatureFlagRegistry`.

## Stage 2
- Goal: Expose the raw embed identifier (YouTube video ID / Instagram shortcode) per detected match, for building the iframe `src` and the cache key.
- Dependencies: None.
- Expected changes: `ForumRewrite\View\MediaEmbedDetector::detect()`'s return shape gains an `embedId` field per match (e.g. `list<array{provider: string, url: string, displayUrl: string, embedId: string, offset: int, length: int}>`); `classify()` captures and returns the ID alongside the provider instead of just the provider name.
- Verification approach: Extend `tests/MediaEmbedDetectorTest.php` with assertions on `embedId` for each known YouTube/Instagram shape already covered.
- Risks or open questions: None — purely additive to an already-tested, pure function.
- Canonical components/API contracts touched: `MediaEmbedDetector` (extended, not forked).

## Stage 3
- Goal: Add the disposable preview-metadata cache store and the Instagram-only page-meta-tag fetcher, independently testable, not yet wired into rendering.
- Dependencies: None.
- Expected changes: New `MediaEmbedPreviewDatabaseConfig::path(string $projectRoot): string` returning `state/cache/media_embed_previews.sqlite3`, following the existing `LlmExchangeDatabaseConfig`/`FastScoreDatabaseConfig` pattern. New `MediaEmbedPreviewCacheStore` with `get(string $provider, string $embedId): ?array{title: string, thumbnailUrl: string, fetchedAt: string}` and `put(string $provider, string $embedId, ?string $title, ?string $thumbnailUrl): void` (lazy `CREATE TABLE IF NOT EXISTS`); a failed fetch is still written with `fetchedAt` and null title/thumbnail, so a short backoff window can be checked before retrying. New `InstagramPagePreviewFetcher::fetch(string $url): ?array{title: string, thumbnailUrl: string}`, using the same minimal `file_get_contents`/`stream_context_create` pattern already used by `AnthropicStructuredChatProvider`, then parses only the `og:title`/`og:image` `<meta>` tags out of the response body; any failure (timeout, non-200, missing/unparseable tags) returns `null`. `MediaEmbedDetector::classify()` (Stage 2) is made `public` rather than adding a second shortcode-shape validator, so Stage 4's endpoint can validate a full URL and derive its `embedId` through the exact same rule the detector already applies — this also preserves whether the link was a `/p/` or `/reel/` post, which a bare-ID reconstruction couldn't.
- Verification approach: New unit tests for the cache store (get/put round-trip against a temp SQLite file; missing key returns `null`; a recent failed attempt is distinguishable from "never attempted" for the backoff check) and for the fetcher (inject a fake transport returning canned HTML to test the tag-parse-success and missing-tags/failure-returns-null paths, without a real network call).
- Risks or open questions:
  - Impact: cache staleness/growth and page-scraping fragility (see Key Risks).
  - Early warning: store design reviewed as disposable before Stage 7; fetcher test covers the missing-tags case explicitly.
  - Mitigation: same posture as existing `state/cache/*.sqlite3` stores; any parse failure degrades like any other fetch failure.
- Canonical components/API contracts touched: New `MediaEmbedPreviewDatabaseConfig`, `MediaEmbedPreviewCacheStore`, `InstagramPagePreviewFetcher` — net-new, following existing per-concern SQLite-store and inline-fetch conventions rather than introducing new ones.

## Stage 4
- Goal: Add the internal, abuse-bounded endpoint that warms the cache on a beacon hit, wired into routing.
- Dependencies: Stage 3.
- Expected changes: A new route (e.g. `GET /internal/media-embeds/warm-preview`) accepting `provider` and `url`. Handler rejects anything where `provider !== 'instagram'` or `MediaEmbedDetector::classify($url)` doesn't return an `instagram` match; the `embedId` for the cache key comes from that same `classify()` call. Calls `MediaEmbedPreviewCacheStore::get()` first and returns immediately (204) on a cache hit or a recent failed attempt within the backoff window; only on a genuine miss does it call `InstagramPagePreviewFetcher::fetch()` and `MediaEmbedPreviewCacheStore::put()`, then returns 204.
- Verification approach: New test(s) exercising the route: a valid Instagram URL + cold cache triggers a fetch (fake transport) and a cache write; an invalid provider or a URL `classify()` rejects is rejected before any fetch; a warm cache or a recent failure short-circuits without a fetch.
- Risks or open questions:
  - Impact: abuse/resource-exhaustion (see Key Risks).
  - Early warning: the rejection/short-circuit tests above.
  - Mitigation: fixed fetch target, strict ID-shape validation, cache-check-first with backoff.
- Canonical components/API contracts touched: New route + handler; reuses Stage 3's store/fetcher rather than duplicating fetch logic.

## Stage 5
- Goal: Extend the card renderer to add the YouTube collapsed expando/iframe and the Instagram cached-thumbnail-or-beacon card.
- Dependencies: Stage 2, Stage 3.
- Expected changes: `MediaEmbedRenderer::render()` gains an `$inlinePlayerEnabled` parameter. When true and a match is YouTube, the card becomes a `<details>/<summary>` wrapping an iframe whose `src` attribute is only emitted once expanded (client-side, no server round-trip needed to open it), hardcoded `sandbox`/`referrerpolicy`/`loading="lazy"` attributes, `src` built from `embedId` only. When true and a match is Instagram, the renderer reads `MediaEmbedPreviewCacheStore::get()` (read-only, never fetches here): a hit renders the thumbnail/title card; a miss renders today's Cycle 5 card plus a hidden beacon (`<img>`-style, no JS required) pointing at Stage 4's endpoint with the match's `embedId`. When `$inlinePlayerEnabled` is false, output is byte-identical to Cycle 5's renderer — unchanged.
- Verification approach: Extend `tests/MediaEmbedRendererTest.php` — inline-player flag off is byte-identical to Cycle 5's current output; YouTube match with flag on produces the expando/iframe markup with the exact hardcoded attributes; Instagram match with a warm cache entry (test seeds the store directly) renders the thumbnail card; Instagram match with a cold cache renders Cycle 5's card plus the beacon tag, with no attempt to fetch during render.
- Risks or open questions:
  - Impact: iframe sandboxing mistake (see Key Risks, high risk).
  - Early warning: the exact-attribute-set assertion.
  - Mitigation: attributes hardcoded, not computed from any input.
- Canonical components/API contracts touched: `MediaEmbedRenderer` (extended in place); reads Stage 3's cache store read-only.

## Stage 6
- Goal: Wire the new flag into the shared `$br` closure alongside Cycle 5's flag.
- Dependencies: Stage 1, Stage 5.
- Expected changes: `TemplateRenderer::renderFile()`'s `$br` closure reads both `FeatureFlagRegistry::MEDIA_EMBEDS_ENABLED` and `FeatureFlagRegistry::MEDIA_EMBEDS_INLINE_PLAYER_ENABLED` and passes both into `MediaEmbedRenderer::render()`. No template call sites change, same as Cycle 5's Stage 4.
- Verification approach: Run the full test suite; manually verify via `TemplateRenderer::renderFragment()` (the technique used in Cycle 5's Stage 5) across the YouTube and Instagram cases with the new flag on and off.
- Risks or open questions: None beyond what Stages 1-5 already cover — this stage is a two-line wiring change.
- Canonical components/API contracts touched: `TemplateRenderer::renderFile()`'s existing `$br` closure (extended in place, same closure Cycle 5 already modified).

## Stage 7
- Goal: End-to-end verification across all render sites and both flag combinations, abuse-bound spot check, then close out the checklist.
- Dependencies: Stage 6.
- Expected changes: Verification only; update the Cycle 6 entry in `mitrapclub_theme_and_features_checklist.md` once verified.
- Verification approach: Full test suite. Manual check (direct `renderFragment()` calls, as in Cycle 5) across YouTube expand-to-play and Instagram warm/cold cache states on at least `thread_card.php`, `thread_root_card.php`, and `post_card.php`. Manual hit of the warm-cache endpoint with a valid Instagram URL (cold, then warm — confirm the second hit doesn't re-fetch) and with an invalid provider/URL (confirm immediate rejection, no fetch attempted).
- Risks or open questions:
  - Impact: a gap between unit-level coverage and real request-path wiring (route registration, flag dependency) going unnoticed.
  - Early warning: this stage's manual pass specifically exercises the route and both flags together, not just the renderer in isolation.
  - Mitigation: none needed beyond running the checks.
- Canonical components/API contracts touched: None (verification stage).
