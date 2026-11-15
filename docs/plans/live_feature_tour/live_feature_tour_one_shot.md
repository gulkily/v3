Cycle: `v3/live_feature_tour`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/live_feature_tour` — [host ledger](../effort_estimation_trial.md#cycle-v3live_feature_tour); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.05**
Status: planned; next permitted action: implementation

## Original request

In a Claude chat on 2026-11-15 the maintainer asked for "a page of screenshots of the most salient V3 and Zen memes features", where each screenshot is a live page and all are on one page. Claude built a standalone HTML page that frames live zenmemes.com pages in a scaled-down grid. The maintainer then wrote: "Can you put it onto Zen Memes? Don't link it from anywhere yet, please."

## Authorization

The second message above, 2026-11-15, read as authorization for a one-shot cycle that adds the page to the site, pushes a branch and opens a PR. Merging and deploying stay with the maintainer.

Excluded: any link to the page from the nav, About, Tools, the agent page, llms.txt or any post. Not adding it to the sitemap or other discovery surfaces.

## Outcome and work type

Application change (one static asset). Normal entry: visiting `/assets/tour.html` directly.

Outcome: a self-contained page showing a grid of live, scaled-down frames of the board, a thread, the agent how-to thread, Prompts, Recent, Gallery, Calendar, Tags, Users, Activity, Compose, Your key, Backup, API, llms.txt and About, each with a short caption and an Open link. Desktop/Phone and Look/Interact toggles. Frames use same-origin relative paths, so the page works on any v3 instance and dev server; the two thread frames point at ZenMemes threads and are the only ZenMemes-specific content.

## Approach and risks

Follow the 11/11 page precedent (`c9f47185`): a flat file in `public/assets/`, served by the existing static asset route with the existing `.html` → `text/html` mapping in `src/ForumRewrite/Host/FrontController.php`. No PHP, template, nav or schema change.

Risks:
- Framing: if the site sends `X-Frame-Options: DENY` or `frame-ancestors 'none'`, frames would be blank. Production sends neither today (checked 2026-11-15); same-origin framing would pass `SAMEORIGIN` anyway.
- Unhashed asset name: other assets carry content hashes for cache busting; `1111.html` does not, and a hand-visited page does not need one.
- Logged-in frames show the viewer's own session (Unread, You menu). Acceptable: it is the real site.

## Reuse decisions

- Hosting: existing static asset route and HTML content type (precedent `public/assets/1111.html`). No new route.
- Styling: self-contained page CSS; it is a presentation page outside the site chrome, like `1111.html`, so it does not use the site stylesheets or theme components.

## Concepts touched

None.

## Verification and recovery plan

- Run the dev server and fetch `/assets/tour.html`: 200 with `text/html`.
- Render the page in headless Chrome against the dev server and production, desktop and phone toggles, and check frames load.
- Confirm no references to `tour.html` anywhere else in the repo (unlinked).
- Smoke set only (`./v3 test`); no tests cover a flat asset. Full suite not run.

## Starting revisions and branch

Host `main` at `b3f7e3e2cefb9a78e3921976208f72a03509aeb2`; branch `feat/live_feature_tour`.

## Planning commit

Pending.

## Dated execution history

- 2026-11-15: cycle opened; plan written before implementation. The page itself was drafted earlier in the same chat as a standalone file against production URLs (prior exposure).

## Study history

- Intake 2026-11-15: **0.05**. Exposed: the page had already been drafted before the cycle opened. Rater Claude (claude-opus-5-5).
- Execution entry 2026-11-15: 0.05 reaffirmed.
