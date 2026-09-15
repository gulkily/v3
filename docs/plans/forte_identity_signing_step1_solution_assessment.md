# Forte Identity Signing — Step 1: Solution Assessment

## Problem
Forte's reply composer never loads the browser-key signing scripts, so every reply posted from Forte is silently anonymous even for readers with a signed identity elsewhere on the site.

## Scope note
The single-thread Forte reader (`/threads/{id}/forte`) is being deprecated separately (its own upcoming FDP cycle), so this feature targets the board view (`/forte`) only — no per-page-type split needed.

## Finding that shapes the options
Classic's own board page (`renderBoard`) already solves this exact problem for this exact page shape: it loads only `lazy_compose_signing.js`, which defers the heavy crypto payload (`openpgp_loader.js` + `browser_signing.js`) until the reader actually focuses a compose field — via the same per-page script-list mechanism Forte's own pages already use for `forte.css`/`paned_reader.js`. It only needs a `data-compose-root` wrapper around the form; `reply_form.php`'s own `data-compose-form`/hidden fields are already fully compatible, since Forte already reuses that exact partial unchanged.

## Option A: Reuse `lazy_compose_signing.js` as-is
- Add it to the board view's script list, add `data-compose-root` to the compose panel wrapper
- Pros: reuses an already-proven, deliberately-chosen pattern verbatim for this exact page shape; board visits that never reply pay zero crypto-lib cost, same trade-off classic already made; only markup/wiring changes, no new JS logic
- Cons: none identified beyond the general cost of any change

## Option B: Load the signing scripts eagerly instead
- Add `openpgp_loader.js` + `browser_signing.js` directly, like classic's thread page does
- Pros: no lazy-loader indirection to reason about
- Cons: pays the full crypto-lib payload on every board visit, even the large majority that never reply — the exact cost classic's own board page deliberately avoids

## Option C: Build a Forte-native signing indicator from scratch
- Pros: could be styled more tightly to Forte's chrome from day one
- Cons: forks a large (3045-line), security-sensitive cryptographic implementation for no functional gain over A/B — directly against this project's preference to reuse shared components over forking

## Recommendation
**Option A.** It costs nothing beyond markup/wiring, invents no new pattern, and matches the exact judgment call classic already made for this exact page shape.

## Addendum (found after approval, doesn't change the choice)
`browser_signing.js`'s signed-reply path bypasses `/compose/reply` entirely (posts straight to `/api/create_reply` via `fetch`) and unconditionally navigates to classic's `/threads/{id}` afterward, with no awareness of `return_to`. Anonymous submissions are unaffected (unchanged `form.submit()` path). Per explicit decision: this shared script will get a small, surgical patch so its post-signed-reply navigation respects `return_to` when present, keeping Forte readers in Forte the same way anonymous submissions already do. Option A is still correct — this only corrects its "no new JS logic" characterization; the loading-strategy choice itself is unaffected.
