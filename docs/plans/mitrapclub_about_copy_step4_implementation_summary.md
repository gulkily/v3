> **Feature plan:** [Step 1](./mitrapclub_about_copy_step1_solution_assessment.md) · [Step 2](./mitrapclub_about_copy_step2_feature_description.md) · [Step 3](./mitrapclub_about_copy_step3_development_plan.md) · [Step 4](./mitrapclub_about_copy_step4_implementation_summary.md)

## Stage 1 - Per-profile intro and section data
- Changes:
  - `src/ForumRewrite/ProfilePresentationContent.php`: `EDITORIAL` gains `introText`, `graphHeading`/`graphParagraphs`, `participationHeading`/`participationParagraphs`, `portableHeading`/`portableParagraphs` for all four profiles.
    - `zenmemes`/`boston`/`qdb`: today's exact hardcoded template strings, copied verbatim (including inline `<a href>` markup).
    - `mitrapclub`: new club-voiced copy for all four pieces, preserving the same functional links and meaning.
  - `about()`'s `introduction` now built from `sprintf($editorial['introText'], $displayName)` instead of a fixed concatenation; `about()` also returns the three new heading/paragraphs pairs. Docblocks on `about()`/`editorial()` updated to match.
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed.
  - Byte-diffed rendered `/about/` HTML against a pre-stage snapshot for all four profiles: `zenmemes`/`chouse`/`qdb` **identical**; `mitrapclub`'s intro paragraph changed to the new club-voiced text (the only diff — the three sections still render old hardcoded markup since the template hasn't changed yet).
- Notes:
  - Confirms the stage boundary worked as planned: data exists but is only partially wired until Stage 2 touches the template.

## Stage 2 - Template switch to per-profile section content
- Changes:
  - `templates/pages/about.php`: the "graph," "participation," and "portable" sections now read `$aboutContent['graphHeading']`/`['graphParagraphs']` (and the participation/portable equivalents) instead of hardcoded markup. Headings escaped via `$e()`; paragraphs rendered unescaped to preserve inline `<a>` links, with a PHP comment documenting why that's safe (private-const-only data path, no runtime/user write).
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed (one unrelated `TaskQueueCommandTest` flake on the first run, confirmed unrelated to this change and clean on rerun).
  - Byte-diffed rendered `/about/` HTML for `zenmemes`/`chouse`/`qdb` against the pre-Stage-1 snapshot: **identical** (an initial attempt leaked an HTML comment and a whitespace difference into the output; fixed by moving the explanation into a PHP-only comment block matching the file's existing zero-indentation `<?php`-tag convention, then reconfirmed byte-identical).
  - Rendered `mitrapclub`'s `/about/`: all five functional links (`/users/`, `/activity/`, `/tools/backup/`, `/api/`, `/llms.txt`) render as real `href` anchors, not escaped text; all three new club-voiced headings ("Built on who vouches for whom," "Anyone can watch, members hold the mic," "Nothing here lives or dies with one server") appear.
- Notes:
  - Feature complete per the Step 3 Completion Contract: `mitrapclub`'s about page is fully club-voiced (intro + 3 sections) with every link working; `zenmemes`/`chouse`/`qdb` are provably byte-identical to before this feature.
