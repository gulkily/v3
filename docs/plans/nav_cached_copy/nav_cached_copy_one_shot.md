Cycle: `v3/nav_cached_copy`; Mode: one-shot; Record/index: [this record](./nav_cached_copy_one_shot.md) · [plans index](../README.md) · [nav speed checklist](../nav_speed_checklist.md)

Debug mode: enabled ([debug log](./nav_cached_copy_debug_log.md))

Effort study: `railspike-effort` / `v3/nav_cached_copy`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Predecessor: `v3/nav_quick_wins`.

Extended estimates: disabled

**Cycle estimate: 0.40**

Status: technically complete on `feat/nav-cached-copy`; release check pending maintainer; next permitted action: maintainer review. Cycle 4 (`page_weight`) waits for the maintainer.

# Navbar pages from the last copy, refreshed in the background

## Original request

Same thread as [nav quick wins](../nav_quick_wins/nav_quick_wins_one_shot.md). Decisions, 2026-11-17 (America/New_York 2026-11-16): "definitely avoid flashing stale content..."; then "i would say reuse the cached copy, load new copy in the background, and then either leave it queued for next click or tell the user there's a new version (already an existing optional feature)"; asked whether the notice "look[s] at the content repo" (it does: `app-version` is the content repository's HEAD); then "skip nav_timing, one-shot nav_quick_wins and nav_cached_copy sequentially, pause before page_weight".

## Authorization

Maintainer, the last message above. Scope: checklist cycle 3. Excluded: page weight (cycle 4, paused), an in-place main swap, per-page versions.

## Outcome and work type

Application change behind a new flag, "Navbar pages from last copy" (`FORUM_NAV_CACHED_COPY_ENABLED`, Forum experience, site-changeable, off by default). With it on and the worker in control, a navbar destination (the main nav and the More menu) the browser has opened before is answered at once from the copy kept last time. The worker then fetches the page in the background and keeps the fresh copy for the next click. If the fresh copy's `app-version` differs from the one shown, the existing new-version notice appears (when "App version notification" is on); its Reload opens the page from the network. The shown page is never replaced or changed by the refresh.

Off by default: it changes what a reader sees (a possibly older page plus a notice), so each instance opts in. The maintainer turns it on at zenmemes.com.

## Approach and risks

- The worker keeps copies in its own cache, `<offline prefix>nav-copy`, with the list of navbar paths the page last sent. Pages with the flag off delete that cache, so the worker has nothing to serve. A new worker version deletes it too (it is an owned cache other than the reader cache), so a code deploy that changes the worker starts clean.
- Only GET, same-origin, no query string, a path in the list, an `ok` and non-redirected response without `no-store`. Offline (`navigator.onLine` false), the existing offline handling runs instead.
- The background refresh uses the early fetch or the preload from nav quick wins when there is one, so it adds no request.
- Risk, found while planning: `version_check.js` remembers a version it has announced and, on a later page with an older version, redirects to `?__v=`. On a page served from a copy that would replace the page after it shows, which is the flash the maintainer ruled out. Mitigation: with this flag on, that case shows the notice instead of redirecting.
- Risk: a copy from another viewer state (signed out, other key). Pages decide viewer-specific UI in the client (AGENTS.md), and counts are fetched live, so the HTML is close to viewer-independent; still, the copies are dropped on `forum:identity-changed`.
- Risk: `app-version` is repository-wide, so any new post marks every copy as behind. Accepted as decided: the notice appears; per-page versions are out of scope.

## Reuse decisions

- `public/service_worker.js` navigation handler and early-fetch/preload from nav quick wins: extended.
- New-version notice (`version_check.js`, `[data-role="app-version-banner"]`): reused; gains a small `window.ForumAppVersion.show(version)` so the copy script can raise it, and the redirect change above.
- `fast_navigation.js` pattern (flag attribute on `<html>`, script only with the flag on): reused for a new `nav_cached_copy.js`; kept separate because the two flags are independent.
- No new UI.

## Completion conditions

- Entry: tapping a navbar destination opened before, on zenmemes.com with the flag on.
- End to end: the page appears from the copy with no server wait; the copy is refreshed; the next tap shows the refreshed copy; when the content changed, the notice appears and Reload opens the current page.
- Recovery: turning the flag off deletes the copies at the next page load; a failed refresh keeps the old copy and changes nothing on screen.
- Release: the maintainer turns the flag on and uses zenmemes.com on his iPhone. Not reachable here.

## Concepts touched

None in the inventory.

## Verification and recovery plan

- `tests/browser/nav_cached_copy.mjs` on a local server: flag off keeps no copy; flag on serves a visited navbar page with the server stopped; the refreshed copy is used next; a new post between visits raises the notice and does not change the shown page; with a remembered pending version the page is not redirected; identity change drops the copies; non-navbar pages are never served from a copy.
- `tests/browser/fast_navigation.mjs` and `offline_compose.mjs` still pass.
- PHP layout test; flag-list test; smoke set. The full suite is not run.

## Starting revisions and branch

Branch `feat/nav-cached-copy` from `feat/nav-quick-wins` at `e7babc06` (PR #291, not merged), because this cycle extends that worker code. This PR contains #291; merge #291 first.

Planning commit: `6e9f9d01`, before any implementation edit.

## Dated execution history

- 2026-11-17: plan written and committed (`6e9f9d01`).
- 2026-11-17: `FeatureFlagRegistry::NAV_CACHED_COPY_ENABLED` ("Navbar pages from last copy", EXPERIENCE, off, site-changeable). `TemplateRenderer` passes `navCachedCopyScriptPath` only with the flag on; the layout writes `data-nav-cached-copy="1"`/`"0"` and loads the script.
- 2026-11-17: `service_worker.js`: `navCopyTarget()` finds a listed navbar path in `<prefix>nav-copy` (GET, same origin, no query, online). With a kept copy, the copy answers and a background refresh (early fetch, else preload, else fetch) replaces it and records its `app-version` for 30 s under `nav-copy-status`. Without one, the normal response answers and an `ok`, non-redirected, non-`no-store` response is kept. A network-served navbar page clears any older refresh record for its path, so it is never told about an older copy.
- 2026-11-17: `public/assets/nav_cached_copy.js` (new): writes the main-nav and More paths into the copy cache, asks the worker for this path's fresh version and raises the notice through `window.ForumAppVersion.show()`, and deletes the cache on `forum:identity-changed`.
- 2026-11-17: `pwa_registration.js`: with `data-nav-cached-copy="0"`, deletes the copy cache after load.
- 2026-11-17: `version_check.js`: exposes `window.ForumAppVersion`; with the flag on, a remembered pending version shows the notice instead of redirecting to `?__v=`. Side effect, accepted: under the flag this also applies to a page that is old for other reasons (for example a static release), which now offers Reload instead of reloading itself.
- 2026-11-17: correction during testing: the version check's own node tests stub `document` without `documentElement.getAttribute`; the new check now tolerates that, and a test for the new branch was added.
- 2026-11-17: one run of the browser test failed once at "board copy refreshed on the previous visit" and passed in eight runs since, unchanged. Cause not found; most likely the refresh had not finished writing before the next visit. Recorded, not fixed.

## Outcome

Delivered as planned. With the flag on, a navbar page opened before appears from its copy; the copy is refreshed in the background and used on the next visit; when the content changed, the existing notice appears and its Reload loads the current page. Nothing on screen is replaced. Off: copies are deleted at the next page load.

Limitations: `app-version` is repository-wide, so any new post anywhere makes the notice appear on the next copied page. No maximum age: a copy from days ago is still shown, with the notice. Not checked on iOS Safari or zenmemes.com.

## Verification

- `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/nav_cached_copy.mjs`: passed in eight of nine runs (see history). Flag off: no copy cache. Flag on: copies of `/` and `/users/`; after a new commit the next visit shows the older copy, the notice appears, the URL is unchanged; a remembered newer version does not redirect the board copy; the next visit gets the refreshed copy; Reload opens the current version; a key change drops the copies; with the server stopped, Users comes from its copy and About (not a navbar page) fails.
- `tests/browser/fast_navigation.mjs` and `tests/browser/offline_compose.mjs`: passed.
- `./v3 test --smoke VersionCheckBehaviorTest LocalAppSmokeTest FastNavigationLayoutTest FeatureFlagEvaluatorTest FeatureFlagChangePageTest AppNavigationPlacementTest PrivateMessageFeatureFlagTest`: 486 of 486 after the fix above (first run: 2 `VersionCheckBehaviorTest` failures caused by this change). The full suite was not run.
- Not done: iOS Safari, a real logged-in session, zenmemes.com.

## Final review

Diff read: one flag, renderer path and layout attribute, the worker's copy handling and status message, one new page script, the copy-cache cleanup, two `version_check.js` additions, tests. Reuses the notice and the nav quick wins worker path; nothing outside the authorized scope.

## Commit, merge and publication status

Planning commit `6e9f9d01`. Completion commit pending in this record; see the PR. Not merged. Contains PR #291's commits.

## Study history

- Intake: 0.40 projected total, scope v1, baseline `e7babc06`, R1, P2; Claude single exposed rater, after building nav quick wins and reading `version_check.js`. Not pristine. R1 hashes not re-verified.
- Execution entry: 0.40 reaffirmed.
- Closeout: retrospective scope assessment 0.40; no revisions (the `version_check.js` redirect change was planned). Maintainer comfortable-fit judgment pending.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
