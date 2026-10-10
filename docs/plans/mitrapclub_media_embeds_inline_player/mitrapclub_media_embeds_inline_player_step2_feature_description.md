> **Feature plan:** [Step 1](./mitrapclub_media_embeds_inline_player_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_inline_player_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_inline_player_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_inline_player_step4_implementation_summary.md)

## Problem

Cycle 5's link-preview card confirms a post contains a YouTube or Instagram link but never plays or previews it richly, so a viewer must always leave the thread to watch the club's video content, and Instagram's card looks unfinished next to a richer YouTube experience.

## User Stories

- As a `mitrapclub` member, I want a YouTube link I post to play inline, behind a collapsed expando, so that viewers can watch without leaving the thread.
- As a `mitrapclub` member, I want an Instagram link I post to still show a recognizable thumbnail and title, even though it can't play inline, so it doesn't look unfinished next to a YouTube card.
- As a viewer, I want the YouTube embed to stay collapsed by default so that nothing extra loads, and no video starts, until I choose to open it.
- As a site operator, I want this kept off until I separately opt in, so existing Cycle 5 behavior (and sites with the Cycle 5 flag off) is never affected by this cycle's new network/iframe surface.

## Core Requirements

- A YouTube match renders with a collapsed `<details>/<summary>` expando (matching this codebase's existing disclosure pattern); the iframe's `src` (`youtube.com/embed/{video_id}`) is only set once a viewer expands it — nothing loads or plays until then.
- An Instagram match renders with a cached title + thumbnail preview — fetched by retrieving the Instagram post's public page and parsing only its `og:title`/`og:image` meta tags, never oEmbed's `html` embed markup or any script — in place of today's bare label-and-link card. (Amended: Instagram's oEmbed endpoint dropped `thumbnail_url`/`author_name` in late 2025, so a thumbnail/title is no longer obtainable that way; Meta's own guidance for this case is exactly the page-meta-tag approach used here.)
- Fetched preview metadata (title, thumbnail URL, fetched-at) lives in a new, dedicated, disposable SQLite cache file (e.g. `state/cache/media_embed_previews.sqlite3`) — no changes to the `posts`/`threads` schema or the main read-model.
- A failed/slow/missing thumbnail fetch, a malformed or unrecognized video ID, or a blocked/failed iframe all fall back gracefully to Cycle 5's plain card — never a broken page or a blocked post.
- Gated behind a new feature flag that `requiresEnabledFlag` the existing `FORUM_MEDIA_EMBEDS_ENABLED` (same dependency mechanism already used by `EMOJI_AUTHORED_TEXT`/`UNICODE_AUTHORED_TEXT`), defaulted off; enabling it is a separate, later manual operator action, same posture as Cycle 5.

## Delivery Scope

- **Work type:** Application change.

## Completion Boundary

- **Normal entry:** A `mitrapclub` member posts or replies to a thread containing a YouTube or Instagram URL, with both the Cycle 5 flag and this cycle's new flag enabled.
- **End-to-end outcome:** The YouTube post's card shows a collapsed expando that plays the video inline once opened; the Instagram post's card shows a cached thumbnail and title.
- **Needed recovery:** The collapsed state is always safe — nothing loads until expanded. Any fetch failure, cache miss, unrecognized ID, or blocked iframe leaves today's plain card, never a broken page. Either flag off reproduces Cycle 5's behavior exactly.
- **Release condition:** Both flags ship defaulted off; full test suite green; manual verification of the expand-to-play flow and the Instagram fallback path complete before close-out.

## Risks

- **First iframe anywhere in post rendering** — a sandboxing mistake could open a clickjacking/script-injection-adjacent surface. Earliest validation: a test asserting the exact, fixed `sandbox`/`referrerpolicy`/`loading` attribute set before Step 4 ships. Mitigation: hardcoded attributes; `src` built only from the video ID already isolated by the existing strict detector, never from raw user text.
- **First outbound network call in a post-render-adjacent path** (the Instagram page fetch) — latency, rate limits, or provider unavailability. Earliest validation: Step 3 must specify the fetch as async/best-effort/cached, triggered by a client-side beacon, never synchronous inside a page render. Mitigation: render always reads the cache only; fetching, parsing, and caching happens on a separate, non-blocking path, triggered out-of-band.
- **Page-scraping fragility** — parsing a public page's meta tags instead of calling a documented API is inherently more brittle: Instagram's markup can change without notice, and unauthenticated page fetches may be rate-limited or blocked more readily than API calls. Earliest validation: Step 3/4 confirm a parse failure or non-200 response degrades exactly like any other fetch failure. Mitigation: treat it as equally best-effort as an API call would have been — any failure just leaves Cycle 5's plain card, same blast radius either way.
- **Cache staleness or unbounded growth** — fetched thumbnails/titles can go stale, and the cache file could grow without bound. Earliest validation: Step 3 defines a refresh/eviction policy. Mitigation: treat the cache as fully disposable and rebuildable, consistent with this app's existing `state/cache/*.sqlite3` stores (e.g. `post_index.sqlite3`).
- **Two-flag confusion** — operators may not realize this cycle's flag depends on Cycle 5's flag. Earliest validation: review the feature-flags admin page's dependency display for this pair before Step 4 close-out. Mitigation: wire the new flag's `requiresEnabledFlag` to `FORUM_MEDIA_EMBEDS_ENABLED`, the same mechanism already shown working for `EMOJI_AUTHORED_TEXT`.

## Shared Component Inventory

- **Detection:** `ForumRewrite\View\MediaEmbedDetector` (Cycle 5) is extended, not forked — it needs to also expose the raw YouTube video ID and Instagram shortcode it already implicitly matches, for building the embed `src` and the cache key.
- **Rendering:** `ForumRewrite\View\MediaEmbedRenderer` (Cycle 5) is extended to emit the collapsed expando (YouTube) and the richer thumbnail markup (Instagram), rather than introducing a second renderer.
- **Feature gating:** `FeatureFlagRegistry`'s existing `siteMutable` + `requiresEnabledFlag` mechanism (already proven by `EMOJI_AUTHORED_TEXT` depending on `UNICODE_AUTHORED_TEXT`) is reused for this cycle's new flag depending on `FORUM_MEDIA_EMBEDS_ENABLED`.
- **Cache storage:** reuses this app's existing per-concern SQLite cache file pattern (`state/cache/post_index.sqlite3`, `state/private/fast_scores.sqlite3`, `state/private/internal_tasks.sqlite3`) for the new preview-metadata store, rather than a new persistence mechanism or a schema change to canonical data.
- **Disclosure UI:** reuses the existing `<details>/<summary>` pattern already shipping in `thread_root_card.php` (`post-analysis`, `codex-handoff-preview`) for the collapsed YouTube expando.
- **Outbound fetch:** no shared HTTP client utility exists to reuse — `AnthropicStructuredChatProvider`/`OpenAiCompatibleStructuredChatProvider` each do their own minimal inline `file_get_contents` + `stream_context_create` fetch; the Instagram page fetch follows that same minimal, inline pattern rather than introducing a new abstraction.
- **Trigger path:** the fetch is kicked off by a client-side beacon (e.g. a hidden `<img>`-style tag) hitting a new internal endpoint, not by anything running in the browser reading Instagram's response directly — Instagram's page doesn't send CORS headers permitting a cross-origin `fetch()`/`XHR` to read its content from our site's JS, so the parse itself must happen server-side regardless.

## Simple User Flow

1. Member posts or replies with a YouTube or Instagram URL; both flags are enabled on the site.
2. Post saves exactly as today — no new validation, no synchronous fetch blocking submission.
3. A YouTube match renders as a card with a collapsed "Watch inline" expando; opening it sets the iframe `src` and plays the video.
4. An Instagram match renders as a card with a cached thumbnail and title once a client-triggered background fetch has parsed them from the post's public page; before that, or on any fetch/parse failure, it shows today's plain card.
5. With either flag off, both providers render exactly as Cycle 5 shipped.

## Success Criteria

- Expanding a YouTube card's disclosure plays the video inline, with nothing loaded or played before that click.
- An Instagram card shows a cached thumbnail and title once fetched, and never blocks or breaks a post when the fetch fails or hasn't happened yet.
- A malformed/unrecognized ID, a blocked iframe, or a fetch failure all degrade to Cycle 5's plain card — never a broken page.
- With either flag off, rendering is unchanged from Cycle 5 for every site.
