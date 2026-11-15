Cycle: `v3/live_feature_tour`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/live_feature_tour` — [host ledger](../effort_estimation_trial.md#cycle-v3live_feature_tour); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.05**
Status: implemented and verified locally; next permitted action: push and PR (merge and deploy need the maintainer)

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

`e8eafa5c` (confirmed with Git).

## Dated execution history

- 2026-11-15: cycle opened; plan written before implementation. The page itself was drafted earlier in the same chat as a standalone file against production URLs (prior exposure).
- 2026-11-15: added `public/assets/tour.html` from the draft. Changed absolute zenmemes.com URLs to same-origin paths and the address bars to `location.host`. Addition within the "don't link it" boundary: `<meta name="robots" content="noindex">` so search engines don't surface the page before it is linked.

## Study history

- Intake 2026-11-15: **0.05**. Exposed: the page had already been drafted before the cycle opened. Rater Claude (claude-opus-5-5).
- Execution entry 2026-11-15: 0.05 reaffirmed.
- Closeout 2026-11-15: retrospective scope assessment **0.05**; delivered as planned. Maintainer fit/acceptance pending.

## Outcome

`/assets/tour.html` serves a grid of 16 live, scaled-down frames of the main pages, with Desktop/Phone and Look/Interact toggles. Not linked from anywhere; `noindex`.

Limitation: the two thread frames and the "ZenMemes" wording are specific to zenmemes.com; on another instance or a dev database those thread frames show Not Found, and Gallery/Calendar show their off states when those flags are off.

## Verification

- `php -S` dev server with `public/router.php`: `/assets/tour.html` returns 200, `text/html; charset=UTF-8`.
- Headless Chrome render against the dev server (desktop): all frames load same-origin; thread and Gallery frames show Not Found on dev data, as expected.
- Headless Chrome render of the same file against production URLs, phone view: all frames load with real content. Production sends no `X-Frame-Options` or CSP `frame-ancestors`.
- `grep` for `tour.html` outside `docs/plans/`: no references, so nothing links to it.
- `./v3 test` (smoke set): 464 run, 464 passed. Full suite not run.
- `public/**/*.html` is git-ignored (generated static pages); the file is force-added like `public/assets/1111.html`.
- Not checked: the deployed URL itself (needs merge and deploy); Apache serving `.html` under `/assets/` is assumed from `/assets/1111.html` returning 200 in production.

## Final review

Diff is one new flat asset plus this record, the plans index, the ledger entry and the debug log. No duplicate components: the page is standalone like `1111.html`. No concepts touched.

## Commit/merge/publication status

Planning commit `e8eafa5c`; completion commit `0ca0bed5` (confirmed with Git). Pushed as `feat/live_feature_tour`; PR #255 open. Not merged or deployed.

## Attribution

Claude (claude-opus-5-5), 2026-11-15 UTC.
