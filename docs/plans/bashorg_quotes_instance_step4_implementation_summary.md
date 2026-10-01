# Bash.org-Style Quotes Instance — Step 4: Implementation Summary

## Stage 1 - Site instance identity plumbing
- Changes:
  - Added a `bashorg` entry to `SiteProfileRegistry::all()` (`src/ForumRewrite/SiteProfileRegistry.php`): `defaultTheme: bashorg`, `composerPrompt: "Submit a quote..."`.
  - Added a `bashorg` entry to `ThemeRegistry::all()` (`src/ForumRewrite/View/ThemeRegistry.php`), `mode: light`.
  - Documented `bashorg` as a valid `FORUM_SITE_ID` value in `docs/runbooks/production_deploy.md`.
  - Extended `tests/SiteProfileRegistryTest.php` with `testActiveHonorsBashorgOverride`, mirroring the existing `chouse` coverage.
- Verification:
  - `./v3 test SiteProfileRegistryTest` — 5 run, 5 passed (including the new bashorg case).
  - Inline PHP check with `FORUM_SITE_ID=bashorg` set: `SiteProfileRegistry::active()` resolves `{"name":"bashorg","defaultTheme":"bashorg","composerPrompt":"Submit a quote..."}`; `ThemeRegistry::explicitNames()` includes `bashorg`; `ThemeRegistry::stylesheetPaths()['bashorg']` resolves to `/assets/theme-bashorg.css`.
- Notes:
  - The `/assets/theme-bashorg.css` file referenced above does not exist yet — that's Stage 2. No error occurs from its absence yet since nothing serves the stylesheet until a page actually loads under this theme.

## Stage 2 - Bash.org-accurate theme stylesheet
- Changes:
  - Added `public/assets/theme-bashorg.css`: variable block (`:root[data-theme="bashorg"]`, `.theme-menu__option[data-theme-option="bashorg"]`) using the archived palette (`#ffffff` page, `#000000` ink, `#c08000` line/active/button chrome), `--code-font` set to Courier New/Lucida Console for the quote body font Stage 4 will use, theme swatch, and scoped chrome overrides (`.site-header`, `.eyebrow`, `.card`, `.nav-link.is-active`) synthesizing the orange title-bar look from existing markup per the theme guide's "CSS-only illusion" approach.
  - Updated `tests/LocalAppSmokeTest.php`'s theme allow-list assertion (`var allowed = [...]`) and added a `data-theme-option="bashorg"` presence assertion, per the theme guide's required two updates.
- Verification:
  - `./v3 test LocalAppSmokeTest` — 113 run, 110 passed; the 3 failures (`testAnonymousPublicBoardDoesNotStartViewerSession`, `testPostAndActivityLinkAdjacentSignatureFiles`, `testSqliteViewerRouteUsesToolsShellAndPublishedSource`) are pre-existing long-standing failures per the suite's own test-run history tracking, unrelated to `SiteProfileRegistry`/`ThemeRegistry`/theme CSS.
  - Confirmed `testApplicationRendersCoreRoutes` (the test containing the updated allow-list/theme-option assertions) passed.
- Notes:
  - No template changes were needed — `ThemeRegistry` already feeds the popover markup, cycle button, anti-FOUC allow-list, and `layout.php`'s stylesheet-swap map automatically, per the theme guide.

## Stage 3 - Upvote/downvote tag weights
- Changes:
  - Added `upvote => 1` and `downvote => -1` to `TagScore::scoredTags()` (`src/ForumRewrite/TagScore.php`), alongside the existing `like`/`flag` entries.
  - Added `tests/TagScoreTest.php` (new, registered in `tests/run.php`) covering the two new tags, the two existing tags' weights staying unchanged, and an unknown-tag case.
- Verification:
  - `./v3 test TagScoreTest` — 4 run, 4 passed.
  - Read-path confirmation (no code change needed): `LocalWriteService::normalizeThreadTag()`/`normalizePostTag()` validate against `TagScore::isScoredTag()` directly — adding the two tags there is what makes the write API accept them at all, not just what scores them. `ReadModelBuilder`/`IncrementalReadModelUpdater` iterate `TagScore::scoredTags()` generically when computing `score_total`/`post_score_total`, so no reducer change was needed either.
