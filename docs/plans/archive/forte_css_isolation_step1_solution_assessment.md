# Forte CSS Isolation Step 1 Solution Assessment

## Problem Statement

Forte's paned-reader styles live inside `site.css` alongside the classic UI's themeable rules, so every `.paned-*` selector must out-specificity or override the site theme to stay visually independent — a pattern that has already caused three separate dark-mode leaks (toolbar/sort/reply-toggle button backgrounds, link color, `color-scheme`) fixed via `.paned-window`-scoped overrides.

## Option A: Extract a dedicated `forte.css`, loaded only on Forte pages

Move the `.paned-*`/`.paned-window` block out of `site.css` into a new `public/assets/forte.css`, and pass it as an additional (or replacement) stylesheet via the existing `renderStandalonePage()` asset-path mechanism Forte pages already use for their JS.

Pros:
- Removes Forte rules from the classic theme's cascade entirely — no competing selector can leak in, so no more specificity patches are needed by construction.
- Uses a mechanism (`renderStandalonePage`'s asset list) already proven for Forte's JS; no new rendering path.
- Smaller `site.css` for the classic UI to reason about; smaller `forte.css` for Forte.

Cons:
- One more asset to fingerprint/serve; classic-UI-wide CSS edits must consider two files instead of one.
- A few generic low-level resets (e.g. `appearance: none` on buttons, box-sizing) would need to be duplicated into `forte.css` rather than inherited from `site.css`'s generic rules, unless `forte.css` is loaded in addition to (not instead of) `site.css`.

## Option B: Keep one stylesheet, enforce isolation by convention

Leave `.paned-*` rules in `site.css`, but adopt a written rule (and maybe a lint/grep check) that every Forte selector must be scoped under `.paned-window` going forward, matching the fix just applied.

Pros:
- No new file, no build/asset-path changes.
- Zero risk of regressing the reset/base rules Forte currently relies on from `site.css`.

Cons:
- Relies on every future edit remembering the convention — the last three bugs happened because it wasn't a rule yet; a written convention with no enforcement is easy to forget again.
- Doesn't reduce the underlying coupling: a global `site.css` rule can still theoretically out-specificity a `.paned-window`-scoped one (e.g. an ID selector or `!important` added later).

## Option C: Full isolation via Shadow DOM or `@layer`

Render Forte's markup inside a shadow root, or wrap `site.css` in a lower `@layer` than `forte.css`, so no author-stylesheet collision is possible regardless of specificity.

Pros:
- Strongest possible guarantee — even an `!important` in `site.css` can't leak in.

Cons:
- Shadow DOM would require restructuring how Forte's templates/JS attach to the DOM (event delegation, `querySelector` scoping) — large, risky change for a CSS problem.
- `@layer` support/ordering is easy to get subtly wrong and harder to reason about than a plain file split; overkill given the leaks so far were all simple specificity gaps, not `!important` fights.

## Recommendation

**Option A** — extract `forte.css`, loaded alongside `site.css` (not replacing it, so Forte keeps the generic low-level resets it already depends on).

Brief justification:
- Directly removes the root cause (shared cascade with the classic theme) rather than continuing to patch each newly-discovered leak, which is what Option B would still require.
- Reuses the existing per-page asset-list mechanism (`renderStandalonePage`) already in place for Forte's JS — no new infrastructure.
- Far lower risk/effort than Option C, and matches the actual bug pattern seen so far (plain specificity gaps, not `!important` or deep architectural conflicts).
