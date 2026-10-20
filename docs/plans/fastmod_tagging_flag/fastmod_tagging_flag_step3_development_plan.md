> **Feature plan:** [Step 2](./fastmod_tagging_flag_step2_feature_description.md) · [Step 3](./fastmod_tagging_flag_step3_development_plan.md) · [Step 4](./fastmod_tagging_flag_step4_implementation_summary.md)

Debug mode: enabled

Follow-up `fastmod_tagging_flag` to [root meta tagging](../fastmod_rubric_actions/fastmod_rubric_actions_step4_implementation_summary.md). Step 1 skipped; Step 2 explicitly approved. Use a new feature branch after Step 3 approval. This cycle is not selected for the effort trial; do not change E07's completed records or invent another accepted outcome.

## Completion Contract

Tools → Feature Flags → Fastmod → signed **Automatic Fastmod tagging** toggle → publish a new local root → ordinary scoring → existing meta label; disable → no new tags, including in-flight application, while existing tags and explicit CLI removal/rollback remain available. One shared capability flag, no rubric/tag-specific flags. Preserve default-off, root-only/no-backfill behavior and private threshold; save does not call the model. Reuse UI, signatures, rubric selection and meta mapping; no schema changes, mapping editor, styling, new navigation, deployment or live-instance activation. Verify phone/desktop and affected themes. Documentation scope: flag/tagging reference/runbook, these plans/log, planning index and predecessor follow-up disposition only.

## Key Risks

- **High risk: inconsistent enablement.** Resolve site/environment/legacy precedence in the flag evaluator first; use it for publication and fresh worker/CLI checks, with explicit configured repository paths. No global precedence change for unrelated flags.
- **High risk: compatibility/rollback.** Shared environment override → saved shared site value → legacy meta environment/private fallback → false; explicit site false beats legacy true. Invalid site state blocks new tagging. Disabling preserves existing labels, suppression and CLI recovery.
- **Authority/usability:** reuse current signed operator flow and effective-source/dependency rendering. Test unsigned/revoked authority, overridden settings and failed-save recovery before claiming the control works.

## Stage 1 — Shared flag and precedence

- Goal: one reusable automatic-tagging capability with reliable compatibility.
- Dependencies: Approved Step 3; new branch and planning-only commit.
- Expected changes: register `FASTMOD_AUTOMATIC_TAGGING_ENABLED`, site-mutable/default-off in FASTMOD, dependent on existing scoring; narrowly adapt evaluator fallback for legacy meta setting, without a second registry flag. Keep threshold/mapping independent.
- Verification approach: default, dependency, environment/site/legacy true/false matrix, invalid state and existing flag regression tests.
- Risks or open questions: generated legacy false must not lock the signed site toggle; preserve effective source so operators can resolve environment overrides.
- Canonical components/API contracts touched: FeatureFlagRegistry/Evaluator/State, private-config key catalog and focused flag-policy tests.

## Stage 2 — Publication and runtime wiring

- Goal: all tagging entry points obey the shared effective flag.
- Dependencies: Stage 1 matrix passing.
- Expected changes: shared tagging-policy resolver; pass repository/project context through publication, worker factories and CLI `meta-*` recovery. Re-evaluate before application; update read-side missing-state diagnostics without hiding existing tags when off. Remove direct legacy-only checks from active enablement paths.
- Verification approach: eligible new roots only; disabled publication; off during provider work; saved site value after process restart; non-default repository paths; CLI recovery/rollback while off; no new calls or historical tagging on enable.
- Risks or open questions: CLI defaults must match the selected instance; thread/reply scoring itself stays independent of tagging policy.
- Canonical components/API contracts touched: shared resolver, RouteServices, FastmodMetaConfig/Runtime/Read, task_queue.php and fast_score.php factories/options, existing meta regressions.

## Stage 3 — Signed browser journey

- Goal: operator enables tagging through the existing interface and sees the outcome.
- Dependencies: Stage 2 focused tests passing.
- Expected changes: existing flag row and Fastmod cross-links recognize the shared key; extend existing feature-flag/Fastmod browser fixtures, without new form/styling/navigation.
- Verification approach: signed save/readback, permissions/tampering, legacy fallback/site-off override, dependency/environment states, failure/retry and keyboard access; toggle on → ordinary/signed root score → existing tag → toggle off → later scoring adds no tag. Keep human tags, prove zero provider calls on save, and check phone/desktop plus existing themes.
- Risks or open questions: source-text assertions do not establish signed toggling or restart consistency; use isolated writable fixtures and deterministic provider.
- Canonical components/API contracts touched: feature_flags template/scripts and signed endpoints, Tools Fastmod context links, existing browser/meta tests.

## Stage 4 — Operations and closeout

- Goal: document activation and compatibility without expanding scope.
- Dependencies: Stage 3 journey passing.
- Expected changes: update configuration/reference/runbook for shared toggle, legacy fallback, overrides, root-only mapping and disable/recovery; record checks/commits and resolve predecessor follow-up disposition.
- Verification approach: relevant flag/private-config/meta/worker regressions; PHP/JS syntax and links/diff checks; existing broader checks as warranted. Report baseline failures separately. Review all enablement call sites and unchanged todo.txt.
- Risks or open questions: no implicit site activation or merge; unavailable deployment/live-model checks are inapplicable, not passed.
- Canonical components/API contracts touched: reference/runbook, feature summary/debug log, predecessor summary and relevant existing test commands.

## Execution and approval

Organize this cycle's approved plans/log and index before its separate planning commit; leave predecessor pending-disposition edits out of that commit. Each completed stage includes verification and its Step 4 summary in one commit before its successor; no amended history. No application implementation or flag change before `Approved Step 3`. Material scope changes require replanning; adding future mappings/rubrics remains separate work.
