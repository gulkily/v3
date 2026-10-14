> **Feature plan:** [Step 1](./startup_dependency_recovery_step1_solution_assessment.md) · [Step 2](./startup_dependency_recovery_step2_feature_description.md) · [Step 3](./startup_dependency_recovery_step3_development_plan.md) · [Step 4](./startup_dependency_recovery_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Terminal `./v3 start`: assess → review → Y/n → authenticate/install → recheck → resume once with original options/caller privileges → HTTP reads/writes/shutdown. Require missing-PHP and missing-SQLite/mbstring recovery on Ubuntu 22.04/PHP 8.1 and 24.04/PHP 8.3.
- Preserve Step 2 consent, optional-selection, noninteractive, privilege and failure boundaries. Pre-execution refusal makes no changes; failed recovery starts no listener. Unsupported/configuration cases receive verified manual next actions; never promise rollback or automatically repair configuration.
- Release requires real disposable two-platform evidence, not WSL certification. Host installation, schema/deployment and other service integrations are excluded. Five stages estimated ≤1 hour each; split overruns before execution. After approval: new branch, grouped approved-plans/debug-log commit, then sequential verified stage commits with summary updates.

## Key Risks

- **High risk: wrong repair.** Read-only inspection confirms Jammy PHP 8.1 ownership/candidates and installed modules unavailable under alternate configuration. Stage 1 must distinguish absent/disabled/custom runtimes; ambiguity remains manual.
- **High risk: elevated/unsafe continuation.** Existing password-sudo evidence does not prove startup integration. Installer/launcher signal handlers overlap; Stage 3 must verify caller identity, arguments and signal handoff before dependent work.
- **High risk: false completion.** Checksum-verified 22.04.5 and existing 24.04.3 chroots are available; new recovery transactions remain unverified. Both exact user journeys and actionable fallback are release gates. Unresolved risks block dependents.

## Stage 1
- Goal: actionable recovery from startup and both aliases.
- Dependencies: planning commit; disposable targets.
- Expected changes: shared OS/runtime/package eligibility, concrete commands, enabling/PATH guidance, optional separation and limits.
- Verification approach: replay both transcripts; execute manual repairs on disposable 22.04; preserve 24.04/plain/NO_COLOR behavior.
- Risks or open questions: wrong CLI/source; validate ownership/configuration before recommending repair.
- Canonical components/API contracts touched: shell/PHP assessment, platform helper, report rendering.

## Stage 2
- Goal: confirmed explicit installation on both releases.
- Dependencies: Stage 1 mapping gate.
- Expected changes: Jammy/PHP 8.1 mapping in existing coordinator; shared eligibility and existing transaction guards.
- Verification approach: real missing-PHP/Git/extensions, optional-only/default-off, ready, disabled/custom runtime and root/password-sudo cases through both aliases.
- Risks or open questions: runtime replacement; reject ambiguous sources/mappings before execution.
- Canonical components/API contracts touched: coordinator, mappings, aliases, disposable harness.

## Stage 3
- Goal: prompted recovery continues startup once.
- Dependencies: Stage 2 installation/privilege gate.
- Expected changes: startup recovery context, reviewed Y/n consent, optional opt-in during required recovery, argument/caller preservation and signal handoff.
- Verification approach: Enter/Y/y with root/password-sudo on both targets; check server identity, original port/log options, HTTP writes/reads and shutdown.
- Risks or open questions: unsafe continuation; validate arguments first, restore signal handling, require package success and fresh readiness.
- Canonical components/API contracts touched: `v3 start`, `local_server_dependencies_install(context)`, checks, launcher; retain default explicit-install behavior.

## Stage 4
- Goal: refusal/failure cannot launch or mutate unexpectedly.
- Dependencies: Stage 3 continuation gate.
- Expected changes: close recovery gaps; extend existing PTY/process tests.
- Verification approach: N/n/invalid/EOF, INT/TERM, redirected I/O, denied/cancelled sudo, optional-only, changed-batch decline, network/lock/partial failure and remaining restrictions; snapshot state and check listeners/children.
- Risks or open questions: active package children; defer reassessment, block startup, report partial changes/manual recovery without rollback.
- Canonical components/API contracts touched: coordinator, launcher signals, confirmation/lifecycle tests.

## Stage 5
- Goal: accept/document the complete user outcome.
- Dependencies: Stage 4 recovery gates.
- Expected changes: durable evidence, setup/help/reference and checklist updates, implementation summary.
- Verification approach: clean-image startup-to-read/write/shutdown for both failures on both releases; manual fallback walkthroughs, alias/help regressions, links and evidence audit.
- Risks or open questions: overclaiming support; publish exact environments; either user-journey failure blocks release.
- Canonical components/API contracts touched: disposable/runtime harnesses, canonical docs, guidance checklist, Step 4 summary.
