> **Feature plan:** [Step 1](./repo_cleanup_step1_solution_assessment.md) · [Step 2](./repo_cleanup_step2_feature_description.md) · [Step 3](./repo_cleanup_step3_development_plan.md) · [Step 4](./repo_cleanup_step4_implementation_summary.md)

Debug mode: enabled

## Original Query
Clean up the repo: remove unused files, consolidate or archive old drafts, and ensure the remaining files are clearly organized and documented.

## Understood Intent
This is the open cleanup item in `BACKLOG.md`. Evidence so far: few tracked files are truly unused (the HN drafts, positioning checklist, and case-study candidates are still referenced by open backlog items), the README's repository map lists three files, and `railspike.txt` (homepage source), `temp/`, and `.idea/` are untracked or ignored. Debug mode was requested by the user and is recorded as enabled.

## Problem
Maintainer working files, finished plans, and untracked local material sit beside the product files with no single place that says what each one is.

## Option A — Document only
Leave every file where it is and extend the README's repository map and `docs/plans/README.md` so each root file, `docs/` file, and untracked local item has a stated purpose and status.

- **Pros:** No links or history change; nothing is lost; reversible in one commit.
- **Cons:** Does not reduce clutter or satisfy "remove" and "archive"; finished drafts still sit at the root.

## Option B — Archive finished drafts, then document
Move only material that is verifiably finished or superseded into `docs/archive/` with a short index, repair references, and extend the repository map. Open backlog items and live checklists stay in place; nothing is deleted.

- **Pros:** Clears the root while keeping everything reviewable and linkable; nothing is irrecoverable; stays inside documentation-only scope.
- **Cons:** Candidates are few, so the visible effect may be small; links in plans and logs that cite moved paths need care.

## Option C — Prune aggressively
Delete finished drafts and checklists outright, relying on Git history, remove other stale files, and document what remains.

- **Pros:** Smallest and cleanest tree; matches "remove unused files" literally.
- **Cons:** Several candidates are still referenced by open backlog items; untracked files cannot be recovered; plan and log history loses cited files.

## Original Recommendation (superseded)
**Option B, with Option A's map as its documentation half.** It satisfies archive, consolidate, and document while keeping every file recoverable, and it never deletes untracked material. Handle `railspike.txt`, `temp/`, and `.idea/` as maintainer decisions recorded in the map, not removals. It is one releasable slice: a documented, link-clean layout merged to `master`, verified by the three link checkers.

## Revised Direction
The user chose **Option C, prune aggressively**, to make the GitHub repository page look clean and neat to a first-time visitor (revision, not yet approval). Rechecked against that goal:

- **Visitor view:** only tracked files appear on GitHub. The first-visit surface is the root listing and `README.md`, so the root should hold only the README, LICENSE, CONTRIBUTING, `RAILSPIKE.md`, the site (`index.html`, logo, `.nojekyll`), `docs/`, and `scripts/`.
- **Pruning candidates to confirm in Step 2:** `HN_POST_DRAFT.md`, `HN_WRITEUP_READINESS_CHECKLIST.md`, `docs/case-study-candidates.md`, and `docs/positioning-checklist.md`. Each is cited by open `BACKLOG.md` items; removal means rewriting or dropping those items and repairing links, with Git history as the recovery path.
- **Unchanged constraints:** untracked files (`railspike.txt`, `temp/`, `.idea/`) are not visible on GitHub and cannot be recovered, so they are not deleted without an explicit instruction. The three link checkers and workflow files stay.
- **Open decision:** whether completed plans under `docs/plans/` (29 files) are also pruned or only indexed; they are outside the root view and are the project's decision record, so the default is to keep them.
- **Slice viability:** unchanged: a pruned, documented, link-clean layout merged to `master`, verified by the three link checkers.

## Further Revised Direction (during Step 4, pending approval)
After Stage 1 the maintainer replaced the "move out of the repository" part of Option C: "Move them somewhere not in the top-level directory and fix the links." Files are kept in the repository under a subdirectory, not pruned or moved to `~`, and `BACKLOG.md` is edited to repair links. This is Option B's archive idea with Option C's goal (a clean root); nothing is removed.
