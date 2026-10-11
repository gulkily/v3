> **Feature plan:** [Step 1](./local_server_dependency_checks_step1_solution_assessment.md) · [Step 2](./local_server_dependency_checks_step2_feature_description.md) · [Step 3](./local_server_dependency_checks_step3_development_plan.md) · [Step 4](./local_server_dependency_checks_step4_implementation_summary.md)

## Problem

`./v3 start` explains missing PHP, but other unavailable local-server capabilities can cause late failures. Users need consistent readiness checks and recovery guidance before startup, plus a way to inspect dependencies independently.

## User Stories

- As a local developer, I want startup to identify missing prerequisites so that I can fix my environment before application state changes.
- As a local operator, I want a separate dependency report so that I can verify setup without starting the server.

## Core Requirements

- Both `./v3 start` and `./v3 check-dependencies` assess the selected PHP CLI runtime and local-server prerequisites: Git, supported PHP version, PDO/PDO SQLite, mbstring, and process execution required for server lifecycle and local Git operations. Required failures block startup before application loading, log-file creation, bootstrap, or rebuild; report all independently assessable failures and identify checks unavailable without PHP.
- The diagnostic command reports available, missing/disabled, and optional capabilities; returns zero when required capabilities pass and nonzero otherwise; and performs no server launch or persistent application changes. Dependency readiness is not a guarantee that a checkout, database, or listening address is usable.
- Missing `stty`, optional terminal shell execution, PCNTL, or intl produces guidance about reduced behavior rather than a startup blocker. Plain logging, ordinary local reads/writes, and reliable shutdown remain usable.
- Give manual installation, enabling, or PATH recovery guidance for the selected runtime; distinguish host restrictions from missing executables and dependency failures from permissions, repository/database errors, or authentication. Preserve startup options on manual retry; never prompt, install, or retry automatically, including unattended runs.
- Provide specific setup guidance for Ubuntu 24.04 with APT, retaining generic guidance elsewhere without promising unverified platform support. Document Bash and pre-check utilities, including `dirname` and `cat`, for failures that prevent launcher diagnostics. Keep help and unrelated commands independent of these checks.

## Delivery Scope

Application change with accompanying local setup/help documentation. Covers only the local-server items in the [checklist](../dependency_fallback_checklist.md), including optional intl; other command dependencies, deployment, remote/provider credentials, and automatic installation are excluded.

## Completion Boundary

Enter through either command, receive actionable diagnostics, repair setup manually, and retry `./v3 start` with original options to serve local requests and complete ordinary local writes and shutdown. Release requires matching assessments, safe failure before mutation, and verified recovery and optional degradation.

## Risks

- **Incorrect PHP minimum:** could reject valid setups or allow load failures. Validate source compatibility before planning stages; establish one evidenced minimum for checks and local guidance.
- **Optional capability assumptions:** can crash terminal output or leave child servers running. Validate existing terminal/shutdown paths before staging; require a working fallback without PCNTL, `stty`, or terminal shell execution.
- **Runtime/setup mismatch:** can direct users to modify another PHP installation. Validate runtime identity and existing platform guidance before staging; tie extension guidance to the selected CLI and retain generic recovery.

## Shared Component Inventory

- Launcher missing-PHP messages and PHP platform helper: extend and reuse their recovery guidance.
- Development-server terminal/logging surface: extend optional degradation and shutdown behavior.
- README PHP setup and launcher help: extend canonical local setup and command discovery.
- New dependency report: needed for independent inspection; shares assessment and guidance with startup. No web UI/API changes.

## Simple User Flow

1. Run `./v3 check-dependencies` or `./v3 start`.
2. Read required failures and optional limitations; complete manual recovery.
3. Recheck, start with original options, use the local server, and stop it.

## Success Criteria

- Both commands agree on required readiness and optional limitations; diagnostics leave application state unchanged.
- Missing/disabled required capabilities fail clearly before mutation; restored capabilities permit successful startup, local reads/writes, and shutdown.
- Optional-capability absence permits usable startup and cleanup; unattended guidance never prompts or installs.
