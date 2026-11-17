Cycle: `v3/nav_quick_wins`; Mode: one-shot; Record/index: [this record](./nav_quick_wins_one_shot.md) · [plans index](../README.md) · [nav speed checklist](../nav_speed_checklist.md)

Debug mode: enabled ([debug log](./nav_quick_wins_debug_log.md))

Effort study: `railspike-effort` / `v3/nav_quick_wins`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: disabled

**Cycle estimate: 0.30**

Status: planned on `feat/nav-quick-wins`; next permitted action: implementation.

# Nav quick wins: a navbar tap starts loading sooner and shows at once

## Original request

ZenMemes thread [thread-20261116184900-5ca26894](https://zenmemes.com/threads/thread-20261116184900-5ca26894): "how to speed up click to done time for the nav bar? still feels sluggish on prod #meta". After the checklist was drafted: "skip nav_timing, one-shot nav_quick_wins and nav_cached_copy sequentially, pause before page_weight".

## Authorization

Maintainer, 2026-11-17 UTC (2026-11-16 23:26 America/New_York), the message above, after "You can pretend to be me for this ... rail?" earlier the same day. Scope: checklist cycle 2. Excluded: measurement (cycle 1, skipped), the cached copy (cycle 3, its own record next), page weight (cycle 4, paused), an in-place main swap.

## Outcome and work type

Application change, behind one new flag, "Fast navigation" (`FORUM_FAST_NAVIGATION_ENABLED`, Forum experience, site-changeable, on by default). With it on:

1. The service worker turns on navigation preload, so the page request starts while a stopped worker is still starting up. It answers navigations with the preloaded response when there is one.
2. When a finger or mouse goes down on a navbar link (bar, More and You menus, New post), the page asks the worker to fetch that page. If the navigation for the same address follows within 10 seconds, the worker answers it with that response.
3. When the click lands, the tapped destination is marked active at once, using the existing `is-active` style, so the bar reacts before the next page arrives.

Off: no preload, no early fetch, no early highlight, exactly as today.

On by default because nothing visible changes except the highlight arriving sooner, and the maintainer wants it on zenmemes.com. Each part falls back to today's behaviour on any failure.

## Approach and risks

- The worker is a static file shared by every site profile and baked into static releases, so it cannot read flags. The page decides: `pwa_registration.js` (already loaded on every public page) enables or disables `registration.navigationPreload` from a `data-fast-navigation` attribute on `<html>`, and a new small page script, `fast_navigation.js`, is loaded only with the flag on and sends the early-fetch message. The worker only acts on what pages tell it.
- Risk: an early fetch follows a redirect. A redirected response cannot answer a navigation. Mitigation: the worker discards redirected or non-OK responses and falls back to preload or network.
- Risk: double server work. A tap that uses the early response still triggers the preload request, which is discarded. Impact: one extra render per navbar tap for a logged-in viewer (anonymous pages are static files). Mitigation: the early fetch is limited to navbar links; recorded for page_weight or later work if server load shows it.
- Risk: the tap is a scroll or is cancelled. Impact: one unused fetch. Mitigation: the entry expires after 10 seconds; the highlight is set on click, not on pointerdown, so a scroll never moves it.
- Risk: offline. Mitigation: the early fetch is skipped when `navigator.onLine` is false, and the worker's offline fallbacks run unchanged when a fetch fails.
- Risk: the page comes back from the back/forward cache with the highlight moved. Mitigation: `pageshow` with `persisted` restores the original active link.

## Reuse decisions

- `public/service_worker.js` `networkFirstNavigation()`: extended in place; its offline fallbacks stay as they are.
- `public/assets/pwa_registration.js`: extended to set navigation preload, since it already owns worker registration.
- `.nav-link.is-active` / `.new-post-button.is-active` (`site.css` and 23 theme rules): reused for the early highlight, so no theme needs new rules and no icon mapping changes.
- `FeatureFlagRegistry.php`: one definition in the EXPERIENCE group, after Usage reports' pattern (`siteMutable`).
- `TemplateRenderer.php` and `templates/layout.php`: the conditional-script pattern used for `usage_log.js`.
- New: `public/assets/fast_navigation.js`, because no existing script handles navbar pointer events before navigation; `nav_slots.js` only counts phone slot use.

## Completion conditions

- Entry: tapping a navbar destination on zenmemes.com.
- End to end: with the flag on and the worker in control, a navbar tap is answered from the early fetch (one request served by the worker for the tap) and the tapped item shows active before the new page paints; other navigations are answered from the preload.
- Recovery: turning the flag off in Tools → Feature flags disables preload at the next page load and stops early fetches; a failed early fetch falls back to a normal request.
- Release: the maintainer deploys and taps around zenmemes.com on his iPhone. Not reachable from this session.

## Concepts touched

None in the inventory: no concept covers worker navigation handling. [Navigation slots](../../concepts/navigation_slots.md) used unchanged.

## Verification and recovery plan

- New `tests/browser/fast_navigation.mjs` against a local `php -S` server with the parity fixture: worker in control; preload enabled with the flag on and disabled with it off; a navbar tap answered by the early fetch; the highlight set on click; offline reader fallback still served when the server is down.
- PHP: a layout test that the attribute and script appear only with the flag on; `FeatureFlagEvaluatorTest`; the smoke set. The full suite is not run.
- Not checkable here: iOS Safari's worker start-up time and the real gain on the maintainer's phone.

## Starting revisions and branch

Branch `feat/nav-quick-wins` from `origin/main` at `c010efca`.

Planning commit: pending.

## Dated execution history

- 2026-11-17: plan written.

## Study history

- Intake: 0.30 projected total, scope v1, baseline `c010efca`, R1, P2; Claude single exposed rater, 2026-11-17 UTC, after measuring prod and reading the worker, registration script, nav templates and flag registry. Not a pristine intake. R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
