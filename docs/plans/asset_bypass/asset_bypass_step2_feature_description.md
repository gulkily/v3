> **Feature plan:** [Step 1](./asset_bypass_step1_solution_assessment.md) · [Step 2](./asset_bypass_step2_feature_description.md) · [Step 3](./asset_bypass_step3_development_plan.md) · [Step 4](./asset_bypass_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/asset_bypass`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.4**

## Problem

In high-traffic mode, Apache serves the cached HTML, but every stylesheet, script and image on that page still costs a PHP request. The verified copies that would let Apache serve them already exist in the cache; Apache just cannot see them.

## User stories

- As the maintainer, I want a busy page view to cost no PHP requests once its page and assets are cached, so that a spike cannot exhaust PHP workers on assets.
- As the maintainer, I want turning the mode on, off, and recovering it to work exactly as today, so that I learn nothing new.
- As a visitor, I want pages to look and behave the same whether Apache or PHP sent the assets.

## Core requirements

- **Assets from the cache.** While the mode is on, Apache answers GET and HEAD for `/assets/<name>.<12-hex hash>.<ext>` from the cache's retained copy when one exists. Requests with a query string or an `Authorization` header, and misses, go to PHP as today.
- **One switch.** The mode exposes the copies through a second managed symlink beside `.high-traffic`. Turning on creates it; turning off, any withdrawal and `restore` remove it. Turning on while already on also creates it, so a server already in the mode picks it up after deploy. Direct requests to the symlink's own path are refused, like `/.high-traffic/`.
- **Headers.** Apache-served copies carry `Cache-Control: public, max-age=31536000, immutable` (PHP's value for the same URLs) `X-Content-Type-Options: nosniff` and `X-V3-High-Traffic: hit`, with directory listings off.
- **Line 35.** The condition matches presentation files by extension: css, js, ico, png, jpg, jpeg, gif, svg, webp, woff, woff2. Those files bypass PHP in both modes, with PHP's current `max-age=300, must-revalidate` header. Everything else under `/assets/` (HTML, `.map`, `.sql`, `.wasm`, licence files, `/assets/tour/`) keeps going through PHP. The new high-traffic rule uses the same extension list.
- **Saved normal rules.** Turning on while already on also refreshes the saved `normal.htaccess`. Then `restore` never brings back the old line 35.

## Decisions to confirm

1. **The server's local `public/.htaccess` edit.** I need its text, or at least which lines it touches. This change edits the high-traffic block (top of file) and line 35. A local edit on those lines makes the deploy refuse with `local_changes_conflict` and change nothing.
2. **Header lifetime.** I recommend one year, immutable, as above. The URL names the file's hash, and browsers already cache these URLs for a year from PHP. A shorter lifetime would protect only first-time visitors during the trial.
3. **Saved-copy refresh.** I recommend refreshing it on every turn-on, as above, rather than leaving `restore` unchanged.

## Corrections to Step 1

- PHP serves `/assets/1111.html` and `/assets/tour.html` without the members-only gate (`FrontController::resolveRawPublicAssetPath()` runs before it); only `/assets/tour/` is gated. This cycle keeps all HTML going through PHP and leaves that behavior unchanged. I can file it as separate work.
- PHP does not send `nosniff` on assets today. Apache adds it; content types come from Apache's `mime.types`.

## Delivery scope

Application change: the managed rules in `HighTrafficMode::routingRules()` and `public/.htaccess`, the symlink lifecycle, the runbook, the tests and this plan's records. Not in scope: normal-mode fingerprinted assets (Option B), the private-instance HTML behavior above, asset retention or cleanup. No feedback item matches; `PF-20261111-B26` (slow first visits) concerns first-byte time for HTML and stays open.

## Completion boundary

- **Entry:** Tools → Operations → High-traffic mode, or `./v3 high-traffic enable`, then an ordinary page visit.
- **End-to-end:** a warm cached page and all its fingerprinted assets load with zero PHP requests.
- **Recovery:** turning off, a withdrawal, or `restore` returns every asset request to PHP. A missing copy falls through to PHP and the page still loads.
- **Release:** the maintainer deploys, runs `./v3 high-traffic enable` (on or off beforehand), and sees assets answered without PHP.

## Known environment

zenmemes production, with its local `.htaccess` edit. Failure to handle: the deploy refuses on a conflict. The maintainer's next step is the existing one: `git stash`, `git pull`, `git stash pop` over SSH. Success shows as an asset response with `X-V3-High-Traffic: hit`. Until I see the edit, I can only test a stand-in edit locally.

## Risks

- **Deploy conflict with the local edit.** Impact: the deploy is refused, safely. Earliest check: compare the local edit with this diff before Step 3. Mitigation: keep changes off its lines.
- **Wrong content type with `nosniff`.** Impact: the browser drops a stylesheet or script. Earliest check: the isolated Apache test (`tests/high_traffic_apache_test.py`) checks each type. Mitigation: set types for the allowlist in the folder's `.htaccess`.
- **Second symlink out of step.** Impact: copies are served after the mode is off. Earliest check: tests for off, withdrawal, `restore` and turn-on while on. Mitigation: one helper removes both links.
- **Local Apache test needs Apache.** I will install it in my workspace for Step 4; it is not a project dependency.

## Shared component inventory

- `HighTrafficMode` (`change()`, `restore()`, `routingRules()`, `status()`): extended; no new mode service.
- `HighTrafficSafety::detach()`: extended to remove both links.
- `HighTrafficCache` retained `assets/` folder and `StaticArtifactBuilder::copyReferencedAssets()`: reused unchanged.
- `FrontController::resolveFingerprintedAssetPath()`: unchanged; it remains the fallback.
- The `pages/.htaccess` pattern written by `change()`: reused for the asset folder.
- Tests: `tests/high_traffic_apache_test.py` (already serves a retained copy through PHP), `WebServerRoutingTest` (asserts the old line 35; updated).
- Docs: `docs/runbooks/high_traffic_mode.md`.

## Concepts touched

- *High-traffic mode* (changed; no inventory entry yet, [runbook](../../runbooks/high_traffic_mode.md)). Proposed: add `docs/concepts/high_traffic_mode.md` in Step 4.
- [Application update](../../concepts/application_update.md) (used unchanged; its stash behavior is the deploy risk).

## User flow

1. The maintainer deploys and turns the mode on.
2. A visitor opens the board; Apache serves the cached HTML.
3. The browser requests each asset; Apache serves each from its copy.
4. The maintainer turns the mode off; assets go through PHP again.

## Success criteria

- In the Apache test, a warm board and its assets add zero lines to the PHP request count.
- Each allowlisted type arrives with the right `Content-Type`, `nosniff` and the one-year header.
- After off, withdrawal or `restore`, the same asset URLs reach PHP, and direct requests to the symlink path return 403.
- `/assets/1111.html` and a `.map` file still reach PHP in both modes.
- A deploy over a stand-in local edit at the end of the file applies cleanly.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*

Debug log: [asset_bypass_debug_log.md](./asset_bypass_debug_log.md)

## Approval

2026-11-16 23:17 ET, maintainer: "approved step 2". The approval covers decisions 2 and 3 as recommended. Decision 1 (the server's local `.htaccess` edit) is unanswered; Step 3 carries it as a risk.
