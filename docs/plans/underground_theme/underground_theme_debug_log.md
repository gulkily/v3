# underground_theme debug log

## 2026-11-23 UTC — one-shot intake

- Instructions read: AGENTS.md, docs/railspike/RAILSPIKE.md (mode selection), docs/railspike/docs/workflow/one_shot.md, docs/runbooks/theme_development_guide.md.
- Used: one-shot entry/planning gate, theme guide's registry + single stylesheet pattern, AGENTS.md test subset rule.
- Missing/unclear: no guidance on which profile(s) a profile-specific theme should be offered on; chose mitrapclub (default) + zenmemes and recorded why.

## 2026-11-23 UTC — closeout

- Used: theme guide (registry, single stylesheet, LocalAppSmokeTest allow-list), AGENTS.md test subset.
- Missing: theme guide doesn't mention that a profile's `brandedStylesheet` slot overrides `defaultTheme`; cost one debugging round. Worth a line in the guide.
- Missing: dev server serves stale pages from `state/static_html_<profile>` after a default-theme change; had to delete it and restart.
