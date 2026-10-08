> **Feature plan:** [Step 1](./mitrapclub_about_copy_step1_solution_assessment.md) · [Step 2](./mitrapclub_about_copy_step2_feature_description.md) · [Step 3](./mitrapclub_about_copy_step3_development_plan.md) · [Step 4](./mitrapclub_about_copy_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** a visitor loads `/about/` on any profile.
- **End-to-end outcome:** `mitrapclub`'s about page shows club-voiced intro and all three sections (identity/participation/backup), with every existing functional link (`/users/`, `/activity/`, `/tools/backup/`, `/api/`, `/llms.txt`) still working; `zenmemes`/`chouse`/`qdb` render byte-identical to today.
- **Required recovery:** none — static content, no new failure mode.
- **Deployment/external verification:** not applicable.
- **Release condition:** `./v3 test` passes in full; a manual byte-diff of rendered `/about/` HTML confirms no change for `zenmemes`/`chouse`/`qdb`.

## Key Risks

- **High risk:** the three sections' current paragraphs contain inline `<a href>` links embedded in prose. The existing data-driven pattern (`communityParagraphs`) escapes text with `$e()`, which would turn those links into literal escaped text. Resolution: render these three sections' paragraphs unescaped in the template, the same trust boundary the template already uses for its own hardcoded HTML — this is developer-authored constant data (a private PHP `const`), never user input, so no new XSS surface opens. Early validation: Stage 2's manual link-click check. Mitigation: keep this content path non-dynamic (no runtime/user write ever reaches `EDITORIAL`).
- No existing automated test asserts the exact wording of the intro sentence or the three sections, so a mismatch between the new per-profile text and today's hardcoded text for `zenmemes`/`chouse`/`qdb` would be a silent regression. Early validation: explicit manual byte-diff of rendered `/about/` HTML before/after each stage. Mitigation: copy today's exact hardcoded strings verbatim into their per-profile fields; don't paraphrase.
- Writing genuinely good club-voiced copy for the identity/participation/backup sections is a content-quality judgment call, not a pass/fail test (carried from Step 2). Mitigation: keep meaning faithful to today's text; treat wording as iterable after this ships.

## Stage 1
- Goal: make the intro sentence and the three sections' heading/paragraph content per-profile, wired through `ProfilePresentationContent::about()`.
- Dependencies: none.
- Expected changes:
  - `ProfilePresentationContent::EDITORIAL` gains, for all four profiles: `introText` (a `%s`-templated string, same pattern as `docsIntro`), `graphHeading`/`graphParagraphs`, `participationHeading`/`participationParagraphs`, `portableHeading`/`portableParagraphs` (each `Heading` a string, each `Paragraphs` a `list<string>`).
  - For `zenmemes`/`boston`/`qdb`: these new fields hold today's exact hardcoded template strings verbatim (including the inline `<a href>` markup for the three sections), so `sprintf(introText, displayName)` reproduces today's computed introduction byte-for-byte.
  - For `mitrapclub`: new club-voiced versions of all four pieces, preserving the same functional links and meaning.
  - `ProfilePresentationContent::about()`'s `introduction` key is now built from `sprintf($editorial['introText'], $displayName)` instead of the current fixed concatenation; `about()` also returns `graphHeading`/`graphParagraphs`/`participationHeading`/`participationParagraphs`/`portableHeading`/`portableParagraphs`.
  - Return-type docblocks on `about()`/`editorial()` updated to match.
- Verification approach:
  - `./v3 test` full suite.
  - Render `ProfilePresentationContent::about()` for all four profiles; diff the `introduction` value for `zenmemes`/`chouse`/`qdb` against today's exact computed string (must match verbatim); confirm `mitrapclub`'s is the new club-voiced text.
  - `templates/pages/about.php` is untouched in this stage, so the three sections still render their old hardcoded markup regardless of the new (as-yet-unused) `about()` fields — only the intro paragraph can visibly change in this stage.
- Risks or open questions:
  - Impact: a typo or paraphrase in the "unchanged" profiles' `introText` silently alters their rendered intro sentence.
  - Early warning / validation: byte-diff check above, done before moving to Stage 2.
  - Mitigation: copy-paste today's exact string rather than retyping it.
- Canonical components/API contracts touched: `ProfilePresentationContent::EDITORIAL`, `about()`, `editorial()`.

## Stage 2
- Goal: switch the three hardcoded sections in the about template to the new per-profile data.
- Dependencies: Stage 1 (the data must exist first).
- Expected changes:
  - `templates/pages/about.php`: the "graph," "participation," and "portable" `<section>` blocks read `$aboutContent['graphHeading']`/`['graphParagraphs']` (and the participation/portable equivalents) instead of hardcoded markup — heading escaped via `$e()` as today; paragraphs rendered unescaped (see Key Risks) to preserve inline links.
  - Same section order, same `data-about-section` attributes, same surrounding structure (hackable-section gate and social-links block untouched).
- Verification approach:
  - `./v3 test` full suite.
  - Manual byte-diff of rendered `/about/` HTML for `zenmemes`/`chouse`/`qdb` against a pre-Stage-1 snapshot — must be identical.
  - Render `mitrapclub`'s `/about/`: confirm club-voiced headings/paragraphs appear and every link (`/users/`, `/activity/`, `/tools/backup/`, `/api/`, `/llms.txt`) is a real, clickable anchor, not escaped text.
- Risks or open questions:
  - Impact: unescaped paragraph rendering is only safe because this content is a private PHP constant.
  - Early warning / validation: confirm no other code path ever writes into `ProfilePresentationContent::EDITORIAL` at runtime (it's a `private const`, so this is structurally guaranteed, not just a convention).
  - Mitigation: none needed beyond the structural guarantee — noted so a future contributor doesn't casually make this field runtime-writable.
- Canonical components/API contracts touched: `templates/pages/about.php`.

Waiting for "Approved Step 3" before branching and starting Step 4.
