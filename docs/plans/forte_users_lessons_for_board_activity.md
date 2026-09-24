# Lessons from Building Forte Users → Carry Back to Board/Activity

Findings from implementing the Users three-pane view (`docs/plans/archive/forte_users_three_pane_step4_implementation_summary.md`) that are relevant to Board and Activity too. Each item below was actually checked against Board/Activity's current code/rendering, not assumed — see "Verified" notes.

## ✅ Confirmed applicable — worth doing

- [ ] **Adopt the relative-date format for list rows.** Users' list/detail dates now render as "2 days ago" (title="Sep 20, 2026 at 01:28 UTC" tooltip) instead of the full "Sep 20, 2026 at 01:28 UTC" text inline. The closure already exists and is globally available to every template — `$relativeTimestamp($isoTimestamp)` in `src/ForumRewrite/View/TemplateRenderer.php` (added Stage 19, alongside the existing `$timestamp`). Swapping it in is a template-only change:
  - Board: `templates/partials/paned_board_thread_list.php` line ~77 (`<span class="paned-list-date">`)
  - Activity: `templates/partials/paned_activity_item_row.php` line ~25, `paned_activity_commit_row.php` line ~27
  - Leave `$timestamp` (full format) alone everywhere it's already used — the new closure is additive, not a replacement of existing behavior. Decide per-view whether content-pane headers should also switch, or stay full-format for precision.

- [ ] **Fix the narrow-width (400px) column-squeeze bug.** Confirmed present in both, not just Users:
  - Board's Subject column collapses to **16px** at a 400px viewport width ("thanks" truncates to "tl").
  - Activity's Label column collapses to **31px** at the same width.
  - Reproduce with a headless-browser check: load the page at `viewport: {width: 400, height: 800}` and read `.paned-list-subject`'s `getBoundingClientRect().width`.
  - Root cause: `.paned-list-subject { flex: 1; min-width: 0; }` lets the flexible column shrink to nothing instead of the row overflowing, while the fixed-width columns (Board: From 9rem + Date 13rem + Replies 5rem = 27rem; Activity similar) never shrink.
  - Fix already built and verified for Users (`public/assets/forte.css`, Stage 20 in the Users summary): give the list pane its own `overflow-x: auto` plus a `min-width` floor on `.paned-list-head`/`.paned-list-row`, scoped to that view only, so it scrolls internally instead of squeezing. Confirmed zero effect at desktop width and zero page-level horizontal scroll.
  - **Not a copy-paste**: Board's and Activity's fixed-column widths differ from Users' (and from each other), so each view needs its own tuned `min-width` floor, not the literal `34rem` Users uses. Also worth considering whether to lift this into the *shared*, unscoped `.paned-list-pane`/`.paned-list-head`/`.paned-list-row` rules now that it's confirmed common to all three views, rather than three separately-scoped copies — a real design decision, not just mechanical repetition.

## ❌ Checked, not applicable — don't re-investigate these

- **Raw post/thread ID shown instead of a title.** This was a real bug in the new Users detail pane (Stage 16), inherited from the older `forte_username.php` full-profile page it was modeled on. Verified: Board's `paned_board_thread_list.php` already uses `$threadTitle($thread)` correctly, and Activity's rows use `label`/`kind` fields that are native to activity records (not raw post IDs) — neither has this bug.
- **Redundant "by {username}" attribution.** Removed from Users' detail pane (Stage 17) because every row on that page is already known to be authored by the one selected user. Doesn't apply to Board or Activity — both show content from many different authors, where attribution is the actual point.

## 📝 Related, but not Board/Activity — separate follow-up if wanted

- `templates/pages/forte_username.php` (the older, non-"paned" full profile page at `/forte/user/{username}`) has **both** issues found and fixed during this feature:
  1. The same raw-post-ID-as-title bug (already flagged in the Stage 16 summary).
  2. The same double-fetch inefficiency fixed in Stage 18: it calls `countVisibleAuthoredRows()` (which internally re-fetches the full thread/post list) *and* separately fetches the same lists again, instead of fetching once and using `count()`. Confirmed via `grep` — `Application.php` lines ~1097-1100 still have the old pattern; the Users detail endpoint (~line 1543) is the only place already fixed.
