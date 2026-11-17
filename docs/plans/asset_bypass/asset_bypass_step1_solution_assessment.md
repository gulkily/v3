> **Feature plan:** [Step 1](./asset_bypass_step1_solution_assessment.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/asset_bypass`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.35

## Original Query

While closing [tour_snapshots](../tour_snapshots/tour_snapshots_step4_implementation_summary.md) (PR #264), Claude noted: "the .htaccess rule meant to let assets skip PHP only matches /assets/ itself. So every asset request in production goes through PHP." The maintainer asked (2026-11-16): "does this mean that high traffic mode doesn't cover /assets/?" After the answer: "please start".

## Understood Intent

When the site is busy, page views should not cost a PHP request per stylesheet, script and image. High-traffic mode already makes the HTML cheap; the assets those pages load should be cheap too.

## Problem Statement

`public/.htaccess` line 35, `RewriteCond %{REQUEST_URI} ^/(?:assets(?:/|$)|favicon\.ico)$`, matches only `/assets`, `/assets/` and `/favicon.ico`: the final `$` applies to the `assets/` branch too. So every file under `/assets/` goes to `index.php`, which reads and sends it (no render, but a full PHP request each).

Fixing the regex alone would change almost nothing: pages reference fingerprinted URLs such as `/assets/site.c3a8d1ca7e52.css`, which are not files on disk; `FrontController::resolveFingerprintedAssetPath()` maps them back to the source after checking the hash. On the board page, 34 of 34 asset references are fingerprinted. Apache's `-f` check fails for all of them, so they would still reach PHP.

High-traffic mode already keeps verified copies of exactly those files: rendering a cached page copies every referenced fingerprinted asset into the cache (`StaticArtifactBuilder::copyReferencedAssets()`), and PHP serves those copies first (`resolveFingerprintedAssetPath()` checks the cache's `assets/` folder). Only the HTML is exposed to Apache (through the `.high-traffic` symlink); the asset copies are not.

## Concepts touched

- All options: *high-traffic mode* (existing; [runbook](../../runbooks/high_traffic_mode.md)). Option A extends what it serves; B and C do not change it.

## Solution Options

### Option A: High-traffic mode serves its asset copies through Apache (recommended)

**Estimate: 0.35**

- Expose the cache's verified asset copies next to the cached pages (a second symlink created and removed with the mode, like `.high-traffic`) and add one rule to the high-traffic block: a GET/HEAD for `/assets/<name>.<12-hex hash>.<ext>` is served from the copy when it exists. A miss falls through to PHP as today.
- Copies are named by content hash, so they get `Cache-Control: public, max-age=31536000, immutable` and `nosniff` from a small `.htaccess` in that folder, matching what PHP sends.
- Also correct the broken condition so plain presentation files (by extension: css, js, images, fonts, favicon) bypass PHP as its comment intends, and HTML such as `/assets/tour/` and `/assets/1111.html` keeps going through PHP and its members-only gate.
- Pros: covers exactly the busy case; files are already written, hash-checked and cleaned up by the mode; normal mode unchanged; private instances never enable the mode.
- Cons: normal-mode asset requests still go through PHP; one more symlink to keep in step with the mode.
- Driver: the rule, the symlink lifecycle in `HighTrafficMode::change()`/`restore()`/`HighTrafficSafety::detach()`, and tests. Uncertainty: the host's `mod_headers` (the pages folder already relies on it inside `<IfModule>`).

### Option B: Write fingerprinted copies into `public/assets/` on every deploy

**Estimate: 0.4**

Same regex correction, plus a deploy step that writes each fingerprinted file next to its source (`.gitignore` already ignores such names), so Apache serves them in normal mode too.

- Pros: cheap assets all the time, not only in high-traffic mode.
- Cons: a new deploy step and cleanup of old copies; a copy missing for any reason still falls back to PHP; more files in the public folder; overlaps with what high-traffic mode already does.

### Option C: Correct the regex only

**Estimate: 0.05**

Fix the condition (with the same extension allowlist) and document that fingerprinted assets still go through PHP.

- Pros: tiny; the comment becomes true.
- Cons: no measurable effect today (0 of 34 references on the board are plain files).

## Recommendation

**Option A.** It answers the question as asked: high-traffic mode would cover `/assets/` too, using copies it already keeps and verifies. B is worth revisiting if normal-mode asset cost becomes a problem on its own.

## Risks for every option

- **Deploy and the server's local `.htaccess` edit.** Deploys set the edit aside and put it back (`AppUpdate`, `git stash`/`git stash pop`). A change near the same lines could conflict there. Step 2 needs to know what the local edit is.
- **High-traffic `restore` writes the saved normal rules** (`state/high_traffic/<hash>/normal.htaccess`) back. A saved copy taken before this change would bring back the old condition until the next enable. Step 2 decides whether enable refreshes the saved copy.

## Questions for Step 2

1. What is the server's local edit to `public/.htaccess`?
2. Should Apache-served copies carry exactly PHP's headers (immutable for one year), or something shorter while this is new?

Debug log: [asset_bypass_debug_log.md](./asset_bypass_debug_log.md)

## Approval

2026-11-16 22:24 ET, maintainer: "approved step 1" (Option A). Step 2 questions still open: the server's local `public/.htaccess` edit; header lifetime for Apache-served copies.
