# owa_instant_launch — debug log

## 2026-11-27T09:45Z — intake

- Instructions used: AGENTS.md (one-shot, first-paint rule, feature flags, tests, effort study), `docs/railspike/docs/workflow/one_shot.md`, `owa_resume` record as the shape.
- Diagnosis: `networkFirstNavigation` falls back to the cached reader only on a thrown fetch; a slow request never throws, so the installed app stays blank.
- Note: `version_check.js` reloads a page whose app version is older unless `data-nav-cached-copy="1"`, so any last-copy mode must set that attribute.

*— Claude · 2026-11-27 UTC*
