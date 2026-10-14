> **Feature plan:** [Step 1](./repo_cleanup_step1_solution_assessment.md) · [Step 2](./repo_cleanup_step2_feature_description.md) · [Step 3](./repo_cleanup_step3_development_plan.md) · [Step 4](./repo_cleanup_step4_implementation_summary.md)

Debug mode: enabled

# Repository cleanup

## Problem
The GitHub page of the repository shows maintainer working files (HN drafts, checklists, case-study candidates) beside the product files, and the README's repository map lists only three of them. A first-time visitor cannot quickly tell what Railspike is made of. Step 1 selected Option C: prune aggressively.

## User stories
- As a first-time visitor, I want the root and `docs/` to hold only files with an obvious purpose so that the repository looks clean and I find the README and workflow quickly.
- As the maintainer, I want finished working drafts removed so that live work is not confused with leftovers.
- As the maintainer, I want every remaining file listed in one place so that nothing is orphaned.
- As an agent following Railspike, I want no link or script to break so that instructions stay trustworthy.

## Core requirements
- Move `HN_POST_DRAFT.md`, `HN_WRITEUP_READINESS_CHECKLIST.md`, `docs/case-study-candidates.md`, and `docs/positioning-checklist.md` out of the repository into a new directory `~/railspike-archive/`, with no `rm`; recovery is that directory or Git history.
- Repair every live reference in the same change: backlog items that exist only for a removed file are dropped, and links elsewhere are removed or rewritten to stand alone.
- Keep the README's repository map complete for what remains; keep the workflow files, the three link checkers, and `docs/plans/` with its debug logs.
- Never delete untracked files (`railspike.txt`, `temp/`, `.idea/`): they are invisible on GitHub and unrecoverable.

## Delivery scope
**Work type:** documentation-only.

**Allowed files:** the four moves above (the destination `~/railspike-archive/` is outside the repository and is created for this feature only); `README.md` (repository map), `BACKLOG.md`, `docs/RELEASES.md` and `CONTRIBUTING.md` only if they link a removed file; `docs/plans/README.md`; and this feature's plans, summary, and debug log. Audience: first-time visitors and the maintainer. Application and runtime changes are not permitted: `index.html`, `railspike-logo.png`, `scripts/`, `docs/workflow/`, `RAILSPIKE.md`, `.gitignore`, and deployment files are unchanged.

## Completion boundary
Document completion: a pruned, link-clean repository merged to `master`. Intended outcome: the root listing shows only README, LICENSE, CONTRIBUTING, `RAILSPIKE.md`, the site files, `docs/`, and `scripts/`. Recovery: copy any moved file back from `~/railspike-archive/` or restore it from Git history. Handoff: maintainer reviews the final root and `docs/` listing, then merges.

## Risks
- **Open backlog work loses its reference:** four moved files are cited by open `BACKLOG.md` items (HN post, replacement examples, case studies). Impact: planned work is silently dropped or left with dead links. Validate by listing each citing item before removal; mitigate by recording in Step 3 which items are dropped versus rewritten, for maintainer confirmation.
- **Historical plans mention removed files:** two approved plan files cite them as code spans, not links. Impact: low, since link checkers ignore them and plan text must stay unaltered. Validate with all three checkers; mitigate by leaving those mentions as history.
- **Only tracked files affect the visitor view:** pruning untracked material does nothing for the GitHub page. Impact: wasted risk. Mitigate by leaving untracked files alone.
- **Pending maintainer edit:** `BACKLOG.md` has an uncommitted whitespace fix. Impact: overwritten or swept into an unrelated commit. Mitigate by building on the working copy and committing it with this feature.

## Shared component inventory
- README "What's in this repo" map: extend; it is the canonical file index.
- `docs/plans/README.md`: extend with this feature's entry; remains the plan index.
- `BACKLOG.md`: edit only the items that cite removed files and this item's checkbox.
- New index files or tooling: not needed.

## Simple user flow
1. A visitor opens the GitHub repository page.
2. The root listing shows only product and site files.
3. The README map names each remaining file and directory.
4. Every link in the Markdown resolves.

## Success criteria
- The four named files no longer exist in the repository on `master`, the root contains no other drafts or checklists, and all four exist byte-identical in `~/railspike-archive/`.
- All three link checkers report no broken local links, and `git diff --check` passes.
- Every tracked root file and `docs/*.md` file appears in the README map.
- No `BACKLOG.md` item links a removed file.
- `railspike.txt` and `temp/` are byte-identical before and after, and `docs/plans/` changes only by the new index entry and this feature's artifacts.

## Revision during Step 4 (pending approval)
The maintainer's direction after Stage 1 supersedes the items below; everything else stands.
- **Core requirement 1:** move only the two root files, `HN_POST_DRAFT.md` and `HN_WRITEUP_READINESS_CHECKLIST.md`, into the new directory `docs/launch/`, keeping their names. `docs/case-study-candidates.md` and `docs/positioning-checklist.md` already sit outside the root and stay. No file leaves the repository; `~/railspike-archive/` is not used.
- **Links:** all references are repaired, including `BACKLOG.md`, `docs/case-study-candidates.md` and the moved files' own relative links; the earlier "leave the backlog file" direction is superseded.
- **Allowed files:** the two moves, `BACKLOG.md` (links and pending whitespace fix), `README.md` map, `docs/plans/README.md`, and this feature's artifacts.
- **Success criteria:** the root contains no HN drafts or checklists, the two files exist unmodified under `docs/launch/`, no Markdown link is broken, and `railspike.txt` and `temp/` are byte-identical.
- **Risks:** dropped backlog items no longer apply since nothing is removed; broken relative links inside the moved files are the new risk, validated by the link checkers.

## Revision after Step 4 handoff (pending approval)
The maintainer asked to move `BACKLOG.md` under `docs/` and to organize the `docs/` directory.
- **Target layout:** `docs/failure-modes.md` (user-facing, stays), `docs/workflow/` and `docs/plans/` (unchanged), and a new `docs/maintainers/` holding `BACKLOG.md`, `RELEASES.md`, `positioning-checklist.md`, `case-study-candidates.md`, and `launch/` (the two HN files, moved from `docs/launch/`). A short `docs/README.md` indexes the four entries.
- **Links to repair (found by search):** the moved files' own relative links, `README.md` (backlog link and repository map), and `docs/plans/README.md` wording if needed. `RAILSPIKE.md`, `docs/workflow/`, `scripts/`, `index.html`, and `CONTRIBUTING.md` contain no references and stay unchanged; historical plans mention these files only as text.
- **Allowed files:** the moves above, the new `docs/README.md`, `README.md`, `docs/plans/README.md`, and this feature's artifacts. Nothing else; content of moved files changes only in link paths.
- **Success criteria:** root has no `BACKLOG.md`; every `docs/` entry is listed in `docs/README.md` and the README map; all three link checkers pass.
- **Risk:** external links to the old paths on GitHub (the HN post or the website) would break. Low impact; none exist in the repository, and moved files remain in Git history.
