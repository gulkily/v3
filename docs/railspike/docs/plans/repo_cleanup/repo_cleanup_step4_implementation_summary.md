> **Feature plan:** [Step 1](./repo_cleanup_step1_solution_assessment.md) · [Step 2](./repo_cleanup_step2_feature_description.md) · [Step 3](./repo_cleanup_step3_development_plan.md) · [Step 4](./repo_cleanup_step4_implementation_summary.md)

Debug mode: enabled

## Branch and planning commit
- Branch `feature/repo-cleanup`; planning commit `7fffb0e` contains Steps 1–3, this feature's debug log, and the `docs/plans/README.md` entry.
- At `Approved Step 3` the maintainer said "You can leave the backlog file": `BACKLOG.md` is not edited and its pending whitespace fix stays uncommitted and unstaged (see the Step 3 approval note).

## Stage 1 - Baseline evidence and reference check
- Changes:
  - No repository file changed besides this summary; `BACKLOG.md` repairs were removed from scope.
  - `docs/RELEASES.md`, `CONTRIBUTING.md`, `README.md`, `index.html`, and `scripts/` contain no references to the four files.
- Verification:
  - SHA-256 baselines:
    - `HN_POST_DRAFT.md` `66293b50…89e2d`
    - `HN_WRITEUP_READINESS_CHECKLIST.md` `f3a64dff…54b07`
    - `docs/case-study-candidates.md` `f33adba8…dc6c3`
    - `docs/positioning-checklist.md` `56a37397…08d9`
    - `railspike.txt` `575d95c6…e37b`
    - `temp/` (hash of per-file hashes) `db5080d1…1d`
  - `~/railspike-archive/` does not exist yet, so no name conflict.
  - Runtime, UI, deployment, and migration checks: not applicable (documentation-only).
- Notes:
  - **Open for the maintainer:** `BACKLOG.md` lines 3, 12, and 14 link three of the four files. Moving them leaves those links dead and the link checkers fail. Stage 2 is held until the maintainer chooses: edit those lines after all, keep the cited files in place, or accept the dead links.

## Revision before Stage 2
- The maintainer redirected the work after Stage 1: move the two root HN files into `docs/launch/` (not to `~`), and fix links including `BACKLOG.md`. Recorded as revisions in Steps 1–3; `Approved Step 3` was given and taken as covering the Step 2 revision, which Step 3 restates.
- Stage 1's "Open for the maintainer" note is resolved: `BACKLOG.md` is edited.

## Stage 2 - Move and repair links
- Changes:
  - `HN_POST_DRAFT.md` and `HN_WRITEUP_READINESS_CHECKLIST.md` moved with `mv` to `docs/launch/`; no `rm`, no copies left at the root.
  - Relative links inside the checklist changed from `./docs/…` to `../…`; `BACKLOG.md` links to the two files point to `docs/launch/`; the pending whitespace fix is included.
- Verification:
  - `HN_POST_DRAFT.md` is byte-identical to the Stage 1 baseline (`66293b50…`). The checklist differs from its baseline only in the link lines listed by diff against `HEAD`, as intended.
  - Python, Perl, and Node link checkers: 52 Markdown files, local links valid. `git diff --check` clean.
  - Runtime, UI, deployment, and migration checks: not applicable.
- Notes:
  - `docs/case-study-candidates.md` and `docs/positioning-checklist.md` were already outside the root and are unchanged.
  - **Correction:** commit `5be94c9` recorded the checklist as deleted because `.git/info/exclude` (local, line 7) ignores `HN_WRITEUP_READINESS_CHECKLIST.md` by bare name, so the moved file was not staged. The file is intact at `docs/launch/`; it was re-added with `git add -f` in the follow-up commit so it stays tracked as before. The exclude entry was left unchanged and is a maintainer decision.

## Stage 3 - Document what remains
- Changes:
  - `README.md` "What's in this repo" map now lists every tracked root file and `docs/` area (workflow, failure modes, releases, plans, launch drafts, positioning files, scripts, site files, backlog, contributing, license).
  - `docs/plans/README.md` entry for this feature reworded to the actual outcome (move to `docs/launch/`, not out of the repository).
- Verification:
  - Every tracked root file and `docs/*.md` file appears in the map (compared with `git ls-files`).
  - Link checkers (Python, Perl, Node) and `bash scripts/check-railspike-git-workflow.sh`: all three report 52 files valid; the workflow fixture passed; `git diff --check` clean.
  - Runtime, UI, deployment, and migration checks: not applicable.
- Notes:
  - The README's debug-mode and update-block markers were not touched; only the map block changed.

## Stage 4 - Release verification
- Changes:
  - None beyond this summary.
