> **Feature plan:** [Step 1](./mitrapclub_media_embeds_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_step4_implementation_summary.md)

## Stage 1 - Feature flag

- Changes:
  - Added `FeatureFlagRegistry::MEDIA_EMBEDS_ENABLED` (`FORUM_MEDIA_EMBEDS_ENABLED`) and its `FeatureFlagDefinition` entry (`siteMutable: true`, default `false`), positioned after `THREAD_DENSITY_TOGGLE_ENABLED`.
  - Updated `tests/FeatureFlagEvaluatorTest.php`'s hardcoded public-flag canary list and env-cleanup key list to include the new flag (both previously enumerated every public flag by name).
- Verification:
  - `php tests/run.php FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest` — 16 run, 16 passed.
- Notes:
  - No changes needed to `testDefaultsMatchExistingSiteFlags` — that test only individually asserts a subset of flags (e.g. `THREAD_DENSITY_TOGGLE_ENABLED` is likewise absent from it), consistent with existing precedent.

## Stage 2 - URL detector

- Changes:
  - Added `ForumRewrite\View\MediaEmbedDetector::detect(string $body)`, matching documented YouTube (`youtube.com/watch?...v=`, `youtu.be/...`) and Instagram (`instagram.com/p/...`, `instagram.com/reel/...`) http(s) URL shapes only; returns `provider`, `url`, `displayUrl`, `offset`, `length` per match.
  - `displayUrl` strips a fixed per-provider tracking-param allowlist (`si`, `igshid`, `igsh`, `fbclid`, any `utm_*`) via `parse_url`/`parse_str`/`http_build_query`; every other query param (e.g. YouTube's `t`, `list`) passes through untouched.
  - Trailing sentence punctuation (`.,;:!?)'"]`) is trimmed off a matched URL so prose like "...clip. Cool right?" doesn't pull the period into the match.
  - Added `tests/MediaEmbedDetectorTest.php` and registered it in `tests/run.php`.
- Verification:
  - `php tests/run.php MediaEmbedDetectorTest` — 5 run, 5 passed: known YouTube/Instagram shapes match; bare domain, other-provider, `javascript:` scheme, and truncated URLs do not; trailing punctuation excluded from the match span; tracking params stripped while content params (`t`, `list`) are preserved.
  - `php tests/run.php` (full suite) — no new failures from this stage. Two failures are present (`LocalAppSmokeTest::testFeatureFlagsPageShowsLockedBadgeWithReasonForNonMutableFlags`, `WriteApiSmokeTest::testTaskQueueProcessesQueuedAgentReplyOnce`); confirmed via `git stash` against the Stage 1 commit that both already fail without any of this feature's changes — pre-existing, out of this feature's scope.
- Notes:
  - Detection is a pure string operation — no network calls, consistent with Step 2's scope.

## Stage 3 - Card-aware body renderer

- Changes:
  - Added `ForumRewrite\View\MediaEmbedRenderer::render(string $body, bool $enabled): string`. Disabled or no-match input is byte-identical to today's `nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))`. On a match, surrounding text is escaped/broken exactly as before and a trusted `<span class="media-embed-card" data-media-embed-card data-provider="...">` fragment (provider icon/label + an `<a>` to the tracking-stripped `displayUrl`) replaces the matched span — a plain HTML string, not a template partial, mirroring `event_block.php`'s shape.
  - Added `tests/MediaEmbedRendererTest.php` and registered it in `tests/run.php`.
- Verification:
  - `php tests/run.php MediaEmbedRendererTest` — 5 run, 5 passed: flag-off and flag-on-no-match both byte-identical to today's escape/`nl2br` output; a match escapes surrounding text (confirmed no raw `<script>` leak) and emits the card with the tracking-stripped link; multiple matches each get their own card with text between preserved.
- Notes:
  - No new failures introduced; the same two pre-existing failures noted in Stage 2 remain, unrelated to this stage.
