# Step 3: Development Plan — Merge Same-Author Reply Chains on Thread Pages

## Stage 1
- Goal: compute and mark the `continuation` flag server-side, with zero visual change yet.
- Dependencies: none.
- Expected changes:
  - `templates/pages/thread.php`: while iterating posts (root, then `$replyPosts` in order), track the previous post and compute a boolean continuation flag per post using a named constant (e.g. `CONTINUATION_WINDOW_SECONDS = 900`); pass the flag + previous post's time/author into each partial's context.
  - Conceptual helper: `isContinuation(array $previousPost, array $post, int $windowSeconds): bool` — true when same author, time delta ≤ window, and neither post is agent-authored (`author_label === 'reply-agent'`).
  - `templates/partials/post_card.php`: add `continuation` class, `data-time`, `data-author` attributes to the `<article>` when flagged.
  - `templates/partials/thread_root_card.php`: add `data-author` attribute (root post itself is never a continuation, but is the "previous post" for the first reply's comparison).
- Verification approach: render `thread-20260927234240-a72307ac` and inspect raw HTML — confirm the 6 guest replies are flagged, the trailing `reply-agent` post is not, and a post following the agent reply (even same guest author, within window) is not flagged since the immediately-previous post is agent-authored.
- Risks or open questions:
  - Confirm "previous post" means immediately-preceding post in thread order, not "previous post by the same author" (a gap post from someone else should break the chain).
  - Confirm behavior when the first reply immediately follows the root (root counts as "previous" for continuation comparison).
- Canonical components/API contracts touched: `templates/pages/thread.php`, `templates/partials/post_card.php`, `templates/partials/thread_root_card.php`.

## Stage 2
- Goal: merge a run of continuation cards into one visual block via CSS only.
- Dependencies: Stage 1 (class/data attributes must exist).
- Expected changes:
  - `public/assets/site.css`: adjust `.stack` spacing so a card followed by `.continuation` loses its bottom border/margin/rounded bottom corners, and `.continuation` loses its top margin/rounded top corners and gets a faint dashed top separator; hide `.continuation > .meta`; generalize `.post-card-permalink` hover/focus visibility to every post card.
  - `content-interactions.css` (or `site.css`): hover/focus-reveal rule for `.post-card-actions` on every card in a run except the last (`:has(+ .continuation)`), shown on `:hover`/`:focus-within`.
  - Hover timestamp: `.continuation::before { content: attr(data-time) }` in the left gutter, hidden by default, shown on hover/focus.
- Verification approach: manual browser check of the acceptance-check thread in light and dark theme, and at phone width — merged run looks like one card; hovering a continuation reveals its time + its own action row; permalinks fade in on hover.
- Risks or open questions:
  - `:has()` selector support — confirm acceptable across the browsers this project targets.
  - Touch devices have no `:hover` — plan a tap/focus fallback (covered in Stage 4) rather than solving it here.
- Canonical components/API contracts touched: `public/assets/site.css`, `public/assets/content-interactions.css`.

## Stage 3
- Goal: collapse the root card's header to one meta line and reinstate a continuation-aware reply count.
- Dependencies: Stage 1 (need the flag to count true replies).
- Expected changes:
  - `templates/pages/thread.php`: compute a "true reply count" = count of `$replyPosts` where continuation flag is false; pass it into the root card's context (replacing reliance on the raw `thread.reply_count`, which counts continuations too).
  - `templates/partials/thread_root_card.php`: merge the existing byline / labels / agent-marker `<p class="meta">` lines into a single combined line, appending the true reply count; title-duplication handling (already implemented) stays as-is and is not touched.
  - Existing title/body dedup logic and body rendering untouched.
- Verification approach: render the acceptance-check thread and confirm one meta line reading roughly "guest · Sep 27, 23:42 · like · 1 reply" (exact wording finalized during implementation), with no duplicated first line of body text.
- Risks or open questions:
  - A prior slice (`thread_root_card_remove_reply_count`) deliberately removed the reply-count line and likely has test assertions expecting its absence — those need to be located and updated, not just added to.
  - Confirm board/tag list pages (`thread_card.php`) are unaffected, per Step 2 scope.
- Canonical components/API contracts touched: `templates/partials/thread_root_card.php`, `templates/pages/thread.php`, any existing test assertions on root-card meta content.

## Stage 4
- Goal: cross-theme and responsive/touch pass so the merge holds up everywhere, per the acceptance check.
- Dependencies: Stage 2, Stage 3.
- Expected changes:
  - Audit and adjust theme override files (`theme-word97.css`, `theme-sticker.css`, `theme-arena.css`, etc.) wherever they re-declare `.card`/`.post-card`/`.stack` in ways that would fight the Stage 2 merge rules.
  - Add a touch-reachable way to reveal a mid-run card's action row (e.g. tap-to-toggle or a small "⋯" affordance), CSS-first; confirm whether a minimal JS enhancement is acceptable or must stay CSS-only (open question below).
- Verification approach: manual check at phone width and with touch/no-hover emulation, in each theme, in light and dark; confirm a deep link to a continuation's anchor still scrolls to and highlights it correctly under the new styles.
- Risks or open questions:
  - Whether "no client-side JS for grouping" (Stage 1's constraint) also forbids a small JS-based touch-reveal affordance, or only forbids JS-driven grouping logic — needs a decision before this stage starts.
  - Highlight style (`:target` or similar) for a merged continuation may need a touch-up now that borders/margins around it have changed.
- Canonical components/API contracts touched: theme CSS files under `public/assets/`.

## Stage 5
- Goal: add automated regression coverage for the new behavior.
- Dependencies: Stages 1–3.
- Expected changes:
  - New/updated cases in `tests/LocalAppSmokeTest.php`: continuation flag correctness (author match, window boundary, agent-authored break), true-reply-count computation, merged root-card meta content, and that a continuation's permalink/anchor still resolves and highlights.
  - Reuse or extend existing multi-reply fixture data (timestamps within/outside the window) rather than introducing a new fixture format.
- Verification approach: `php tests/run.php` full suite — confirm no new failures beyond the existing tracked-pre-existing baseline.
- Risks or open questions:
  - Existing fixture timestamps may not currently exercise both "within window" and "outside window" cases; may need small fixture additions.
- Canonical components/API contracts touched: `tests/LocalAppSmokeTest.php`, fixture repository used by the test suite.
