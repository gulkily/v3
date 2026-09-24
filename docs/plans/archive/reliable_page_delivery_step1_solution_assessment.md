# Reliable Page Delivery: Step 1 Solution Assessment

## Problem statement

Cold mobile loads can visibly render before CSS arrives or wait behind the large OpenPGP bundle before basic controls work, while the current production static-build process can mix release states and hold the live SQLite write lock long enough to stall page requests.

## Option A — CSS-only one-shot

**In plain language:** Inline a small amount of styling for the first screen and move the theme control ahead of cryptography, then leave caching and static publishing unchanged.

Pros:
- Fastest route to improving the mobile first paint.
- Small, reversible template change.

Cons:
- Does not prevent stale HTML/new-script mismatches after deployment or live static builds from blocking requests.

## Option B — Staged reliable-page-delivery work

**In plain language:** Start downloading OpenPGP immediately, but make the visible page and basic controls interactive before evaluating the large library; then separately make HTML cache-safe and static builds publish only after they are fully ready.

Pros:
- Removes the no-CSS first paint without inlining the whole stylesheet.
- Lets theme controls work before the OpenPGP bundle is evaluated, while retaining an eager download so authentication and signing can use it as soon as the initial interactive work is complete.
- Addresses the reload-loop risk and production build outage under one release-safety goal.
- Each stage is independently testable, deployable, and reversible.

Cons:
- Requires planning and verification across templates, HTTP caching, and the build workflow.
- The safe publication stage is larger than the visual CSS improvement.

## Option C — Inline the full stylesheet into every page

**In plain language:** Put all of `site.css` directly into each HTML response.

Pros:
- Eliminates the separate stylesheet request on a cold page load.
- Simple mental model.

Cons:
- Repeats roughly 15 KB of compressed CSS in every HTML page instead of reusing the browser cache.
- Makes generated artifacts larger and does not address deployment cache coherence or live build locking.

## Recommendation

Choose **Option B**. Treat the items as one page-delivery reliability effort, but release them in order: critical CSS first; cache correctness second; safe offline build/publish last. Do not send the selected theme as a cookie: the existing early `localStorage` theme selection can activate inline critical rules for every theme without per-user HTML variants.
