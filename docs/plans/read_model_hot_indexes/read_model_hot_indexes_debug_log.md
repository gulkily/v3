# read_model_hot_indexes — Railspike debug log

## 2026-10-24 UTC — Step 1 (Solution Assessment)

### Necessary/useful instructions
- `RAILSPIKE.md` "Step Guide" + `step1_solution_assessment.md` structure contract (Original Query → Understood Intent → Problem → ≥2 options → Recommendation): kept the assessment to one page and forced an explicit isolation rationale for the schema-change cycle. Retain.
- `CONTRIBUTING.md` read-model schema-change checklist (referenced, not part of Railspike): materially shaped the options and the verification list. Retain; this cross-reference is the reason Option A is scoped as it is.
- Effort study fields (`AGENTS.md` standing P2 enrollment + ledger R1 recovery note): enabled a concrete projected total (≈0.30) instead of a deferred "unavailable" because R1 frozen references were recovered 2026-10-23. Useful; retain.

### Redundant/unnecessary instructions
- No supported findings. (The effort-estimation guidance is long, but each clause consulted was load-bearing for this cycle's snapshot.)

### Inapplicable instructions
- Extended estimates (`docs/effort-estimation.md#extended-estimates`): not enabled for this cycle; no per-option estimate line required. Non-use is not redundancy.
- Follow-up-cycle / branch-reuse rules: no Step 4 branch exists yet.

### Uncertain assessments
- Projected total ≈0.30 is a single exposed, non-blind rater judgment against R1; confidence is moderate because the schema-change checklist cost is well-characterized but rebuild time on a large instance is unmeasured here.

## 2026-10-24 UTC — Step 2 (Feature Description)

### Necessary/useful instructions
- `step2_feature_description.md` "Known user or environment" clause: correctly prompted an explicit omission (no named user/env) rather than silently dropping the section. Retain — the explicit-omission guidance prevented an ambiguous gap.
- "A feature is a normal-flow vertical slice, not a component, API, cache, migration" guardrail: forced framing an inherently infrastructure change (indexes) as reader/operator outcomes with entry→outcome→recovery, which kept the completion boundary user-facing. Retain.
- Shared component inventory requirement: surfaced that `PublicOfflineSnapshotBuilder` already defines the equivalent index, reinforcing the "bring main model in line" framing and confirming no new component. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Documentation-only scope rules: this is an application change; recorded the work type explicitly instead.
- Extended estimates: not enabled; no per-item estimate annotation required.

### Uncertain assessments
- Rebuild cost on a large production instance is unmeasured in this environment; flagged as a risk with `./v3 rebuild` as earliest validation rather than asserted as negligible.
- ≈0.30 reaffirmed is a single exposed, non-blind rater judgment; confidence moderate because the schema-change checklist is well-characterized.

## 2026-10-24 UTC — Step 3 (Development Plan)

### Necessary/useful instructions
- `step3_development_plan_do.md` per-stage fields (Goal/Dependencies/Expected changes/Verification/Risks/Canonical components): surfaced a concrete, otherwise-easy-to-miss dependency — `ReadModelSchemaTest` asserts the exact table/index name set, so it must change in the same stage as the index additions. Retain; the structured fields caught a real coupling.
- `## Key Risks` near the top with `**High risk:**` prefix: forced the results-drift risk to the top where a reviewer sees it first. Retain.
- "Document database changes conceptually (no SQL)" guardrail: kept the plan to index names + column lists rather than DDL, which stayed readable while still unambiguous. Retain.
- Completion Contract "required recovery" field: prompted tracing the actual rebuild trigger (`Application::ensureReadModel()` → `schema_identity_mismatch`), making Stage 3's regression test concrete rather than hand-wavy. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Extended-estimate stage-work bullets: mode not enabled; per-stage estimate bullet omitted by rule.
- Known-user/environment replay stage: Step 2 named no user/environment; no replay stage required.
- Documentation-only file-scope checks: this is an application change.

### Uncertain assessments
- Stage 4 is verification-only; whether it should be folded into Stage 3 is a judgment call — kept separate so query-plan/content evidence has a distinct, auditable handoff. Low-confidence either way.
- ≈0.30 reaffirmed is a single exposed, non-blind rater judgment; rebuild timing on a large instance remains the main unmodeled factor.

## 2026-10-24 UTC — Step 3 (Development Plan) — Revision: extended estimates enabled

Revision of the 2026-10-24 Step 3 entry above (does not supersede its findings; adds the extended-mode observation). User requested enabling extended estimates for this cycle; Step 3 re-drafted with per-stage stage-work values. Step 3 approval remains pending; this revision does not advance the step.

### Necessary/useful instructions
- `RAILSPIKE.md` extended-estimate rules ("every Step 3 stage gets a separately labeled stage work estimate"; "never sum stage estimates"; "cycle total independently judged"): kept the four stage annotations (0.05/0.07/0.15/0.08) distinct from the reaffirmed ≈0.30 cycle total and prevented presenting their sum as the projection. Retain — the explicit no-sum rule is load-bearing here.
- "do not backfill missing historical estimates" + "record an explicit disable/enable in the current artifact": correctly drove recording the mode as effective from Step 3 and NOT inventing retroactive Step 1 per-option estimates. Retain.
- "identify the owner of shared work without charging whole-cycle planning to every stage": made Stage 4 the explicit owner of end-to-end/deployment verification, avoiding double-counting that overhead across stages. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Documentation-only file-scope checks: application change.
- Known-user/environment replay stage: none named.

### Uncertain assessments
- Stage 3's ≈0.15 is the least certain stage value: it depends on how cleanly `Application::ensureReadModel()` can be driven from a test harness without heavy fixtures; flagged as medium uncertainty in-plan.
- The per-stage values are single exposed, non-blind rater judgments against R1; they are annotations, not measured effort.

## 2026-10-24 UTC — Step 4 (Implementation) — completed-step report

Single Step 4 report (not per stage). Stages 1–4 implemented and committed on `feat/read-model-hot-indexes`; planning `7f928218`, stages `4eb59792`/`87fceacd`/`cef85b1d`/`3a25919c`.

### Necessary/useful instructions
- Stage Boundary Protocol (finish → verify → update summary → `git status` → stage commit → `git log` → next): kept each stage independently committed with its own verification, and caught that the two unrelated debug logs must stay unstaged at every boundary. Retain — the explicit `git status` gate prevented cross-cycle leakage.
- "A focused pass is never reported as a full-suite result" + "identify reproduced baseline failures": directly shaped the honest Stage 4 reporting of the two pre-existing `ReadModelProvenanceTest` git failures as reproduced-baseline, not regressions. Retain — load-bearing for an accurate handoff.
- Extended-mode per-stage retrospective requirement: surfaced useful signal (Stage 3 came in under estimate because existing fixtures/public API avoided a heavier harness; Stage 4 ran over due to environmental PHP linkage). Retain.
- `CONTRIBUTING.md` schema-change checklist (external to Railspike but referenced throughout): every clause mapped to a concrete stage/verification. Retain the cross-reference.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Documentation-only verification gates (`git diff --check`, allowed-file-scope): this was an application change; used runtime/test verification instead.
- Known-user/environment recovery replay: none named in Step 2.
- Browser/UI state matrix: no presentation change (indexes only; content proven byte-identical), recorded not-applicable with reason.

### Uncertain assessments
- The ≈0.30 cycle total and per-stage retrospectives are single exposed, non-blind rater judgments; not measured effort. Maintainer comfortable-fit judgment is pending (unreviewed/unmerged), so study outcome acceptance stays pending — explicitly not invented.
- Whether Stage 4 should have been folded into Stage 3 remains a low-confidence call; keeping it separate gave the query-plan/rebuild evidence a clean auditable commit.
