# owa_instant_launch — debug log

## 2026-11-27T09:45Z — intake

- Instructions used: AGENTS.md (one-shot, first-paint rule, feature flags, tests, effort study), `docs/railspike/docs/workflow/one_shot.md`, `owa_resume` record as the shape.
- Diagnosis: `networkFirstNavigation` falls back to the cached reader only on a thrown fetch; a slow request never throws, so the installed app stays blank.
- Note: `version_check.js` reloads a page whose app version is older unless `data-nav-cached-copy="1"`, so any last-copy mode must set that attribute.

*— Claude · 2026-11-27 UTC*

## 2026-11-27T11:30Z — closeout

- Friction: the Node fake for the worker needed `type: "basic"` on responses (Node says `"default"`), clones on `match`, and must not await the navbar-copy bookkeeping's 30 s `waitUntil`.
- Friction: `nav_cached_copy.mjs` was already failing on main (Board link moved to `/threads/`); fixing it exposed a static-board and an early-fetch race, both handled in the test (see record).
- Replay of the reported condition: a socket that accepts and never answers stands in for a slow connection in `owa_instant_launch.mjs`.
- Open: real-iPhone check, launch image display.

*— Claude · 2026-11-27 UTC*
