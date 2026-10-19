> **Feature plan:** [Step 1](./retracted_posts_hidden_from_listings_step1_solution_assessment.md) · [Step 2](./retracted_posts_hidden_from_listings_step2_feature_description.md) · [Step 3](./retracted_posts_hidden_from_listings_step3_development_plan.md) · [Step 4](./retracted_posts_hidden_from_listings_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- Entry: a reader opens a board, tag, API text, author profile, or Activity listing.
- Outcome: retracted threads and posts are absent from those listings, and counts, paging and cursors match the visible rows.
- Recovery: permalinks and thread pages for retracted content are unchanged and still show the retraction notice; canonical records are untouched, so nothing is lost.
- Deployment/external verification: none beyond the existing read-model rebuild path; no schema change, so no migration.
- Release condition: new per-listing tests and the existing suite pass, and a manual check on a retracted thread confirms it is gone from listings but present at its permalink.

## Key Risks
- **A listing is missed.** Impact: retracted rows remain visible. Validation: Stage 1 inventory of `fetchThreads`/`boardRowsSql` callers (board, tags, Forte, API text, Qdb, editable listing) plus a test per surface. Mitigation: one shared rule where queries are shared.
- **Counts or cursors drift.** Impact: wrong totals or skipped Activity pages. Validation: tests with a retracted row mid-page. Mitigation: filter in SQL, and have totals use the same clause as rows.
- **Retracted root with live replies.** Impact: replies become unreachable from listings. Validation: Stage 1 test confirms the thread permalink still renders. Mitigation: accepted behavior per Step 2.
- **Dead notice branches.** Impact: listing templates keep unused retraction-notice code. Validation: Stage 4 grep. Mitigation: remove only if unused; keep for permalink use.

## Stage 1
- Goal: hide retracted thread roots from every board-style thread listing.
- Dependencies: none.
- Expected changes: add the retraction condition to `ThreadRepository::boardRowsSql()` (covers `fetchThreads`, board, tags, Forte board, API text, Qdb and the editable `BoardListingQuery`); `byId` stays unfiltered.
- Verification approach: tests with a retracted thread present on board, tags page, per-tag page and API text listing; permalink still returns the notice.
- Risks or open questions:
  - Impact: callers that expect retracted rows (for example Qdb eligibility) change behavior.
  - Early warning / validation: read each of the six caller groups before editing.
  - Mitigation: if a caller needs retracted rows, give it an explicit unfiltered path.
- Canonical components/API contracts touched: `ThreadRepository::boardRowsSql`, `ThreadRepository::fetchThreads`, `ThreadRepository::byId` (unchanged).

## Stage 2
- Goal: hide retracted threads and posts from author profile lists and their counts.
- Dependencies: Stage 1 pattern.
- Expected changes: add the retraction condition to `AuthoredContentRepository::visibleThreads` and `visiblePosts`; `countVisible` follows since it counts their results.
- Verification approach: tests on the profile page and Forte profile and user-detail API with retracted thread and reply; counts equal visible rows.
- Risks or open questions:
  - Impact: authors lose sight of their retracted posts in their own profile.
  - Early warning / validation: confirm this matches Step 2 (retracted work leaves author lists).
  - Mitigation: none needed; retracting user still sees it by permalink.
- Canonical components/API contracts touched: `AuthoredContentRepository`, `ProfilePageController`, `ForteProfileController`, `ForteContentAndUserDetailApiController` (consumers only).

## Stage 3
- Goal: hide retracted posts from the Activity content view and keep totals and cursors consistent.
- Dependencies: none (independent of Stages 1-2).
- Expected changes: extend the `content` view clause in `ActivityService::activityViewClause` with the retraction condition on the joined post; verify the row query, `countActivityViewTotal` and cursor paging all use that clause.
- Verification approach: tests with a retracted post between two live ones across a page boundary; total equals visible rows. Other Activity views (all, identity, security) are left unchanged.
- Risks or open questions:
  - Impact: Activity rows for non-post kinds (approvals, commits) have no joined post.
  - Early warning / validation: condition must pass when `activity.post_id` is null.
  - Mitigation: mirror the existing `post_id IS NULL OR ...` guard.
- Canonical components/API contracts touched: `ActivityService::activityViewClause`, `fetchActivityRows`, `countActivityViewTotal`.

## Stage 4
- Goal: confirm the whole slice end to end and clean up.
- Dependencies: Stages 1-3.
- Expected changes: add a regression test that retracted content stays reachable by permalink; remove listing-only retraction-notice branches that are now unused (keep permalink ones); record any removed UI text in `docs/plans/ui_copy_audit_checklist.md`.
- Verification approach: full PHP test suite; manual check on a local instance with a retracted thread across board, tags, profile, Activity and permalink.
- Risks or open questions:
  - Impact: removing a template branch still used by another page.
  - Early warning / validation: grep for each partial's callers first.
  - Mitigation: leave the branch if any caller remains.
- Canonical components/API contracts touched: `templates/partials/thread_card.php`, `thread_root_card.php`, `retracted_post_notice.php`, `RetractedPostNotice` (verify only unless unused).
