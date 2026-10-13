> **Feature plan:** [Step 1](./startup_dependency_recovery_step1_solution_assessment.md) · [Step 2](./startup_dependency_recovery_step2_feature_description.md) · [Step 3](./startup_dependency_recovery_step3_development_plan.md) · [Step 4](./startup_dependency_recovery_step4_implementation_summary.md)

Debug mode: enabled

## Problem

The user's Ubuntu 22.04 startup failures identify missing PHP/extensions but leave recovery vague. Startup should offer applicable, authorized recovery and continue successfully, while giving concrete manual instructions when automation cannot help.

## User stories

- As a local developer, I want a reviewed Y/n recovery option during failed startup so that I can reach a working server without researching package names or restarting manually.
- As an operator, I want unattended checks and unsupported repairs to remain nonmutating so that diagnostics never trigger unexpected installations.

## Core requirements

- Identify OS/release and selected CLI when available. Share accurate package/configuration guidance across startup, both diagnostic aliases and installation. Support Ubuntu 22.04/distribution PHP 8.1 and preserve Ubuntu 24.04/distribution PHP 8.3. Missing packages receive verified commands; installed-but-disabled modules, PATH errors, custom runtimes and unsupported environments receive specific manual next steps and explicit automation limits.
- For installable required failures with terminal input/output and root or potentially usable sudo, show the resolved required batch, privileges and system effects before a Y/n recovery-and-startup confirmation. Enter/Y/y accepts; N/n declines; invalid input does not execute; EOF or pre-execution cancellation stops without changes. Authenticate sudo only after consent; password authentication is supported. Missing/denied privileges produce manual guidance. Reconfirm changed transactions after metadata refresh; declining then may leave refreshed metadata but no installation.
- Keep redirected startup and ordinary diagnostics read-only/noninteractive. Preserve explicit installation through both aliases. Optional intl remains separately selected, off by default and nonblocking; optional-only limitations do not prompt during startup. Never automatically rewrite configuration, replace a custom runtime or relax host restrictions.
- Resume startup once only after the requested package operation succeeds and fresh required checks pass, preserving original address/logging options and caller privileges. Elevate package operations only. Refusal, failure, interruption or remaining required problems return nonzero without starting the server; report package outcome separately from readiness, possible partial changes and manual recovery, without automatic rollback or retry. Defer reassessment while package processes remain active.
- Preserve readable terminal/plain/NO_COLOR reports and independent help. Update README, CLI reference and checklist claims to match verified recovery, including external launcher prerequisites and manual verification/retry instructions.

## Delivery scope

Application change covering the [discovered guidance gaps](../local_server_dependency_recovery_checklist.md#fix-the-discovered-guidance-gaps), interactive startup recovery and the necessary Ubuntu 22.04 installer extension. Excludes broader platforms, automatic configuration repair, schema/deployment changes and installing packages on the user's host for testing.

## Completion boundary

From ordinary `./v3 start`, recover either reported failure state—missing PHP, or PHP 8.1 with missing SQLite/mbstring—then serve HTTP reads/writes and shut down cleanly. Release requires real disposable 22.04 and 24.04 evidence plus verified manual fallback; record WSL-specific claims separately.

## Risks

- **Wrong repair/runtime:** may install irrelevant packages. Before Step 3, validate mapping/ownership and disabled-versus-absent distinctions; retain manual recovery for ambiguity.
- **Privilege/consent error:** may mutate unexpectedly or run the server elevated. Before Step 3, validate terminal, password-sudo and continuation boundaries; isolate elevation and verify refusal leaves state untouched.
- **False completion:** supported-platform tests may again miss the user's problem. Before Step 3, confirm matching disposable environments and concrete expected recovery for both transcripts; require outcome evidence, not message-presence checks.

## Shared component inventory

- Extend existing `v3` startup/alias/help surfaces, shared shell/PHP assessment and platform guidance; use one recovery mapping.
- Reuse the install coordinator's transaction, privilege and failure handling and the launcher's lifecycle management; add startup integration, not a parallel installer.
- Extend shell/PTY/disposable-runtime tests and canonical setup/reference docs. No web UI/API surface changes.

## Simple user flow

1. Start normally; review the missing capabilities and applicable recovery.
2. Accept the reviewed batch and authenticate if needed, or receive actionable manual guidance.
3. Recheck and continue the original startup request; on failure, stop with recovery instructions.

## Success criteria

- Both reported states recover through startup on 22.04; 24.04 retains equivalent verified behavior.
- Decline/EOF/unattended paths make no package/application changes; failed recovery starts no listener.
- Successful recovery preserves caller privileges/options and completes HTTP thread/reply writes, reads and shutdown; manual guidance supplies a verified next action without package-name guessing.
