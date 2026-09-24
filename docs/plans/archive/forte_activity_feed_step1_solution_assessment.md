# Forte Activity Feed — Step 1: Solution Assessment

## Problem
Forte has no view of recent activity across the board; classic's `/activity/` is actually a technical/administrative event log (source file paths, commit SHAs, signature-verification status, identity/bootstrap/approval/feature-flag events across 5 filtered views) — not a simple "what's new" feed, so porting it as-is doesn't obviously fit Forte's reader-focused tone.

## Option A: Full technical port
All 5 classic views (All/Content/Identity/Bootstraps/Approvals) plus source-path/commit/signature metadata, in paned chrome.
- Pros: complete parity with classic, nothing left out.
- Cons: identity/bootstrap/approval events and signature metadata are operator/admin-facing, not reader-facing — out of tone with every other Forte page shipped so far (each one has deliberately dropped admin actions, e.g. "Approve user" excluded from `forte_profiles`); would be the first Forte page to surface raw commit SHAs and file paths.

## Option B: Reader-focused feed only
Reuse the existing `content` view's query filter unchanged (already excludes identity/bootstrap/hidden-post noise — it's the exact subset a reader cares about) and render a flat list of new threads/replies, each linking into the board via the same `/forte?selected=...&created_post_id=...` pattern `forte_post_permalink` already established. Drop source-path/commit/signature metadata and the other 4 view tabs entirely.
- Pros: reuses an already-correct, already-filtered query verbatim — zero new backend logic; matches the tone and scope-discipline of every Forte page shipped so far; the one genuinely new value this adds over the board itself is showing individual reply-level events (not just re-sorted threads), reusing the permalink mechanism.
- Cons: readers who want the technical/admin views have no Forte-native way to reach them (classic's own `/activity/` still exists for that, just not linked from Forte — linking it would reopen the still-on-hold nav-bridge question, so deliberately not doing that here).

## Option C: Embed activity inline in the board instead of a separate page
A new board sub-view or folder-tree entry rather than a standalone page.
- Pros: none identified over Option B.
- Cons: no precedent — every prior addition (profiles, directory) shipped as its own standalone Forte page, not a new board mode; more integration work for a page that's naturally a simple flat list.

## Recommendation
**Option B.** It reuses an already-correct, already-filtered query with zero new backend logic, and matches this project's consistent pattern of leaving admin/technical concerns out of Forte's reader-facing surfaces.

## Decision
**A different shape than any option above, per explicit user direction**: a genuine three-pane paned view mirroring `forte_board.php`'s own structure — left pane is activity-type filters (breadth matching classic's All/Content/Identity/Bootstraps/Approvals, i.e. Option A's scope, not Option B's single reader-only list), top-right pane is the activity list for the selected type, bottom pane is the selected item's detail. Reached via a new "Activity" toolbar button (same icon-button pattern as the just-shipped "Users" button), not folded into the existing board.

The detail pane **includes classic's technical metadata** (source file path, commit SHA, signature-verification status) — confirmed directly rather than defaulting to the reader-only simplification Option B/my recommendation assumed, since the filter breadth already leans toward the same admin-facing audience classic's Identity/Bootstraps/Approvals views serve.

This supersedes the Option A/B framing above: it takes Option A's full breadth and metadata, restructured into a three-pane paned layout instead of classic's stacked-card page — closer to how `forte_board.php` itself works (folder tree + list + content pane) than to any option originally proposed.
