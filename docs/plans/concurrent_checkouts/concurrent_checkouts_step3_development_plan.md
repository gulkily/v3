> **Feature plan:** [Step 1](./concurrent_checkouts_step1_solution_assessment.md) · [Step 2](./concurrent_checkouts_step2_feature_description.md) · [Step 3](./concurrent_checkouts_step3_development_plan.md) · [Step 4](./concurrent_checkouts_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/concurrent_checkouts`; standing [P2 enrollment](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2). Step 2 approved; scope v4.

## Completion Contract

- From IntelliJ's WSL terminal, use `./v3 checkouts add|list|remove|preview|apply`: configure a shared private registry, fetch/preview selected current branches, apply eligible updates and receive per-checkout outcomes with actionable recovery. Default local registry: `~/.config/v3/checkouts.json` (respect XDG config home); shared local preview/state storage respects XDG state home. Support explicit registry/state overrides for testing and isolation, selectors and JSON. Registry owns path, expected source, remote, target and role; no disk scan or automatic enrollment of existing clones.
- Preview records source/HEAD/branch/identity, registry version and worktree state under a generated local ID; apply consumes that ID, rechecks inputs and updates only eligible checked-out branches. Require an explicit `--sessions-paused` operator assertion to apply; it is coordination, not process-idle detection. Never use auto-stash, push, reset, rebase, branch switching or automated migration. Missing/moved folders receive corrective guidance and registry remove/re-add; no deletion of their contents.
- Successful release requires real-Git fixtures plus on-host disposable-clone terminal acceptance for setup, preview/apply, skips, conflicts/abort/retry and rewrite handoff; human output, JSON, help, exit codes and partial results agree. Record evidence and limits in Step 4. No production/live-clone trial or IDE modification required; actual IntelliJ UI interaction is a manual check and remains pending if inaccessible. Other branches, stashes and linked worktrees remain untouched; no historical-erasure claim.

## Key Risks

- **High risk: old history restored.** Validate rewrite/unknown-history classification before merge support. Safe enrollment requires the checked-out head to be an ancestor of the fetched target, or the fetched target to be an ancestor of that head; unproven divergent enrollment is blocked. Persist the fetched target as a trusted baseline only after this check. Subsequently require baseline ancestry in both the branch and new source, or direct head ancestry in source with no detected source rewind. Record pre-fetch and prior validated targets; non-forward changes block even if an older ancestor is shared. First-run divergent branches need manual reconciliation; no unsafe “trust anyway” bypass. Common ancestry alone is insufficient.
- **High risk: wrong folder/concurrent writes.** Validate canonical source and worktree/common-Git-directory identities, group shared storage and serialize this tool's writers. Recheck under that lock immediately before mutation; external agents do not honor this lock, so paused sessions remain necessary. Source/path/registry/HEAD changes invalidate the affected preview; do not silently regenerate it.
- **High risk: partial update/recovery.** Atomic private state writes and per-checkout attempt/outcome records make retries understandable. Reused previews never replay finished or uncertain operations; inspect and create a new preview. Git conflict state remains available for explicit resolve/abort. Bound Git subprocess time and report network/identity/hook failures without hanging the batch.

## Stage 1 — Registry and command entry

- Goal: register and list known clones from any project window.
- Dependencies: approved Step 3 and planning commit before implementation.
- Expected changes: wire `checkouts` dispatch/help, private atomic registry storage, add/list/remove and selectors; normalize expected sources conservatively (do not equate different hosts), validate directories, group shared Git storage and reject duplicate update selections; PHP script/classes via existing autoloader, no new dependency.
- Verification approach: real temporary repositories, malformed/missing registries, missing/moved paths, source mismatch, symlink aliases, linked worktrees, alternate config and human/JSON/help output.
- Risks or open questions: wrong targets/private-path exposure; validate identity/private file permissions before later fetch/apply stages; local fixture remotes allowed explicitly without treating them as GitHub.
- Canonical components/API contracts touched: `v3`, new `scripts/checkouts.php` and `src/ForumRewrite/Checkouts/`, existing PHP test harness; registry contract.

## Stage 2 — Fetch, inspect and establish history evidence

- Goal: classify branches safely, including rewritten history.
- Dependencies: Stage 1 committed.
- Expected changes: bounded argument-array Git runner, fetch selected source only, inspect head/target/pre-fetch refs, clean/busy state and validated ancestry baseline; persist evidence separately from human output; detached heads or uncertain histories receive a blocking reason.
- Verification approach: real fast-forward/ahead/diverged cases, no baseline, force-push before/during prior fetch, source rewind, old-history feature branch sharing an ancient ancestor, missing refs/network failure and operation markers. Prove blocked classification leaves checked-out refs/files untouched.
- Risks or open questions: already-fetched rewrites; validate the baseline policy with adversarial fixtures before apply; if evidence is insufficient, block rather than invent a Git-only certainty claim.
- Canonical components/API contracts touched: checkout Git/state support and classification result contract; existing Git authoritative metadata.

## Stage 3 — Saved preview and operator output

- Goal: provide a reviewable stable batch preview.
- Dependencies: Stage 2 committed and history-safety fixtures passing.
- Expected changes: `preview` saves private immutable per-checkout observations/actions and source revisions, returns ID, prints actions/reasons and distinguishes fetch side effects from unchanged working files; JSON mirrors results. Excluded, failed and unavailable entries remain visible.
- Verification approach: mixed eligibility, deterministic selector behavior, storage failures/tampered IDs, no credentials in output, shared-source deduplication and all-disposition human/JSON assertions.
- Risks or open questions: misleading preview or leaked configuration; store local state privately, sanitize remote errors and distinguish eligible count from total folders.
- Canonical components/API contracts touched: preview storage/result schema, CLI renderer and exit contract (0 completed, 1 failure, 2 partial/attention needed).

## Stage 4 — Apply fast-forwards with stale-state protection

- Goal: perform eligible fast-forwards from an explicit preview.
- Dependencies: Stage 3 committed.
- Expected changes: `apply <id> --sessions-paused`, per-common-Git-directory tool lock, validate plan schema/registry/source/branch/HEAD/target/worktree again, fast-forward-only main/eligible branches and leave already-current/locally-ahead entries unchanged; journal each attempted result, never auto-replay a consumed preview.
- Verification approach: genuine fast-forward and no-op, changed paths/remote/branch/HEAD/source/dirty state, lock contention, stale/consumed IDs, mid-batch interruption and per-folder revalidation; assert no force update or discarded local commits.
- Risks or open questions: external writers and interrupted attempts; coordination required, uncertainty blocks retry until a fresh preview; don't claim Git locks stop agent edits.
- Canonical components/API contracts touched: preview/apply state, tool lock, Git runner and exit/result contracts.

## Stage 5 — Feature merges and conflict recovery

- Goal: update eligible feature branches without rewriting commits.
- Dependencies: Stage 4 committed and safe history classification proved.
- Expected changes: ordinary noninteractive merge of the previewed source commit, no hook bypass; journal success/conflict/other failure, print path-specific resolve/continue/abort guidance and continue independent folders. Existing conflicts/rebase/cherry-pick state remain skipped.
- Verification approach: clean divergent merge preserving both parents, missing Git identity, failing hooks, intentional conflict, dirty/busy skips, partial results, abort and fresh-preview retry, interruption; ensure failed cases preserve recoverable state and never report completed synchronization.
- Risks or open questions: merges may change files or execute local hooks; explicit apply and session coordination are prerequisites; bound subprocesses and report actual outcome rather than assuming rollback.
- Canonical components/API contracts touched: apply orchestration, Git merge state, recovery renderer and journal.

## Stage 6 — Operator documentation and integration checks

- Goal: make setup/update/recovery usable without chat assistance.
- Dependencies: Stage 5 committed.
- Expected changes: extend top-level help and `docs/reference/v3_cli.md`; document registry/roles/selectors, private shared state, baseline restrictions, preview/fetch effects, apply coordination, partial exits and conflict/missing-folder/source/rewrite recovery; link existing rewrite runbook. State scope of untouched refs and no live clone enrollment by default.
- Verification approach: execute every documented command against disposable clones; validate help/JSON/exit recovery agreement and regression isolation from `status` and `railspike sync`; syntax and diff checks.
- Risks or open questions: docs promising universal rewrite detection or full migration; align exact claims with evidence and mark first-run divergence as requiring reconciliation.
- Canonical components/API contracts touched: `v3` help, CLI reference, checkout command tests; no forum UI/schema.

## Stage 7 — WSL terminal acceptance and handoff

- Goal: verify ordinary entry and reported failure recovery on this host.
- Dependencies: Stage 6 committed.
- Expected changes: isolated private registry and disposable WSL clones; replay setup from two project directories, preview/apply, dirty/busy and stale skips, missing/mismatched source, linked-storage duplicate, conflict abort/retry and fetched/already-fetched rewrite blocks; finalize Step 4 evidence and study ledger, leaving live clone pool intact.
- Verification approach: run focused Git acceptance matrix and affected existing command tests; fresh preview/status confirms outcomes and local work preservation; record exact commands/results, incomplete tests and any inaccessible IntelliJ manual check. No broad suite reruns absent new concerns.
- Risks or open questions: confusing shell acceptance with actual IDE interaction; distinguish on-host terminal evidence from unperformed IntelliJ clicks; remove only self-created fixtures after preservation checks.
- Canonical components/API contracts touched: checkout tests, cycle Step 4 summary/debug log and host ledger; CLI reference corrections if acceptance reveals errors.

## Execution and Study Checkpoint

- Seven stages are scoped to roughly an hour each; if a stage cannot remain bounded, split/replan before dependent work. Every stage gets applicable checks, a Step 4 summary update and its own commit before advancing. After Approved Step 3, create `feat/concurrent-checkouts`, organize this cycle's plans under `docs/plans/concurrent_checkouts/`, update the planning index and make the authorized planning-only commit first. Step 4 summary is created afterward. Keep unrelated ledger changes outside the commit; include this cycle's bookkeeping at its authorized stage. No branch or implementation created during Step 3.
- Public conceptual contracts: registry enrollment/list/remove; inspect-and-classify; save/load preview; apply-preview; per-checkout outcomes. Concrete helper signatures remain internal PHP choices; no database change or external API is introduced.
- Request record: terminal mockup and shared-registry questions / in-scope refinements of approved Step 2 / formalized above, illustrative command ID becomes an opaque local ID / optional automatic candidate discovery deferred, not part of this cycle.
- 2026-10-23T09:50:06+00:00; Step 3 projected total **unavailable pending exact frozen R1 feedback recovery**, reaffirming scope v4/R1/P2, original baseline `19f1066aac81fe417d88a10efa9c756125199af5`, current rewritten checkout `1a358bc9be3cdb725277c792625a678e7bc85436`, Codex single exposed rater. Earlier missing scores remain missing; manual migration is prior exposure, not automated delivery or measured work. No maintainer acceptance or accepted-count increment.

**Approved Step 3** received. Implementation authorized on `feat/concurrent-checkouts` under the recorded staged plan.
