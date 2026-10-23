> **Feature plan:** [Step 1](./concurrent_checkouts_step1_solution_assessment.md) · [Step 2](./concurrent_checkouts_step2_feature_description.md) · [Step 3](./concurrent_checkouts_step3_development_plan.md) · [Step 4](./concurrent_checkouts_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/concurrent_checkouts`; [ledger](../effort_estimation_trial.md), R1/P2, scope v4. Step 3 approved; planning commit `b1b33e0b8`; original baseline `19f1066a`, rewritten entry `1a358bc9`. Entry projection reaffirmed unavailable pending exact frozen feedback recovery; earlier snapshots preserved. Codex single exposed rater. No numeric score or measured effort invented.

## Stage 1 - Registry and command entry

- Changes: private atomic shared registry, add/list/remove, source/path validation and shared-storage duplicate rejection; `v3` dispatcher and human/JSON entry.
- Verification: `python3 tests/test_checkouts.py`: initial run had 4 passes and a JSON error-mode failure; corrected option pre-detection, then 5 real-Git cases passed (lifecycle/permissions, aliases/shared storage, malformed/missing paths, roles/help, credential rejection); PHP syntax checks passed.
- Notes: no live checkout registrations; stages 2–7 pending. Other local planning-ledger changes remain pending and outside the initial planning commit.

## Stage 2 - Fetch and history evidence

- Changes: selected-source fetch without tags/submodules, identity/state inspection, persistent validated ancestry baselines and sticky rewrite blocks, conservative unknown-divergence handoff, role/path selection.
- Verification: `python3 tests/test_checkouts.py`: 10 real-Git tests passed, including observed and already-fetched rewrites, unknown versus validated feature divergence, dirty/busy and source mismatch. Working HEAD remains unchanged by preview.
- Notes: Stage 1 commits `5c296bac3`, correction `3bd230353`; initial stage check failure is recorded. A baseline proves continuity from observed enrollment, not universal history authenticity; uncertain branches block. Saved preview/UI pending Stage 3.

## Stage 3 - Saved previews and output

- Changes: private signed preview snapshots with registry/source/HEAD identity, opaque IDs, human actions/reasons and JSON parity; attention exit 2 and fetch side-effect notice. Source URLs/errors remain sanitized; selected/excluded roles visible.
- Verification: `python3 tests/test_checkouts.py`: 12 cases passed, including private saved-preview permissions, human guidance, missing paths and invalid selectors; PHP syntax passed.
- Notes: Stage 2 commit `8721d5963`. Apply and preview-consumption validation follow in Stage 4; no live pool touched.

## Stage 4 - Revalidated fast-forward application

- Changes: session-coordination assertion, signed-preview/registry validation, tool/common-storage locks, stale snapshot rejection, fast-forward-only Git update, per-folder durable attempt/results and no preview replay.
- Verification: `python3 tests/test_checkouts.py`: 17 real-Git tests passed, including invalid/tampered preview, registry/HEAD/branch/source/dirty changes, session flag, lock contention and interrupted-result replay rejection.
- Notes: Stage 3 commit `f3527f330`. External writers must still be paused; tool locks coordinate only this tool using the same state directory. Preview applies its fetched commit, not an unobserved later remote tip.
