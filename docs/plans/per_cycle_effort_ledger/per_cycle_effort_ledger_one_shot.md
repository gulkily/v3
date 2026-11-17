# One ledger file per cycle

Cycle: `v3/per_cycle_effort_ledger`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/per_cycle_effort_ledger`; [cycle ledger file](../effort_ledger/per_cycle_effort_ledger.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)

**Cycle estimate: 0.10**

Status: technically complete; PR open. Not merged.
Debug report: [log](per_cycle_effort_ledger_debug_log.md)

## Original request

Maintainer, 2026-11-14 21:59 ET, in a Claude session: "A lot of the PRs have merge conflicts and it's only for study data. Can you please help me fix this somehow?" No feedback item matches (searched `docs/feedback/items/`).

## Authorization

The request authorizes a fix to the conflict source, a branch and a PR, and resolving the open PRs' study-data conflicts. It does not authorize merging, deploying, rewriting earlier study values, or changing vendored Railspike in `docs/railspike/`.

## Outcome and work type

Documentation and repository-configuration only. Every open cycle appends to the single host ledger `docs/plans/effort_estimation_trial.md`, so any two open PRs conflict there (on 2026-11-14: #239, #240, #241, #246). After this change:

- Each cycle keeps its ledger entries in its own file, `docs/plans/effort_ledger/<feature_prefix>.md`. Two PRs for different cycles never touch the same ledger file.
- `effort_estimation_trial.md` keeps its protocol text and all history unchanged, with a dated notice that new entries go to the per-cycle files. Existing cycles continue in a new per-cycle file that links back to their earlier section.
- `.gitattributes` marks the old ledger `merge=union`, so local merges of the PRs still open against it keep both sides. GitHub's merge button ignores merge drivers, so this only helps local merges.
- `AGENTS.md` and `docs/reference/railspike_workflow.md` point at the new location.

Excluded: moving or editing existing ledger entries; the plans index `docs/plans/README.md`, which conflicts for the same reason but carries hand-edited entries where a union merge would keep stale lines; vendored Railspike (`docs/railspike/docs/effort-estimation.md` still describes one host ledger; the per-cycle files are that ledger split by cycle, which its "merge by cycle/checkpoint" rule allows).

## Approach and risks

Add the directory with a README holding the entry format (the same table row and dated lines the old ledger uses). Risk: agents keep appending to the old file out of habit. Mitigation: the notice at its top and at the P2 enrollment section, plus the `AGENTS.md` wording. A union merge on the old file is safe because entries there are appended dated lines and tables; it would be unsafe for code (see `merge_217_fix`), so the attribute names this one file only.

## Reuse decisions

Reuses the old ledger's index-row columns and dated-line style unchanged; no new format.

## Completion conditions

- New directory, README and this cycle's own ledger file exist; links resolve.
- `git check-attr merge docs/plans/effort_estimation_trial.md` reports `union`.
- A local merge of each open conflicting PR with this branch no longer conflicts on the ledger.
- Release: PR merged by the maintainer.

## Concepts touched

None.

## Verification and recovery plan

Link check of changed Markdown, `git check-attr`, trial merges of the open PRs. Runtime tests inapplicable (no application change). Recovery: revert the PR; no data moves.

Starting revision and branch: `chore/per_cycle_effort_ledger` from `90d3b0f`.
Planning commit: `616e7bf`.

## Dated execution history

- 2026-11-15: implementation committed (`0b438af`): `effort_ledger/README.md`, this cycle's ledger file, notices in the old ledger (top and P2 section; no entries changed), `AGENTS.md`, `docs/reference/railspike_workflow.md`, `.gitattributes`.
- Verification: `git check-attr` reports `union`. Links in the new files resolve (the README's template placeholder excepted). `git merge-tree` of this branch with #239, #240, #241 and #246 shows no ledger conflict; #239 and #240 still conflict on `docs/plans/README.md`, which is out of scope here and resolved by hand in those PRs. Runtime tests not run: no application change. Full suite not run.
- Limitation: GitHub's merge button ignores `merge=union`, so PRs opened before this change still need one local merge of `main`.

## Retrospective

Work matched the 0.10 estimate. Maintainer's judgment of fit: pending. Follow-up candidate: upstream the per-cycle layout into Railspike's effort-estimation contract, and give `docs/plans/README.md` the same treatment if its conflicts keep recurring.


*Claude (claude-opus-5-5)*
