> **Feature plan:** [Step 1](./mitrapclub_media_embeds_step1_solution_assessment.md) · [Step 2](./mitrapclub_media_embeds_step2_feature_description.md) · [Step 3](./mitrapclub_media_embeds_step3_development_plan.md) · [Step 4](./mitrapclub_media_embeds_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** A `mitrapclub` member posts or replies to a thread whose body contains a YouTube or Instagram URL, on a site where an operator has manually enabled the new feature flag.
- **End-to-end outcome:** That post renders, on both the board listing and the thread page, with a locally-rendered media card (provider label + the URL) in place of the bare link.
- **Required recovery:** An unsupported-provider URL, a malformed/non-http(s) URL, or the flag being off, all render exactly as today's plain-link/plain-text behavior — no raw third-party HTML ever reaches the page.
- **Deployment/external verification:** No deploy-time step beyond the flag itself; this feature's rollout does not enable the flag anywhere (per Step 2, enabling is a manual, later operator action).
- **Release condition:** Flag ships defaulted off on every site; full test suite green; Stage 5 manual verification complete.

## Key Risks

- **High risk:** Flag-off regression — every existing post body on every site already routes through the single shared `$br` closure, so a mistake here changes rendering everywhere, not just `mitrapclub`. Early validation: Stage 3's byte-identical-output unit test. Mitigation: implement the flag-off path as an exact pass-through of today's `nl2br(htmlspecialchars())` logic, not a reimplementation.
- Pattern-match correctness — too loose or too tight a URL match misses real links or false-positives on unrelated text. Early validation: Stage 2's unit test table of known matches/non-matches. Mitigation: match only documented YouTube/Instagram URL path shapes; prefer a false negative (plain link) over a false positive.
- Enrichment scope creep — temptation to add thumbnail/title fetching mid-implementation, introducing a network dependency Step 2 explicitly excluded from this slice. Early validation: this plan's stages ship bare text-cards only, no fetch. Mitigation: treat any enrichment as unscheduled, future Step 1/2 work.
- Tracking-param stripping removes a parameter that actually affects content (e.g. a timestamp or playlist reference). Early validation: Stage 2's unit test table includes known-content params alongside known-tracking params. Mitigation: strip only a fixed, explicit per-provider allowlist of tracking param names; everything else passes through untouched.

## Stage 1
- Goal: Register the new site-mutable feature flag, defaulted off everywhere.
- Dependencies: None.
- Expected changes: Add a `MEDIA_EMBEDS_ENABLED` constant and a `FeatureFlagDefinition` entry to `FeatureFlagRegistry::all()` (`siteMutable: true`, default `false`), matching the existing `THREAD_DENSITY_TOGGLE_ENABLED` pattern.
- Verification approach: Run `tests/FeatureFlagEvaluatorTest.php` and `tests/FeatureFlagsBehaviorTest.php`; confirm the new flag defaults to `false` and is returned by `get()`/`all()`.
- Risks or open questions: None expected — same mechanism as existing flags.
- Canonical components/API contracts touched: `FeatureFlagRegistry`.

## Stage 2
- Goal: Detect YouTube/Instagram URLs inside raw post body text, as an isolated, pure, unit-testable step, and produce a tracking-param-stripped display form of each matched URL.
- Dependencies: None (logically precedes Stage 3, no code dependency on Stage 1).
- Expected changes: New class `ForumRewrite\View\MediaEmbedDetector` with a method shaped like `detect(string $body): list<array{provider: string, url: string, displayUrl: string, offset: int, length: int}>`, matching only documented http(s) YouTube (`youtube.com/watch`, `youtu.be/...`) and Instagram (`instagram.com/p/...`, `instagram.com/reel/...`) URL shapes. `displayUrl` is `url` with a fixed, explicit per-provider allowlist of tracking query params removed (YouTube: `si`; Instagram: `igshid`, `igsh`; both: `utm_*`, `fbclid`) — every other query param, including YouTube's `t`/`list`, passes through untouched.
- Verification approach: New `tests/MediaEmbedDetectorTest.php` with a table of known-matching URLs (several shapes per provider) and known non-matches (bare domain, other providers, `javascript:` scheme, a truncated/cut-off URL), plus a table asserting `displayUrl` strips each known tracking param and preserves each known content param.
- Risks or open questions:
  - Impact: regex too loose/tight (see Key Risks); tracking-strip removes a content-affecting param (see Key Risks).
  - Early warning: the match/non-match test table; the strip/preserve param table.
  - Mitigation: restrict to documented path shapes only; strip only the fixed tracking-param allowlist.
- Canonical components/API contracts touched: New `MediaEmbedDetector` — net-new, no existing contract reused.

## Stage 3
- Goal: Produce body HTML that is byte-identical to today's output when disabled or unmatched, and swaps in a small trusted card only where the detector matches.
- Dependencies: Stage 2.
- Expected changes: New method shaped like `ForumRewrite\View\MediaEmbedRenderer::render(string $body, bool $enabled): string`. Flag-off or no-match path calls the same escape/`nl2br` logic used today, unchanged. Match path escapes surrounding text normally and emits a server-authored card fragment (provider label + escaped `displayUrl`, the tracking-stripped form from Stage 2) per match, in the same "plain HTML string, not a template partial" shape as `event_block.php`'s markup.
- Verification approach: New `tests/MediaEmbedRendererTest.php` — flag-off output asserted byte-identical to today's `nl2br(htmlspecialchars())` on a fixed sample set; flag-on output asserted to contain card markup for matches and untouched escaped text elsewhere; confirm no double-escaping or raw-tag leakage.
- Risks or open questions:
  - Impact: flag-off divergence from current output (see Key Risks, high risk).
  - Early warning: the byte-identical assertion.
  - Mitigation: pass-through implementation, not a reimplementation.
- Canonical components/API contracts touched: New `MediaEmbedRenderer`; reuses existing escape/`nl2br` logic rather than duplicating it.

## Stage 4
- Goal: Make every existing body-rendering call site benefit with zero per-template edits.
- Dependencies: Stage 1, Stage 3.
- Expected changes: In `TemplateRenderer::renderFile()`, change the `$br` closure body to delegate to `MediaEmbedRenderer::render($value, $this->featureFlags->isEnabled(FeatureFlagRegistry::MEDIA_EMBEDS_ENABLED))` instead of calling `nl2br(htmlspecialchars())` directly. No changes to any of the 8 existing `$br(...)` call sites (`post_card.php`, `quote_card.php`, `thread_root_card.php` ×2, `thread_card.php`, `paned_thread_reply_tree.php`, `compose_reply.php`, `paned_board_content_article.php`).
- Verification approach: Run the app locally; flag off — board/thread pages render byte-identical to current production. Flip the flag on locally, post a YouTube/Instagram link, confirm the card renders on the board listing and the thread page.
- Risks or open questions:
  - Impact: the flag is global per request, not a per-post toggle, so an unexpected enable affects every body render at once.
  - Early warning: already covered by the flag's existing `siteMutable` admin safeguards — nothing new needed.
  - Mitigation: rely on existing flag-admin review before any production enable.
- Canonical components/API contracts touched: `TemplateRenderer::renderFile()`'s existing `$br` closure (extended in place).

## Stage 5
- Goal: Confirm the full vertical slice end-to-end and zero regression elsewhere, then close out the checklist entry.
- Dependencies: Stage 4.
- Expected changes: Verification only; update the Cycle 5 line in `mitrapclub_theme_and_features_checklist.md` to "Done" once verified.
- Verification approach: Run the full test suite. Manually browse `mitrapclub` locally with the flag on across all 8 render sites (board, thread page, a reply, a quoted reply, the compose-reply parent-context preview, the paned layout view) to confirm cards render where expected. Confirm with the flag off (default) that nothing changes.
- Risks or open questions:
  - Impact: `paned_board_content_article.php`'s truncated `body_preview` could contain a cut-off URL.
  - Early warning: manual check of that specific call site during this stage.
  - Mitigation: Stage 2's detector requires a complete URL shape, so a truncated preview should simply fall back to plain text — verify this holds in practice.
- Canonical components/API contracts touched: None (verification stage).
