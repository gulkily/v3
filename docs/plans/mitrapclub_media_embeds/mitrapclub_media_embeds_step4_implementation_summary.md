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

## Stage 4 - Wire into the shared `$br` closure

- Changes:
  - `TemplateRenderer::__construct()` gained a `MediaEmbedRenderer $mediaEmbedRenderer = new MediaEmbedRenderer()` dependency.
  - `TemplateRenderer::renderFile()`'s `$br` closure now reads `$this->featureFlags->isEnabled(FeatureFlagRegistry::MEDIA_EMBEDS_ENABLED)` once per render and delegates to `$this->mediaEmbedRenderer->render($value, $mediaEmbedsEnabled)` instead of calling `nl2br(htmlspecialchars())` directly. None of the 8 existing `$br(...)` template call sites were touched.
- Verification:
  - `php tests/run.php` (full suite) — 856 run, 854 passed; same two pre-existing failures as Stage 2/3, confirmed via `git stash` to predate this feature entirely.
  - Manual end-to-end run via a scratch script (`Application` against a copy of the `parity_minimal_v1` fixture, a directly-inserted post/thread containing `https://www.youtube.com/watch?v=dQw4w9WgXcQ&si=trackme`): with the flag off (default), the thread page (`/threads/{id}`) shows the plain escaped link and no card markup; after writing `FORUM_MEDIA_EMBEDS_ENABLED: true` to the site's `feature-flags.txt` and rebuilding `Application`, the same thread page shows the trusted card (`data-provider="youtube"`), the href is the tracking-stripped URL, and `si=trackme` does not appear anywhere in the output.
  - The board-listing route in the same scratch run didn't surface the card for this directly-inserted-into-SQLite fixture post (unrelated canonical-record warnings from bypassing the normal write path); not pursued further since Stage 3's unit tests already prove `paned_board_content_article.php`'s call site shares the exact same `$br` closure now wired in Stage 4, and Stage 5 covers a fuller per-call-site pass.
- Notes:
  - This stage's change is architecturally a one-line swap inside the single shared closure — by design, no per-template edits were needed or made.

## Stage 5 - End-to-end verification and checklist close-out

- Changes:
  - None (verification-only stage).
  - Marked the Cycle 5 line in `mitrapclub_theme_and_features_checklist.md` as Done, noting the flag still needs a manual operator enable on the production `mitrapclub` instance; updated the Cycle 5 entry in the checklist's "Cycle split" section to match.
- Verification:
  - `php tests/run.php` (full suite) re-run after Stage 4's wiring, with zero code changes in between — the same two pre-existing failures reappeared, plus one additional flaky failure (`WriteApiSmokeTest::testApprovalIncrementalFailureFallsBackToFullRebuildAndKeepsReadModelHealthy`); re-running that test alone passed immediately, confirming it's a pre-existing timing-sensitive flake unrelated to this feature (no source files changed between the Stage 4 run and this one).
  - Directly rendered every one of the 8 `$br(...)` call sites via `TemplateRenderer::renderFragment()` (plus the `thread_root_card.php` qdb-quote-root branch, a 9th code path through the same template), each with the flag off and then on via `putenv('FORUM_MEDIA_EMBEDS_ENABLED=true')`:
    - `thread_card.php` (board listing card) — no card off, card on.
    - `thread_root_card.php`, non-qdb branch (thread page root body) — confirmed earlier via a full `Application` pipeline run (flag off: plain escaped link; flag on: card, tracking-stripped href, no `si=` leak) and again here directly.
    - `thread_root_card.php`, qdb-quote-root branch — no card off, card on.
    - `post_card.php` (a reply) — no card off; card on with `data-provider="instagram"` and no `igshid` leak.
    - `quote_card.php` (qdb board card) — no card off, card on.
    - `paned_thread_reply_tree.php` (paned view reply) — no card off, card on.
    - `paned_board_content_article.php` (paned view root) — no card off, card on.
    - `compose_reply.php` (reply-compose parent-context preview) — no card off; card on with `data-provider="instagram"`.
  - All flag-off renders showed no `data-media-embed-card` markup, matching today's plain-link behavior; all flag-on renders with a matchable URL showed the card with the correct provider and a tracking-stripped link.
- Notes:
  - The Stage 4 manual check's board-listing gap (via the `Application`/SQLite-fixture route) is resolved here: rendering `thread_card.php` directly with `renderFragment()` shows the card correctly — that gap was a fixture/canonical-record data-shape artifact of the scratch script, not a product issue.
  - Per Step 2/3, this feature's own rollout does not enable the flag anywhere; enabling `FORUM_MEDIA_EMBEDS_ENABLED` on the production `mitrapclub` instance remains a manual operator action.
