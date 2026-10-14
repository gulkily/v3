> **Feature plan:** [Step 1](./local_server_dependency_install_step1_solution_assessment.md) · [Step 2](./local_server_dependency_install_step2_feature_description.md) · [Step 3](./local_server_dependency_install_step3_development_plan.md) · [Step 4](./local_server_dependency_install_step4_implementation_summary.md)

## Problem

Local-server checks identify missing dependencies, but users must install them manually and repeat verification. An explicit batch-install option should complete that recovery flow while preserving ordinary read-only diagnostics.

## User Stories

- As a local developer, I want to review and install missing dependencies together so that I can finish setup in one flow.
- As an operator, I want ordinary checks to remain noninteractive and read-only so that diagnostics remain safe to automate.

## Core Requirements

- Both `check-dependencies` and `dependency-check` offer the same explicit install mode: assess, show the concrete required package batch and system-change implications, obtain confirmation, install, and recheck. Optional packages require a separate explicit choice; ordinary checks and startup never offer installation prompts.
- Initially support Ubuntu 24.04/APT and its distribution PHP 8.3 packages for missing PHP, matching required extensions, and Git. Bind extension choices to the selected CLI; retain manual guidance for unverified/custom runtimes, incompatible versions, PATH/configuration problems, disabled functions, and host restrictions. Never silently switch runtimes or rewrite host configuration.
- Explain administrator privileges before execution and support existing root sessions. Decline, cancellation before execution, and noninteractive invocation perform no package changes; this slice provides no unattended confirmation bypass. Already-ready systems make no required package changes. Explain launcher prerequisites externally when the launcher cannot run.
- Reassess with the shared checks after a package attempt whenever execution can continue. Distinguish installation failure from remaining readiness failures, acknowledge possible partial system changes, and give manual recovery and the original startup retry instructions. Return success only when the requested installation succeeds and required readiness passes; do not promise rollback or start the server automatically.
- Preserve existing report symbols, command emphasis, plain/`NO_COLOR` output, and readiness limits. Unsupported platforms receive actionable manual guidance. Dependency assessment and verification do not bootstrap, rebuild, or write application state.

## Delivery Scope

Application change with CLI/setup documentation and recovery tests; local-server dependencies only. The separate [platform checklist](./local_server_dependency_platform_support_checklist.md) is a future backlog, not additional installer scope. Excludes deployment, remote credentials, configuration repair, repository setup, and other command dependencies.

## Completion Boundary

Enter explicit mode, review and confirm the batch, install, verify, then manually retry startup with original options and complete local reads/writes and shutdown. Release requires verified success, decline, noninteractive, unavailable-privilege, unsupported-platform, partial-failure, and already-ready paths.

## Risks

- **Wrong runtime targeted:** packages may leave the selected CLI unchanged. Before Step 3, validate runtime/package ownership and missing-PHP selection; constrain automatic mappings and retain manual recovery.
- **Package side effects:** cancellation or failure can leave partial changes. Before Step 3, validate confirmation, privilege, and interruption boundaries; distinguish pre-execution cancellation from an interrupted package transaction.
- **Misleading verification:** successful APT execution may leave capabilities unavailable. Before Step 3, validate shared reassessment and outcome handling; retain both package-operation and readiness results.

## Shared Component Inventory

- `v3` dispatch/help and both aliases: extend with the explicit mode.
- Shared shell checker, standalone PHP probe, and PHP platform/setup helper: reuse assessments, runtime identity, mappings, and recovery guidance.
- Existing terminal/plain reports and README/CLI reference: extend with batch review, confirmation, and installation outcomes. A new install coordinator is needed because existing checks are read-only; no web UI/API changes.

## Simple User Flow

1. Run the explicit install mode and review missing dependencies and optional choices.
2. Confirm the concrete batch or decline without changes.
3. Read installation and readiness results, resolve remaining issues, and retry the original startup command.

## Success Criteria

- Both aliases produce equivalent batches and outcomes; ordinary checks/startup remain read-only.
- Confirmed installation restores usable local operation; decline and unattended use change no packages.
- Partial failures and unsupported environments provide accurate recovery; optional limitations remain nonblocking.
