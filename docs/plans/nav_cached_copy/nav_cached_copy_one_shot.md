Cycle: `v3/nav_cached_copy`; Mode: one-shot; Record/index: [this record](./nav_cached_copy_one_shot.md) · [plans index](../README.md) · [nav speed checklist](../nav_speed_checklist.md)

Debug mode: enabled ([debug log](./nav_cached_copy_debug_log.md))

Effort study: `railspike-effort` / `v3/nav_cached_copy`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Predecessor: `v3/nav_quick_wins`.

Extended estimates: disabled

**Cycle estimate: 0.40**

Status: planned on `feat/nav-cached-copy`; next permitted action: implementation.

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

Planning commit: pending.

## Dated execution history

- 2026-11-17: plan written.

## Study history

- Intake: 0.40 projected total, scope v1, baseline `e7babc06`, R1, P2; Claude single exposed rater, after building nav quick wins and reading `version_check.js`. Not pristine. R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
