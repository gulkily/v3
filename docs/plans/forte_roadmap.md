# Forte Interface — Feature Roadmap

Consolidated backlog of what's still missing, deliberately excluded, or rough around the edges in Forte, based on a gap analysis against the classic UI's full route table (`src/ForumRewrite/Application.php`) and every prior Forte planning doc in `docs/plans/`. This is a menu to pull from, not a plan — each item below still needs its own pass through the FDP steps when picked up. The ordered execution list lives in `forte_fdp_cycles.md`; this doc tracks the gap analysis itself.

## Completed

- **Signed identity / browser-key authorship (board view).** `forte_identity_signing` (Step 1-4, done) wired `lazy_compose_signing.js`/`browser_signing.js` into Forte's board-view composer: signed replies now carry real authorship instead of always posting as anonymous "guest," the identity-status feedback element shows progress, and a successful signed submission lands back in the board view (tag/selection/highlight preserved) instead of redirecting to classic. **Deliberately scoped to the board view only** — the single-thread Forte reader (`/threads/{id}/forte`) was left untouched because it's slated for deprecation next (`forte_deprecate_single_thread_view`), so no per-page-type signing work was done there. See `forte_identity_signing_step4_implementation_summary.md`.

## A. Missing capabilities (candidate features)

- **Compose a new thread.** The toolbar's "New" button exists in both Forte views but is permanently `disabled`, wired to nothing. Classic uses a separate canonical form (`thread_compose_form.php`, distinct from the `reply_form.php` Forte already reuses) — porting this means adopting a second shared form, not extending the existing one.
- **Reactions & moderation.** Like/Flag buttons, agent-reply requests, Codex handoff workflow, user-approval flow — none of this exists in Forte today.
- **Discovery pages.** `/activity/` (recent activity feed) and `/tags/` (a browsable tag index with shareable per-tag URLs, distinct from the board's filter-only folder tree) have no Forte equivalent. Note: classic itself has no full-text search either, so search isn't a "parity" gap — nothing to match there.
- **Profiles & permalinks.** No profile pages, no user directory, and no single-post permalink (classic's `/posts/{id}`) — Forte can only select a post within its own thread view, with no shareable direct link to one specific post.
- **A way back to the rest of the site.** There is currently no link from Forte to any classic page, and no link from classic pages into Forte-adjacent destinations (about, tools, downloads). This directly conflicts with an explicit non-goal from the original design (see below) — don't add this without a deliberate decision to reverse that choice.

## B. Deliberate non-goals from prior planning — don't "fix" these by accident

These were intentional scope calls, not oversights. Still active unless noted otherwise:

- **No navigation between Forte and classic pages, either direction** — `forte_step2_feature_description.md`. Still true today; directly relevant to the "way back to the rest of the site" item above.
- **No read/unread state** — called out as explicitly out of scope in both `forte_step1_solution_assessment.md` and `forte_step2_feature_description.md`.
- **No custom keyboard shortcuts beyond basic pane selection**, and **no keyboard-driven expand/collapse of nested replies** — `forte_step1_solution_assessment.md` / `forte_keyboard_navigation_step2_feature_description.md`. The single-thread list pane's collapse toggle is still mouse-only. (The board-view half of this is now moot — `forte_board_reply_tree` made board replies always-expanded, no collapse state left.)
- **Desktop-first; mobile/narrow-viewport not an engineered target** — `forte_step2_feature_description.md` and `forte_board_view_step2_feature_description.md` both call this a pragmatic, not engineered, decision.
- **"No reply/compose affordances, no Permalink links"** — `forte_step2_feature_description.md`'s original read-only framing. The reply half is superseded (`forte_reply`/`forte_board_reply` shipped it); the permalink half was never revisited and is still absent — see the permalink item above.

## C. Known rough edges (debt, not new features)

- **New/Refresh toolbar buttons are permanently disabled dead UI** in both Forte views, indefinitely.
- **No empty-state messaging** — a tag filter matching zero threads (or a thread with zero visible posts) renders nothing, no "no results" text. Neither `paned_board_thread_list.php` nor `paned_list_pane.php` has an empty-array branch.
- **No error handling in the composer JS path** — after `forte_board_reply_tree` removed the old lazy-fetch's `.catch()`, there's no error-surfacing left anywhere in `paned_reader.js`/`paned_board_reader.js`. A failed reply submission silently redirects to the *classic* compose-error page (a known, accepted limitation from `forte_reply` Stage 5).
- **Narrow-viewport column squeeze** — `forte_step4_implementation_summary.md` itself flags the Subject column getting "quite narrow" at 420px against fixed-width From/Date columns. Known, unresolved.
- **Uneven accessibility** — folder tree and list rows have real `role`/`aria-selected`/roving-tabindex support, but there's no `aria-live` region anywhere, so screen-reader users get no announcement when a reply posts, a highlight applies, or a filter changes the result count.
- **Dead code:** `TemplateRenderer::renderFragment()` has had its only caller removed (`forte_board_reply_tree`) and was deliberately left in place pending a cleanup decision.
- **No permalink/anchor support on load** — Forte's own post-reply redirect generates a `#post-{id}` hash, but `paned_reader.js` never reads it on page load (only the `created_post_id` query param, present solely right after a fresh submission). A plain shared link like `/threads/{id}/forte#post-{id}` from anywhere else won't auto-select that post.

## Suggested sequencing

1. ~~**Signed authorship**~~ — done, board view only (see Completed above).
2. **Deprecate the single-thread Forte reader** (`forte_deprecate_single_thread_view`) — next up per `forte_fdp_cycles.md` #2. Confirmed low-risk (nothing outside Forte links to `/threads/{id}/forte`, board view is already fully self-contained), and queued right after identity-signing so no later cycle builds more into a view about to be removed.
3. **Nav back to classic / "way back to the rest of the site"** — needs a conscious decision first (it reverses a stated non-goal), not just an implementation pass.
4. Everything else in Section A is additive and can be sequenced by whatever the user values most; each should still go through Step 1 (recommended, given real trade-offs exist for most of these) → 2 → 3 → 4 like every other Forte feature so far. See `forte_fdp_cycles.md` for the full ordered backlog (12 cycles) with per-item scope notes.
