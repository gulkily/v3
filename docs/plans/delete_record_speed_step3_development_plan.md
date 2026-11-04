> **Feature plan:** [Step 1](./delete_record_speed_step1_solution_assessment.md) · [Step 2](./delete_record_speed_step2_feature_description.md) · [Step 3](./delete_record_speed_step3_development_plan.md) · [Step 4](./delete_record_speed_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/delete_record_speed`; [host ledger](./effort_estimation_trial.md#cycle-v3delete_record_speed). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 approved; Step 3 proposed; implementation unapproved.

**Cycle estimate: 0.65** (revised; Step 2 0.60)

## Completion Contract

- Normal entry: `./v3 delete-record <post-id> --inspect` and `./v3 delete-record <post-id>` over SSH on the qdb.us host.
- End-to-end outcome: each command prints one status line per step with elapsed time and finishes in under 1s wall time, startup included; after the delete, the thread is gone from its page, the front page, Recent and its tag pages.
- Required recovery: a failure or kill after the commit leaves the read model marked stale with a message naming the rerun command; rerunning completes the delete. The full rebuild runs only as that fallback. `--repair-labels` keeps its current path.
- Deployment and external verification: the operator deploys as usual (`git pull` in `~/v3`); the first `./v3` run after deploy migrates the read model to the new schema.
- Release condition: verified on a local copy of the qdb repository, then on the qdb.us host with pasted `time` output for a real spam thread.

## Key Risks

- **High risk:** direct row removal diverging from a rebuild (wrong counts, stale labels, missing reply placeholders). Early validation: equivalence test in Stage 4 before any host run. Mitigation: compare the full read model after delete with a fresh rebuild on the same commit.
- Git cost in a ~311k-file repository. Today's delete runs about a dozen `git` processes (`ls-files`, `ls-tree`, `add`, `diff`, `commit`), each loading the whole index. Early validation: Stage 1 host timing. Mitigation: Stage 3 commits with one index read.
- Startup floor. Early validation: Stage 1 times `./v3 --version` and a bare commit on the host. Mitigation: if the floor alone exceeds about 0.5s, stop and restate the target with the maintainer before Stage 3.
- **High risk:** schema migration on a live site. Early validation: prior-version regression test in Stage 2. Mitigation: the index is derived and rebuildable; with an old schema the command falls back to the scan instead of failing.

## Stage 1

**Estimate: 0.10**

- Goal: both commands print live status lines now, and the host floor is measured.
- Dependencies: none.
- Expected changes: a small step reporter used by `scripts/delete_record.php`, `LabelRemoval::inspect` and `SourceLabelRemoval::run` (stderr lines `<step>… 0.04s`, lock waits named, final `Deleted <id> in Xs`); fallback rebuild wired to the existing `ReadModelBuilder` progress reporter. New `-q`/`--quiet` hides step lines but keeps errors and the final `Deleted` line; `--json` prints no step lines and leaves stdout unchanged. Usage text lists `-q`.
- Verification approach: CLI test asserting step lines appear on stderr in order, `-q` leaves only errors and the final line, and stdout JSON is unchanged; maintainer runs the timing commands from the Step 4 summary on the host and pastes the output.
- Estimate details: driver: threading one reporter through three call sites; uncertainty: low.
- Risks or open questions:
  - Impact: a slow floor would make the 1s target unreachable regardless of later stages.
  - Early warning / validation: host timings recorded in the summary before Stage 3 starts.
  - Mitigation: stop and restate the target with the maintainer if the floor is too high.
- Canonical components/API contracts touched: `ReadModelBuilder` progress closure (reused); `delete-record` stderr output and options (extended with `-q`/`--quiet`).
- Revision 1 (2026-11-04): maintainer asked for `-q`/`--quiet`; added here. Stage estimate 0.10 reaffirmed.

## Stage 2

**Estimate: 0.18**

- Goal: inspect finds a thread's label files without scanning the directory.
- Dependencies: Stage 1.
- Expected changes: a derived read-model table of thread label files (thread ID, label path, source post ID) filled by `ReadModelBuilder` during its existing label pass and kept current by `applyThreadLabelWrite`; `LabelRemoval::inspect` and `IncrementalReadModelUpdater::loadThreadLabelRecords` read it, falling back to the scan when the schema is older. `SCHEMA_VERSION` 47 → 48, `docs/specs/read_model_schema_v1.md` updated.
- Verification approach: prior-version (47) regression test per `CONTRIBUTING.md`; inspect output identical with index and with scan for root, reply and legacy-label cases; timing on a local qdb copy.
- Estimate details: driver: schema change rules and keeping the index current on label writes; uncertainty: legacy labels without a source post ID.
- Risks or open questions:
  - Impact: a stale index would hide labels from deletion.
  - Early warning / validation: test comparing index results with the scan after label writes and a rebuild.
  - Mitigation: the scan fallback stays for old schemas and for `--repair-labels`.
- Canonical components/API contracts touched: `ReadModelBuilder`, `IncrementalReadModelUpdater::applyThreadLabelWrite`, `LabelRemoval::inspect(string $root, string $id): array` (signature unchanged).

## Stage 3

**Estimate: 0.12**

- Goal: the removal commit costs one index read.
- Dependencies: Stage 1 timings.
- Expected changes: `SourceLabelRemoval::accept` stages removals and the receipt with a single `git update-index` call and creates the commit from the index tree (no per-path `ls-files`/`ls-tree`, no pathspec commit); journal, receipt format and `PublicationLock` unchanged.
- Verification approach: existing removal and retry tests; new test that a kill between staging and commit is completed by a rerun; resulting tree identical to the current path's tree; local timing on the qdb copy.
- Estimate details: driver: preserving retry semantics with plumbing; uncertainty: index refresh behavior on the shared filesystem.
- Risks or open questions:
  - Impact: a bad commit could drop unrelated staged changes in the content repository.
  - Early warning / validation: test with an unrelated dirty file present.
  - Mitigation: stage and commit only the named paths; refuse to run when unrelated paths are staged.
- Canonical components/API contracts touched: `SourceLabelRemoval::accept`, `LabelRemoval::git`, delete receipt (format unchanged).

## Stage 4

**Estimate: 0.20**

- Goal: after the commit, the site reflects the deletion without a rebuild.
- Dependencies: Stages 2 and 3.
- Expected changes: `IncrementalReadModelUpdater::applyPostRemoval(string $postId, string $threadId, string $commitSha): array` removes the post's rows, re-derives that thread's label state and counts, drops the thread when the post was its root, and updates activity; `SourceLabelRemoval::run` calls it and `StaticArtifactInvalidator::invalidateBoardThread` or `invalidateReply`, extended to the thread's tag pages, instead of `invalidateAllGeneratedPresentation` and `rebuild()`. On failure: stale marker plus fallback rebuild, unchanged.
- Verification approach: equivalence tests (root thread with labels, reply with labels, reply with replies) comparing the read model after delete with a fresh rebuild; static page checks for thread, front page, Recent and tag page; recovery test with a forced failure after commit.
- Estimate details: driver: matching rebuild output exactly; uncertainty: activity and qdb welcome selection side tables.
- Risks or open questions:
  - Impact: see Key Risks (divergence).
  - Early warning / validation: equivalence tests written first.
  - Mitigation: any mismatch found during the stage falls back to marking stale and rebuilding for that case until fixed.
- Canonical components/API contracts touched: `IncrementalReadModelUpdater`, `StaticArtifactInvalidator`, `ReadModelStaleMarker`, `SourceLabelRemoval::run`.

## Stage 5

**Estimate: 0.08**

- Goal: accept on the qdb.us host and record it.
- Dependencies: Stages 1–4 deployed.
- Expected changes: concept entries *incremental source removal* and *operator command progress*; `delete-record` reference text updated; Step 4 summary with host evidence.
- Verification approach: maintainer runs `time ./v3 delete-record <id> --inspect` and the delete for a real spam thread, pastes output; thread URL and front page checked; `./v3 purge-history preview` lists the post; smoke tests plus tests for changed code locally (full suite not run).
- Estimate details: driver: coordinating the host run; uncertainty: host load at run time.
- Risks or open questions:
  - Impact: a host-only slowdown not seen locally.
  - Early warning / validation: Stage 1 host numbers as the baseline.
  - Mitigation: status lines show which step is slow; record it and decide with the maintainer.
- Canonical components/API contracts touched: docs only.

## Concepts Touched

[Read model rebuild](../concepts/read_model_rebuild.md) used unchanged as fallback; [static publication](../concepts/static_publication.md) changed (tag pages in targeted invalidation); [post retraction](../concepts/post_retraction.md) unchanged; new entries at Stage 5: *incremental source removal*, *operator command progress*.

## Enhancement Requests

- Web tagging also scans every label file through `loadThreadLabelRecords`; Stage 2 fixes it as a side effect since the same lookup is replaced. Disposition: within scope because the delete path calls it; no separate web work planned.

## Study Checkpoint

2026-11-04T17:55:00Z; scope v2/D, baseline `ea306fc03b3152a2e66b3d2a75b13d7d1c4064bc`, R1/P2; frozen hashes verified earlier this session, unchanged. User explicitly approved Step 2. Whole-cycle revised **0.60 → 0.65**: inspection found about a dozen `git` processes per delete, so the plumbing commit is its own stage. Stage-work originals S1 0.10, S2 0.18, S3 0.12, S4 0.20, S5 0.08; non-additive. Claude, single exposed rater. No implementation, measured labor or accepted outcome. Await Approved Step 3.

2026-11-04T17:58:00Z revision: maintainer added a `-q`/`--quiet` option to Stage 1. Small addition within Stage 1; S1 0.10 and whole-cycle 0.65 reaffirmed. Same v2/D/baseline/R1/P2, single exposed Claude rater.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
