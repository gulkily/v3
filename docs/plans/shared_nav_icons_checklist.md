# Shared Navigation Icons (Word 97 + Chicago)

Branch: `feature/shared-nav-icons`. One stage = one commit; update this
checklist in the same commit as the stage it records.

## Decisions (user, 2026-10-10)

- Icons are reused exactly as drawn for Word 97 (full color, no 1-bit
  redraw) in Chicago.
- Icons live in one shared stylesheet, `public/assets/theme-nav-icons.css`,
  scoped to both themes, instead of inline in each theme file.
- Scope is **navigation** buttons only: `.nav-link` (main nav, tools nav,
  qdb nav) and `.tool-launcher-button`. Not action buttons (Reply, Post,
  Submit, …).

## Progress

- [x] Stage 0 — Checklist and branch
- [ ] Stage 1 — Extract existing Word 97 nav/launcher icons into the shared
      stylesheet; load it for both themes, so Chicago gets the same icons
- [ ] Stage 2 — Draw icons for navigation buttons that have none in Word 97
      (they then appear in both themes via the shared file)
- [ ] Stage 3 — Verify Chicago has every icon and fill any gaps / spacing
      differences

## Inventory

Word 97 today (icon exists): nav links `/`, `/about/`, `/users/`, `/tools/`,
`/account/key/`, `/invites/`, `/compose/thread`; tool launchers `/activity/`,
`/forte`, `/tools/bookmarklets/`, `/tools/backup/`, `/tools/sqlite/`,
`/tools/llm-exchanges/`, `/tools/codebase/`, `/tools/feature-flags/`,
`/offline/`, `/docs/`, `/tools/outbox/`, `/tools/visitor-statistics/`,
`/account/key/`, bookmarklet kinds `url`, `clip`, `selection`, `tweet`.

Navigation buttons with no icon yet (stage 2 candidates): `/lobby/`,
`/profiles/*` (Profile), `/messages/inbox`, `/tags/`, qdb nav (`/latest`,
`/top`, `/leetness`, `/random`, `/add`, `/search`), and every tools-nav
`.nav-link` (the tool launcher icons are only keyed to
`.tool-launcher-button` today).

## Constraints

- Theme-menu rows are representative (see
  `docs/specs/theme_menu_representative_options_spec_v1.md`): never style
  `.theme-swatch`, `.theme-menu__trigger`, `.theme-menu__option`.
- `rm -f public/index.html` before `php tests/run.php`.
