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
