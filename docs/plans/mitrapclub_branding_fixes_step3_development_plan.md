> **Feature plan:** [Step 2](./mitrapclub_branding_fixes_step2_feature_description.md) · [Step 3](./mitrapclub_branding_fixes_step3_development_plan.md) · [Step 4](./mitrapclub_branding_fixes_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** a visitor loads any `mitrapclub` page, including a deliberately triggered busy state.
- **End-to-end outcome:** every pure-display site-name surface (header eyebrow, error/busy/update pages, codebase-state and instance-backup pages) reads "MIT Rap Club"; the busy page shows club-voiced copy. qdb's behavior/filename logic and all download filenames are unaffected since they still key off the slug.
- **Required recovery:** none new; the existing `zenmemes` fallback is untouched since `displayName()` reads the same active profile as `siteName()`.
- **Deployment/external verification:** not applicable — in-app config/copy only, no deployment target changes.
- **Release condition:** `./v3 test` passes in full; `zenmemes`/`chouse`/`qdb` render byte-identical to before this change, including their filename and qdb-specific behavior branches.

## Key Risks

- Missing one of the 6 pure-display call sites would leave an inconsistent lowercase "mitrapclub" showing somewhere. Early validation: the grep below is treated as the exhaustive list; re-grep after Stage 1 to confirm nothing was missed. Mitigation: only touch the enumerated sites.
- Accidentally switching one of the 6 identifier/filename call sites (`TagScore.php`, `ThreadAndPostPageController.php`, `thread_root_card.php`, the three `InstancePageController.php` download-filename methods) to `displayName()` would break qdb's vote-tag/quote-root behavior and turn download filenames into a spaced, mixed-case string. Early validation: `./v3 test` after Stage 1 (qdb-specific tests exercise this). Mitigation: Stage 1 explicitly lists these as untouched.
- Scope creep pulling the deferred Facebook-link or favicon items back into this cycle. Mitigation: hold the line per Step 2's scope note — those stay out.

## Full call-site inventory (from `grep -rn siteName`)

- **Pure display (Stage 1 changes these):** `src/ForumRewrite/View/TemplateRenderer.php:170`, `src/ForumRewrite/Host/FrontController.php:453,472,483,497`, `src/ForumRewrite/Http/CodebaseStateController.php:57`, `src/ForumRewrite/Http/InstancePageController.php:46`.
- **Identifier/filename (Stage 1 leaves these untouched):** `src/ForumRewrite/TagScore.php:51`, `src/ForumRewrite/Http/ThreadAndPostPageController.php:92`, `templates/partials/thread_root_card.php:59`, `src/ForumRewrite/Http/InstancePageController.php:104,123,162`.

## Stage 1
- Goal: add a profile display-name accessor and use it at every pure-display site-name surface, without touching identifier/filename usages.
- Dependencies: none.
- Expected changes:
  - `SiteConfig::displayName(): string` returning `SiteProfileRegistry::active()['displayName']`.
  - Switch the 6 pure-display call sites listed above to `SiteConfig::displayName()`.
  - `SiteConfig::siteName()` and its 6 identifier/filename call sites remain unchanged.
- Verification approach:
  - `./v3 test` full suite.
  - Manually render a `mitrapclub` page and confirm the eyebrow/header shows "MIT Rap Club".
  - Manually render `zenmemes`/`chouse`/`qdb` pages and confirm eyebrow text is unchanged (identical today since `name` == `displayName` for those three).
  - Confirm qdb-specific behavior (vote-tag quirk in `TagScore`, quote-root rendering in `thread_root_card.php`) and all three download filenames are unaffected.
- Risks or open questions:
  - Impact: a missed display call site leaves a stray lowercase slug visible.
  - Early warning / validation: re-run `grep -rn siteName` after the change; every remaining `siteName()` call should be one of the 6 enumerated identifier/filename sites.
  - Mitigation: treat the inventory above as exhaustive before closing the stage.
- Canonical components/API contracts touched: `SiteConfig` (new `displayName()` method; `siteName()` untouched).

## Stage 2
- Goal: club-voiced busy-page copy for `mitrapclub`.
- Dependencies: none (independent of Stage 1).
- Expected changes: `ProfilePresentationContent::EDITORIAL['mitrapclub']['busyTitle']`/`['busyHeading']`/`['busyMessage']` updated to club-voiced text (replacing the shared "Temporarily Busy" strings).
- Verification approach:
  - `./v3 test` full suite.
  - Render the busy path for `mitrapclub` (`ProfilePresentationContent::busy()` / `FrontController::renderBusyError()`) and confirm the new copy.
  - Confirm `zenmemes`/`chouse`/`qdb` busy copy is unchanged.
- Risks or open questions: none material — an isolated string change in the per-profile content map added in the prior feature.
- Canonical components/API contracts touched: `ProfilePresentationContent::EDITORIAL['mitrapclub']`.

Waiting for "Approved Step 3" before branching and starting Step 4.
