> **Feature plan:** [Step 1](./repo_cleanup_step1_solution_assessment.md) · [Step 2](./repo_cleanup_step2_feature_description.md) · [Step 3](./repo_cleanup_step3_development_plan.md) · [Step 4](./repo_cleanup_step4_implementation_summary.md)

Debug mode: enabled

## Approval note
At `Approved Step 3` the maintainer directed: "You can leave the backlog file." `BACKLOG.md` is therefore **not edited** by this feature, which supersedes its backlog repairs in the Completion Contract, Key Risks, and Stage 1 below; its pending whitespace fix stays uncommitted and unstaged. Consequence: links in `BACKLOG.md` to the four moved files would dead-end, so the Stage 2 move and its link-check pass are held for the maintainer's decision (see the Step 4 summary).

## Completion Contract
- Allowed documentation scope (Step 2): move `HN_POST_DRAFT.md`, `HN_WRITEUP_READINESS_CHECKLIST.md`, `docs/case-study-candidates.md`, `docs/positioning-checklist.md` to `~/railspike-archive/`; edit `README.md` (repository map), `BACKLOG.md`, `docs/plans/README.md`, and `docs/RELEASES.md`/`CONTRIBUTING.md` only if they link a moved file; this feature's plans, summary, and debug log. Nothing else.
- Audience and outcome: a first-time visitor sees a root of only README, LICENSE, CONTRIBUTING, `RAILSPIKE.md`, `BACKLOG.md`, the site files, `docs/`, and `scripts/`, with every file named in the README map.
- Document completion and recovery: all links resolve; moved files can be copied back from `~/railspike-archive/` or restored from Git history.
- Evidence boundary: SHA-256 of moved and untracked files before and after; the three link checkers; `git diff --check`; final directory listings.
- Not applicable: runtime, UI, deployment, and migration checks. Release condition: maintainer reviews the final listing, then merge to `master`.

## Key Risks
- **High risk: dropped backlog work:** open items cite the moved files. Validate in Stage 1 by listing each citing line and its disposition (drop `HN draft` item; rewrite the examples item; remove the intro link) for maintainer confirmation. Mitigation: edit references before moving anything.
- **Irrecoverable loss:** untracked `railspike.txt` and `temp/`. Validate by recording hashes in Stage 1 and re-checking in Stage 4. Mitigation: never `git add -A`, `git clean`, or `rm`; stage named paths only.
- **Sweeping the maintainer's `BACKLOG.md` edit:** the pending whitespace fix is staged only in Stage 1, with this feature.
- **Broken intermediate commits:** moving files before repairing links would fail checks. Mitigation: repair references (Stage 1) before moving (Stage 2).
- **Moved-file corruption or overwrite:** `~/railspike-archive/` may already exist. Validate by inspecting it first; mitigation: stop and ask on any name conflict.

## Stage 1
- Goal: Repair references while the files still exist; record baseline evidence (≤1 hour).
- Dependencies: `Approved Step 3`; feature branch; planning commit with plans organized under `docs/plans/repo_cleanup/`.
- Expected changes: `BACKLOG.md` (keep the pending whitespace fix; drop the HN draft item; drop the positioning-checklist intro link; rewrite the examples item to cite `CONTRIBUTING.md` evidence rules instead of the readiness checklist); `docs/RELEASES.md` and `CONTRIBUTING.md` only if they link a moved file.
- Verification approach: grep shows no live link to the four files outside the files themselves; three link checkers pass; baseline SHA-256 of the four files, `railspike.txt`, and `temp/` recorded in the summary.
- Risks or open questions:
  - Impact: a backlog item silently loses meaning.
  - Early warning / validation: before/after list of the edited backlog lines in the summary.
  - Mitigation: pause for maintainer confirmation if an item's intent changes.
- Canonical components/API contracts touched: `BACKLOG.md`.

## Stage 2
- Goal: Move the four files out of the repository (≤1 hour).
- Dependencies: Stage 1 committed.
- Expected changes: create `~/railspike-archive/` and `mv` each file there, preserving names; `docs/` keeps no copies.
- Verification approach: hashes in the archive match the Stage 1 baseline; the four paths are absent from the working tree; `git status` shows exactly four deletions; link checkers pass.
- Risks or open questions:
  - Impact: a name collision or partial move.
  - Early warning / validation: inspect the destination before the move; compare hashes after.
  - Mitigation: stop on conflict; copy back from the archive if verification fails.
- Canonical components/API contracts touched: the four files; the archive directory.

## Stage 3
- Goal: Document what remains (≤1 hour).
- Dependencies: Stage 2 committed.
- Expected changes: extend the README "What's in this repo" map to cover every root file, `docs/` file, `docs/plans/`, `scripts/`, and `index.html`/logo; add this feature's entry to `docs/plans/README.md`.
- Verification approach: every tracked root file and `docs/*.md` appears in the map; link checkers pass.
- Risks or open questions:
  - Impact: map drift or a long README opening.
  - Early warning / validation: compare the map with `git ls-files` output.
  - Mitigation: keep one line per entry, in the existing code-block style.
- Canonical components/API contracts touched: `README.md` map; `docs/plans/README.md` index.

## Stage 4
- Goal: Release verification and maintainer review (≤1 hour).
- Dependencies: Stage 3 committed.
- Expected changes: none beyond defect fixes within scope.
- Verification approach: all three link checkers, `bash scripts/check-railspike-git-workflow.sh`, `git diff --check`; re-hash untracked files and the archive; list root and `docs/`; confirm `docs/plans/` changed only by the index and this feature.
- Risks or open questions:
  - Impact: a leftover reference or an unreviewed look.
  - Early warning / validation: the checkers and the maintainer's review of the listing.
  - Mitigation: fix within scope; return to Step 2 for new scope.
- Canonical components/API contracts touched: whole repository listing.

## Revision during Step 4 (pending approval)
Supersedes the Approval note, Stage 1's `BACKLOG.md` exclusion, and Stages 2–3 as follows; Stage 1 is complete.
- **Stage 2 - Move and repair links:** `mv` the two HN files into `docs/launch/` (hashes must match the Stage 1 baseline), then update `BACKLOG.md` links (lines 3, 12, 14), the relative links inside the moved files, and `docs/case-study-candidates.md` or `docs/positioning-checklist.md` if they link them. Verify: three link checkers pass, `git diff --check`, `git status` shows only the moves and intended edits; commit named paths only.
- **Stage 3 - Document:** README map and `docs/plans/README.md` as planned, listing `docs/launch/`.
- **Stage 4 - Verification:** unchanged, with the archive hash check replaced by the `docs/launch/` hash check.
- **Risk:** the pending `BACKLOG.md` whitespace fix is committed with Stage 2's link repairs.

## Revision after Step 4 handoff (pending approval)
- **Stage 5 - Reorganize docs:** `git mv` `BACKLOG.md`, `docs/RELEASES.md`, `docs/positioning-checklist.md`, `docs/case-study-candidates.md`, and `docs/launch/` into `docs/maintainers/`; rewrite the relative links inside them; add `docs/README.md`. Verify: three link checkers, workflow fixture, `git diff --check`; content diffs limited to link paths; the force-add caveat for the excluded checklist name is applied explicitly. Commit named paths with a summary update.
- **Stage 6 - Update references and map:** repair `README.md` (backlog link and map) and the `docs/plans/README.md` entry; re-run the checks and hash comparison for `railspike.txt`, `temp/`, and the moved files; commit, then make the log-only handoff commit.
- **Risk:** `.git/info/exclude` again hides the checklist after the move; check `git status` shows it staged before committing.
