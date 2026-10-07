> **Feature plan:** [Step 2](./mitrapclub_branding_fixes_step2_feature_description.md) · [Step 3](./mitrapclub_branding_fixes_step3_development_plan.md) · [Step 4](./mitrapclub_branding_fixes_step4_implementation_summary.md)

## Stage 1 - Display-name accessor for pure-display call sites
- Changes:
  - `src/ForumRewrite/SiteConfig.php`: added `displayName()`, returning the active profile's `displayName`; `siteName()` unchanged.
  - Switched the 6 pure-display call sites to `displayName()`: `View/TemplateRenderer.php:170`, `Host/FrontController.php:453,472,483,497`, `Http/CodebaseStateController.php:57`, `Http/InstancePageController.php:46`.
  - Left the 6 identifier/filename call sites on `siteName()` untouched: `TagScore.php:51`, `Http/ThreadAndPostPageController.php:92`, `templates/partials/thread_root_card.php:59`, `Http/InstancePageController.php:104,123,162`.
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed.
  - Re-ran `grep -rn "SiteConfig::siteName()\|SiteConfig::displayName()"` and confirmed the split matches the Step 3 inventory exactly (6 and 6).
  - Rendered the board page for all four profiles: `zenmemes`/`chouse`/`qdb` eyebrow unchanged (`zenmemes`/`chouse`/`qdb`); `mitrapclub` eyebrow now reads "MIT Rap Club".
  - Confirmed qdb-specific tests (`QuoteCardDisplayNumberTest`, etc.) still pass — the identifier-based `siteName() === 'qdb'` branches are untouched.
- Notes:
  - No missed display call site; the full inventory from Step 3 planning accounted for every occurrence in the codebase.

## Stage 2 - Club-voiced busy-page copy
- Changes:
  - `src/ForumRewrite/ProfilePresentationContent.php`: updated `EDITORIAL['mitrapclub']['busyTitle']`/`['busyHeading']`/`['busyMessage']` to "Cypher's Full" / "The Cypher's Full" / "The mic's getting passed around right now. Try again in a moment."
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed.
  - Rendered `ProfilePresentationContent::busy()` for all four profiles: `zenmemes`/`chouse`/`qdb` unchanged; `mitrapclub` shows the new club-voiced copy.
- Notes:
  - Feature complete per the Step 3 Completion Contract: both branding-fix items (site-name display, busy-page copy) shipped with no regression to other profiles.
