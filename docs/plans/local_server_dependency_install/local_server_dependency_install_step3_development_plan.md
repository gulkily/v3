> **Feature plan:** [Step 1](./local_server_dependency_install_step1_solution_assessment.md) · [Step 2](./local_server_dependency_install_step2_feature_description.md) · [Step 3](./local_server_dependency_install_step3_development_plan.md) · [Step 4](./local_server_dependency_install_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Both diagnostic aliases accept `--install`: assess, review required/explicitly selected optional packages, confirm, install, recheck, and guide manual startup retry through local reads/writes/shutdown. Ordinary checks/startup stay read-only; no unattended bypass.
- Ubuntu 24.04/APT distribution PHP 8.3 only; unsupported/configuration cases retain manual recovery. No schema, deployment, external-service, or broader-platform changes. Release requires real installation/recovery evidence in a disposable target environment.
- Five stages, each <=1 hour; split oversized work before implementation. After `Approved Step 3`, create a feature branch and approved-plans-only first commit; verify, summarize, and commit each stage before the next.

## Key Risks

- **High risk: wrong runtime/packages.** Existing probes expose display text; add structured capability facts, verify package ownership/origin, and reject ambiguous mappings before execution. Installed-but-disabled capabilities require configuration guidance, not reinstallation.
- **High risk: system side effects.** Validate confirmation before metadata refresh, privilege elevation, or installation; distinguish untouched cancellation from interrupted transactions. Review APT's resolved changes, reject removals/runtime switches, preserve configuration, and never promise rollback.
- **High risk: false readiness evidence.** Workspace inspection found Ubuntu 22.04/PHP 8.1, not the target. Require disposable 24.04 availability before dependent installer stages; retain package outcome separately from shared reassessment. Simulations alone cannot satisfy release.

## Stage 1
- Goal: review accurate batches through both CLI aliases without execution.
- Dependencies: approval/planning commit; disposable Ubuntu 24.04 test environment available.
- Expected changes: `--install` dispatch and shared structured assessment; `local_server_dependencies_install()` coordinator; allowlisted required mappings, package ownership and missing-PHP/PATH discrimination. Review-only stage reports installation unavailable.
- Verification approach: real package metadata/ownership plus missing-PHP, disabled-extension, custom-runtime, unsupported-platform, and alias fixtures; existing read-only regressions.
- Risks or open questions: ambiguous mapping could target another CLI; validate identities early and fall back to manual guidance before Stage 2.
- Canonical components/API contracts touched: `v3`, shared checker/PHP probe, platform helper, new install coordinator; preserve existing report modes/statuses.

## Stage 2
- Goal: complete confirmed required-package installation and reassessment.
- Dependencies: Stage 1 mapping/environment gates passed.
- Expected changes: terminal-only confirmation, root/sudo handling, reviewed APT transaction, one required batch, fresh shared assessment, original-runtime verification, readiness/package outcomes and retry guidance.
- Verification approach: disposable real missing-PHP/Git/extensions install; root/sudo, decline/EOF, redirected input, no privileges, already-ready and unchanged application-state checks.
- Risks or open questions: refresh and dependency resolution can change system state/batch; disclose refresh, validate resolved changes before installation, and reconfirm changes or stop. Never elevate ordinary checks.
- Canonical components/API contracts touched: coordinator, shared reports, alias dispatch; success requires successful requested installation and required readiness.

## Stage 3
- Goal: complete optional choices and failed/interrupted recovery.
- Dependencies: Stage 2 confirmed-install path verified.
- Expected changes: explicit optional package selection before final batch confirmation; accurate partial-failure/interrupt guidance and reassessment when possible. Leave noninstallable optional restrictions manual.
- Verification approach: optional-only/declined choices, unavailable package, refresh/lock/network/install failures, signals, remaining restrictions, and changed runtime identity.
- Risks or open questions: interruption can leave changes or an active package process; validate signal/child handling and avoid automatic retry, rollback, or package-database repair.
- Canonical components/API contracts touched: coordinator, optional capability mappings, outcome rendering and recovery tests.

## Stage 4
- Goal: prove install-to-usable-server recovery across both aliases.
- Dependencies: Stage 3 failure/cleanup gates passed.
- Expected changes: repeatable disposable-environment installation tests extending existing shell/Python fixtures.
- Verification approach: real install/recheck then manual startup with original port/log options, HTTP reads/writes/shutdown; regression matrix for no mutation, PTY/plain/NO_COLOR output, optional degradation, and independent help.
- Risks or open questions: mocks hide package side effects; require real transactions and package/application snapshots before acceptance.
- Canonical components/API contracts touched: dependency shell/runtime tests, isolated install harness, existing local-write/lifecycle coverage.

## Stage 5
- Goal: publish setup/recovery guidance and release evidence.
- Dependencies: Stage 4 acceptance passed.
- Expected changes: README, CLI help/reference, checklist and implementation summary; document confirmation, privileges, statuses, partial changes, external launcher setup and scope limits.
- Verification approach: docs/help/link checks and evidence audit against the complete contract.
- Risks or open questions: documentation could overclaim support; list exact tested environment and keep other platforms unchecked.
- Canonical components/API contracts touched: canonical setup/CLI docs, dependency/platform checklists, plan navigation and summary.
