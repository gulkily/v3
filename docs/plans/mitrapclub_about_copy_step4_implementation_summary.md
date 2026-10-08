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
