> **Feature plan:** [Step 1](./local_server_dependency_checks_step1_solution_assessment.md) · [Step 2](./local_server_dependency_checks_step2_feature_description.md) · [Step 3](./local_server_dependency_checks_step3_development_plan.md) · [Step 4](./local_server_dependency_checks_step4_implementation_summary.md)

## Completion Contract

- Shared checks protect `./v3 start` and power `./v3 check-dependencies`: required failures exit nonzero before application loading, logs, or bootstrap; diagnostics preserve application state.
- Manual repair/retry preserves options and restores local reads/writes and shutdown; optional limitations only warn. No automatic installation, prompts, retries, schema changes, or unrelated checks.
- Release: verified recovery, degradation, state preservation, and Ubuntu 24.04/APT and generic guidance. Production/external verification is excluded.
- Stages target <=1 hour. Step 4 requires a feature branch, approved-plans-only first commit, then verification, summary, and commit per stage.

## Key Risks

- **High risk:** late checks can permit writes; launcher ordering must be validated with state/log snapshots before integration is accepted.
- PHP mismatch can permit load failure: source uses PHP 8.1 features; PHP is unavailable here. Stage 1 requires a test runtime and minimum-compatibility evidence before dependents; bind guidance to the selected CLI.
- **High risk:** optional terminal/signal assumptions can crash output or strand child servers. Stage 3 gates recovery testing on guarded terminal operations and verified cleanup without PCNTL.

## Stage 1
- Goal: deliver both entry points for executable/version readiness.
- Dependencies: Step 3 approval, planning commit, and test-runtime availability.
- Expected changes: shared shell contract `local_server_dependencies_check(mode)` (`startup`/`report`, 0/1); Git/PHP/version checks and guidance in both commands before side effects; mark unavailable dependent checks.
- Verification approach: source/minimum-runtime compatibility; missing Git/PHP, incompatible/restored PHP, independent help, existing launcher regression and forwarding.
- Risks or open questions: a guessed minimum permits load failure; validate before enforcing it and stop dependent stages if unsupported.
- Canonical components/API contracts touched: `v3`, PHP setup helper, launcher tests, shared checker.

## Stage 2
- Goal: complete required runtime readiness in both commands.
- Dependencies: Stage 1 compatibility and runtime gate passed.
- Expected changes: standalone PHP probe for PDO/in-memory SQLite, mbstring, and server/Git process capabilities; aggregate failures with runtime-specific recovery.
- Verification approach: missing extensions, disabled functions, healthy runtime, unusable executables, matching reports, and state/log preservation.
- Risks or open questions: presence alone can hide execution restrictions; validate harmless capability probes and preserve operational error distinctions.
- Canonical components/API contracts touched: shared checker/probe and recovery guidance.

## Stage 3
- Goal: keep startup usable with optional capabilities absent.
- Dependencies: Stage 2 required checks passed.
- Expected changes: warnings for intl, PCNTL, terminal shell execution, and `stty`; guarded layout/restoration, plain logs, and child cleanup.
- Verification approach: real requests and SIGINT/SIGTERM with optional capabilities removed, terminal/redirected output, and no surviving listener.
- Risks or open questions: shutdown fallback may require launcher/server lifecycle changes; validate bounded cleanup before proceeding.
- Canonical components/API contracts touched: development server, launcher lifecycle, report; reuse Unicode fallback.

## Stage 4
- Goal: verify the complete repair-to-running-server flow.
- Dependencies: Stage 3 cleanup gate passed.
- Expected changes: isolated command/lifecycle regressions and updated launcher fixtures.
- Verification approach: missing/disabled/restored matrix, report agreement, state snapshots, unattended input, ports/log paths, HTTP reads/writes/shutdown; relevant local-write, Unicode, and logging tests.
- Risks or open questions: simulated probes can conceal runtime failures; require real PHP integration and isolated application state.
- Canonical components/API contracts touched: launcher, dependency/lifecycle, and local application/write tests.

## Stage 5
- Goal: make setup and recovery discoverable and finish release evidence.
- Dependencies: Stage 4 acceptance passed.
- Expected changes: README/help covering command, minimum, extensions, optional behavior, Bash/`dirname`/`cat`, retry, and readiness limits; evidence for completed checklist items only.
- Verification approach: guidance against runtime/platform fixtures, docs without PHP, links/help, acceptance evidence and platform limitations.
- Risks or open questions: misleading platform promises; retain generic guidance outside verified Ubuntu/APT instructions.
- Canonical components/API contracts touched: README, launcher help, checklist, summary/navigation.
