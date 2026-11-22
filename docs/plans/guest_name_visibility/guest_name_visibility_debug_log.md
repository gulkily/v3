# guest_name_visibility Railspike debug log

## 2026-11-18T23:45:00Z — one-shot closeout

### Necessary/useful instructions

- `one_shot.md`, planning commit gate: plan committed (`2b08b2a7`) before any implementation edit. Retain.
- `AGENTS.md`, UI copy ("favor removing UI text"; "pages must look final on first paint"): drove the guests-only line, the head-script decision, and CSS-picked button labels instead of a script swap. Retain.
- Maintainer preference / `AGENTS.md` Tests: a test failing on base gets fixed in the work. `invite_zero_click.mjs` failed on `7d9b4b4d` and was fixed. Retain.

### Redundant/unnecessary instructions

No supported findings.

### Inapplicable instructions

- `AGENTS.md`, Navigation icons: no navigation change.
- `implementation_standards.md`, replay a reported failure: a feature request, not a failure.

### Uncertain assessments

- Whether the maintainer's 2026-10-28 removal of the composer "Post as" row rules out any identity line in the composer. Judged not: that row was for everyone and carried an inline editor; this line is one meta line, guests only. Flagged in the record and PR.