- Verification:
  - Root now lists only `.gitignore`, `.nojekyll`, `BACKLOG.md`, `CONTRIBUTING.md`, `LICENSE`, `RAILSPIKE.md`, `README.md`, `docs/`, `index.html`, `railspike-logo.png`, `scripts/`, plus local-only `railspike.txt`, `temp/`, `.idea/`. `docs/` holds `launch/`, `plans/`, `workflow/` and four Markdown files.
  - Changed vs `master`: `BACKLOG.md` and `README.md` edited, the two HN files moved to `docs/launch/`, `docs/plans/README.md` plus this feature's artifacts under `docs/plans/repo_cleanup/`; no other plan or file touched.
  - Hashes: `railspike.txt` `575d95c6…` and `temp/` `db5080d1…` match the Stage 1 baseline; `HN_POST_DRAFT.md` is byte-identical; the checklist differs only in two relative-link lines.
  - Link checkers (Python, Perl, Node): 52 files valid; workflow fixture passed; `git diff --check` clean.
  - Runtime, UI, deployment, and migration checks: not applicable (documentation-only).
- Notes:
  - Commit count on the branch: planning, Stages 1–3, one Stage 2 correction, and this stage; not below `1 + 4`.
  - Open for the maintainer: `.git/info/exclude` ignores `HN_WRITEUP_READINESS_CHECKLIST.md` by name; the tracked file is unaffected but a future re-add needs `-f`. The pending README essay-site items in `BACKLOG.md` are unchanged; the cleanup item itself is left unchecked for the maintainer to tick on merge. `railspike.txt` remains untracked.

## Revision after the Step 4 handoff
- The maintainer asked to move `BACKLOG.md` under `docs/` and organize `docs/`. Recorded as pending revisions in Steps 2–3; `Approved Step 3` was given and taken as covering the Step 2 revision, as before. Layout: `docs/maintainers/` for backlog, releases, positioning, case-study candidates, and `launch/`; `docs/README.md` indexes it.

## Stage 5 - Move files and repair links
- Changes:
  - `git mv`: `BACKLOG.md`, `docs/RELEASES.md`, `docs/positioning-checklist.md`, `docs/case-study-candidates.md` into `docs/maintainers/`; `docs/launch/` files into `docs/maintainers/launch/`. No `rm`.
  - Relative links in the moved backlog and positioning checklist rewritten; the checklist and case-study files needed none.
  - New `docs/README.md` index; the `README.md` backlog link repointed (done here, not Stage 6, so this commit passes the link checkers).
- Verification:
  - Word-level comparison with `HEAD`: the backlog and positioning checklist differ from their previous versions only in link targets; the other four moved files are byte-identical (rename similarity 100%).
  - Link checkers (Python, Perl, Node): 53 files valid; workflow fixture passed; `git diff --check` clean.
  - The excluded checklist name stayed tracked because `git mv` moved a tracked file; `git ls-files docs/maintainers/launch` lists both HN files.
  - Runtime, UI, deployment, and migration checks: not applicable.
- Notes:
  - Allowed-scope check: `RAILSPIKE.md`, `docs/workflow/`, `scripts/`, `index.html`, and `CONTRIBUTING.md` are unchanged.

## Stage 6 - Update the README map and final verification
- Changes:
  - `README.md` "What's in this repo" map rewritten to the new layout (`docs/maintainers/`, `docs/README.md` pointer); `docs/plans/README.md` entry reworded to the actual outcome.
  - Removed the empty `docs/launch/` directory left after the move (`rmdir`, not `rm`).
- Verification:
  - Root: `.gitignore`, `.nojekyll`, `CONTRIBUTING.md`, `LICENSE`, `RAILSPIKE.md`, `README.md`, `docs/`, `index.html`, `railspike-logo.png`, `scripts/`, plus local-only `railspike.txt`, `temp/`, `.idea/`. `docs/`: `README.md`, `failure-modes.md`, `maintainers/`, `plans/`, `workflow/`.
  - Every tracked root file and `docs/` area appears in the README map or `docs/README.md`.
  - Python, Perl, and Node link checkers: 53 files valid; workflow fixture passed; `git diff --check` clean.
  - Hashes: `railspike.txt` `575d95c6…` and `temp/` `db5080d1…` match the Stage 1 baseline; `HN_POST_DRAFT.md` `66293b50…` is unchanged.
  - `docs/plans/` changed only by its index entry and this feature's artifacts; `RAILSPIKE.md`, `docs/workflow/`, `scripts/`, `index.html`, and `CONTRIBUTING.md` are unchanged.
  - Runtime, UI, deployment, and migration checks: not applicable.
- Notes:
  - Open for the maintainer: `.git/info/exclude` still ignores `HN_WRITEUP_READINESS_CHECKLIST.md` by name; the tracked file is unaffected. The "Clean up the repo" item in `docs/maintainers/BACKLOG.md` is left unchecked for the maintainer to tick on merge. `railspike.txt` remains untracked.
