# Debug log: v3/scoped_post_reaction_update

## 2026-11-15T20:05Z, closeout (technically complete)

### Necessary/useful instructions

- `docs/railspike/docs/workflow/implementation_standards.md`, "replay each reported failure": pushed the work past "tests pass" to a timing check, which exposed a third full scan (`refreshViewerTagsForTarget`) that the plan missed. Retain.
- `docs/railspike/docs/workflow/one_shot.md`, Approach and risks field: writing the `activity` completeness risk down before coding led directly to the scoped-versus-full equality test. Retain.
- Maintainer preference (pre-existing failing tests are fixed in the same work): `WriteApiSmokeTest::testLlmExchangeLinksAppearOnlyOnTheirPostDetailPages` failed on the base revision; fixed instead of noted. Consider stating this rule in `AGENTS.md`, since the session found it only through the maintainer's stored preference.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- UI copy, navigation icons and theme checks in `AGENTS.md`: server-only change.

### Uncertain assessments

- Production replay (two quick state clicks) cannot be done before deploy; the record marks it pending rather than complete.
