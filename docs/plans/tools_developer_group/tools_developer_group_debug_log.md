# Debug log — v3/tools_developer_group

## 2026-11-24T18:20Z — One-shot closeout

### Necessary/useful instructions

- `AGENTS.md` (host), Navigation icons: a group heading is not a destination, but the stylesheet keys a glyph to each heading's `data-shape`, so the new group needed one in all three icon sets.
- `AGENTS.md` (host) and the maintainer's standing rule, a test failing on the base commit is fixed as part of the work: the chouse launcher check failed on `origin/main` and was fixed here.
- `AGENTS.md` (host), verify the UI in each affected theme: measuring the launcher's height, not only running the existing check, showed the fifth heading pushed the last row below 720 px in the default themes.
- `one_shot.md`, record scope growth: the spacing change and the new measurement are listed as growth with their reason.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- Feedback trailers: no item matched and none was allocated.

### Uncertain assessments

- The plan said the browser check validated the 720 px fit. It did not; the plan's risk section was wrong until the measurement was added.
- Tightening the gaps changes the launcher's look in every theme, which the maintainer did not ask for by name. It restores the default themes to their earlier height; he may prefer the looser spacing and a little scrolling.
- Word97 and Midnight miss the fit by 7 to 10 px and were left that way.
- The Developer glyph in the smooth-line set is close to System status's tool glyph.
- The maintainer did not answer whether Platform Docs should move; it moved, as proposed.
