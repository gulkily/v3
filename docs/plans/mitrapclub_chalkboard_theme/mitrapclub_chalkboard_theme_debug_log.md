# mitrapclub_chalkboard_theme debug log

## 2026-11-25 UTC — one-shot intake

- Instructions read: AGENTS.md, docs/railspike/docs/workflow/one_shot.md, the Underground theme record as precedent.
- Used: one-shot planning gate, the single-stylesheet theme pattern, AGENTS.md test subset rule.
- Missing/unclear: "80% MIT Rap Club" has no written brand guide; used the club's own artifacts on record (cypherposium.com, the underground concert flyer as described in the Underground record, club copy in ProfilePresentationContent).

## 2026-11-25 UTC — one-shot closeout

- Mid-task scope addition (switches) recorded in the record; root cause was a theme submit-button rule matching `role=switch` buttons. Other themes with submit rules (Bubblegum, Spearmint) checked by screenshot.
- Friction: no fixture content for theme screenshots on a fresh clone; `./v3 import-instance zenmemes.com` (28 s) supplied it. Theme preview needed a temporary profile edit, since no URL parameter selects a theme.

## 2026-11-25 UTC — correction

- Misread "mitrapclub theme" as the theme *named* mitrapclub; the user meant the theme the mitrapclub site shows (Underground). Lesson: when a profile and a theme share a name, confirm which one before a redesign.
- `body` has `isolation: isolate`; a root-level fixed layer at negative z-index paints under it. Use a pseudo-element inside body (`.shell::before`) for page-margin art.