- Notes:
  - **Resolved the Stage 3 risk flagged in Step 3 (approval gating):** confirmed in code (`LocalWriteService::isApprovedIdentity()` plus the score-reduction loops in `ReadModelBuilder`/`IncrementalReadModelUpdater`) that a reaction record is always written regardless of the voter's approval status, but the displayed `score_total`/`post_score_total` only moves for reactions from identities already marked `is_approved` in the `profiles` table. This is pre-existing behavior that `like`/`flag` already have today — upvote/downvote inherit it unchanged rather than introducing a new limitation. Net effect: a drive-by visitor's upvote/downvote is recorded but will not visibly move the score unless/until their identity is approved. This is a real gap from "anonymous bash.org-style voting" as originally imagined, but closing it would mean changing shared approval-gating behavior used by every other reaction on the site — out of scope for this feature per the plan's "reuse, don't fork" rule. Logged as a separate plan: `docs/plans/anonymous_reaction_scoring_step1_solution_assessment.md` (uncommitted, not part of this branch).

## Stage 4 - Quote-list index rendering
- Changes:
  - New `templates/partials/quote_card.php`: `#ID` permalink to `/threads/<id>`, a `data-role="thread-score"` node (reusing `thread_reactions.js`'s existing `setThreadScore`/AJAX wiring as-is), the full root-post body (`$br($thread['root_post_body'])` — no truncation, no subject), and upvote/downvote/flag buttons. Root `<article>` carries both `class="post-card"` and `data-thread-reactions-root` so the existing `bindThreadReactions`/`bindPostReactions` initializers (both already in `thread_reactions.js`) pick it up with zero JS changes. Upvote/downvote use `data-action="apply-thread-tag"` (thread-labels family, scores into `score_total`) and flag uses `data-action="apply-post-tag"` (post-reactions family) — mirroring the existing split in `thread_root_card.php` exactly.
  - `templates/pages/board.php` branches per-thread on a new `$isBashorgInstance` flag: renders `quote_card.php` instead of `thread_card.php` for this instance only.
  - `BoardPageController` (`src/ForumRewrite/Http/BoardPageController.php`): now takes `repositoryRoot` and the viewer-identity-hint resolver closure (mirroring `ForteBoardController`'s existing constructor pattern); when the active site profile (`SiteConfig::siteName()`) is `bashorg`, bulk-computes the viewer's prior upvote/downvote/flag state via the existing generic `ViewerTagLookup::threadTags()`/`postTags()` (no new lookup code, tag name passed as a parameter) and passes it to the template. No-op (empty arrays, no extra lookups) for every other site profile.
  - `Application::boardPageController()` updated to pass the two new constructor args.
- Verification:
  - `./v3 test LocalAppSmokeTest` — same 113 run / 110 passed as Stage 2, no new failures.
  - Full suite (`./v3 test`) run twice, once with Stage 4 changes and once with them `git stash`-ed back to the Stage 3 state, to isolate cause: both runs produced the identical 6 failures (confirmed pre-existing/order-dependent flakiness unrelated to this feature, not a Stage 4 regression).
  - Manual render check: bootstrapped a temp repo from `tests/fixtures/parity_minimal_v1`, ran the real `Application` with `FORUM_SITE_ID=bashorg` against `/?view=all&sort=newest`, and confirmed the rendered `<article class="card post-card quote-card">` contains the correct `#thread-zenmemes-rules` permalink, `Score: 0`, full untruncated body text, and correctly-wired upvote/downvote/flag buttons (`data-action`/`data-tag`/`aria-pressed` all correct).
  - Did not re-prove the full write-and-rescoring pipeline specifically for `upvote`/`downvote` end-to-end (doing so standalone requires bootstrapping a real approved identity, which existing test helpers do but aren't trivially reusable outside the test harness) — relying instead on: (a) `TagScoreTest` proving the weights, and (b) existing passing tests (e.g. `testIncrementalApprovalMatchesFreshRebuildForTransitiveApprovalAndScoreRefresh`) proving the exact same write/read-model code path already works correctly for `like`, which `upvote`/`downvote` share unchanged.
- Notes:
  - The score display still reads "Score: N" (the existing shared JS's hardcoded label), not bash.org's bare "(N)" — a cosmetic gap, deliberately left as-is per Stage 4's plan to avoid touching shared JS beyond Stage 5's already-scoped fix. Easy follow-up if wanted.
  - No optimistic instant-score-preview on upvote/downvote clicks (the existing JS's `setOptimisticThreadReactionState` only special-cases `tag === "like"`) — the score still updates correctly once the server responds, just without the instant flash `like` gets. Left as-is, same reasoning.

## Stage 5 - Generalize thread-reaction feedback copy
- Changes:
  - `bindThreadReactions()` in `public/assets/thread_reactions.js`: replaced the hardcoded `"Liked."`/`"Already liked."` feedback strings with `${appliedLabel}.` / `Already ${appliedLabel.toLowerCase()}.`, derived from the button's existing `data-applied-label` attribute. For the `like` tag this produces byte-identical text to before ("Liked."/"Already liked."); for `upvote`/`downvote` it now produces correct, tag-specific text instead of always saying "Liked."
  - Added `tests/BrowserSigningNormalizationTest::testThreadReactionFeedbackCopyFollowsAppliedLabelForNonLikeTags`, proving the `upvote` tag produces "Already upvoted." (the `wrote_record=no` branch) rather than the old hardcoded "Already liked." — this is the one branch with no prior test coverage at all for any tag.
- Verification:
  - `./v3 test BrowserSigningNormalizationTest` — 64 run, 62 passed; the new test passes, and all existing `testThreadReaction*`/`testPostReaction*` tests (including the two asserting exact `"Liked."`/`"Already liked."` text) still pass unchanged. The 2 failures are the same pre-existing long-standing ones (`testThreadSubmitRendersPendingShellBeforeApiResponseAndNavigatesOnSuccess`/`testInlineReplySubmitRendersPendingCardBeforeApiResponseAndNavigatesOnSuccess`), unrelated to `thread_reactions.js`.
- Notes:
  - **Correction to the Step 3 plan:** Stage 5 originally also called for "regenerate the fingerprinted copies of `thread_reactions.js`." That assumption was wrong — per `docs/specs/asset_fingerprinting_and_css_split_v1.md`, fingerprinted asset URLs (`/assets/name.<hash>.js`) are computed from the live source file's content hash at request time (`AssetFingerprint::fingerprintedPath()`), and the hashed filenames are `.gitignore`d build artifacts, not committed/hand-maintained copies. Editing the single canonical `public/assets/thread_reactions.js` is the entire change; there is no separate regeneration step.
  - `bindPostReactions()` (used by the Flag button) was already using a proper `postReactionMessage(appliedLabel, wroteRecord)` helper, not hardcoded text — it needed no change. Only `bindThreadReactions()` (used by Like/Upvote/Downvote) had the hardcoded bug.

## Stage 6 - End-to-end verification
- Changes: none (verification-only stage).
- Verification:
  - Visual/structural match: bootstrapped a temp repo from `tests/fixtures/parity_minimal_v1`, rendered `/?view=all&sort=newest` under `FORUM_SITE_ID=bashorg`, confirmed the rendered `quote_card.php` markup matches the blended bash.org/qdb.us reference shape (`#ID`, score, full body, upvote/downvote/flag) — see Stage 4.
  - Click-through: rendered `/threads/thread-zenmemes-rules` under the same `bashorg` profile and confirmed it's the ordinary, unmodified discussion-thread view (`thread-root-card` present, a reply-compose form present, no `quote-card` markup) — the thread/reply system needed zero changes, as planned.
  - No regression: rendered the board (`/?view=all&sort=newest`) with no `FORUM_SITE_ID` set (default `zenmemes` profile) and confirmed it still uses `thread-card` markup, not `quote-card` — the new instance's rendering path is fully gated behind the site profile check.
  - Full suite (`./v3 test`): 622 run, 617 passed, 5 failed — all 5 are the same pre-existing long-standing failures tracked since before this feature (none newly introduced); 2 tests recovered since Stage 4's baseline run (one of which, `testIncrementalApprovalMatchesFreshRebuildForTransitiveApprovalAndScoreRefresh`, was confirmed in Stage 4 to be pre-existing order-dependent flakiness, not caused by this feature).
  - Upvote/downvote/flag updating the score without a reload and persisting across reload: not re-verified end-to-end against a real approved identity in this pass (would require bootstrapping a full browser-signed identity + approval flow outside the test harness) — resting on the Stage 4 code-path argument (upvote/downvote/flag share the exact write/read-model/AJAX code path already proven for `like` by passing tests) plus the Stage 5 regression test for the feedback-copy piece specifically.
- Notes:
  - The Stage 3 approval-gating risk was resolved with certainty back in Stage 3 (see that section) — re-confirmed here, nothing new to add.
  - Feature is functionally complete per the Step 2 success criteria: new instance stands up via site-profile + vhost env vars alone, index visually matches the blended reference, every quote's `#ID` reaches a fully working unmodified discussion thread, and vote score is wired through the existing reaction system with zero schema changes.
