# Dependency fallback checklist

Future dependency checks should explain what is missing, help users recover, and let them retry the affected workflow. Prioritize Git and PHP capabilities needed for local startup; check optional tools only when their workflow is selected. This backlog supplements the [guided PHP setup assessment](guided_php_setup_fallback/guided_php_setup_fallback_step1_solution_assessment.md) without expanding its scope.

Unchecked items represent future work, including improvements to existing error messages.

## Local-server delivery progress

This delivery covers only `./v3 start` and `./v3 check-dependencies`; broader workflow items below remain unchecked when their full scope is not delivered. Evidence and stage results: [implementation summary](local_server_dependency_checks/local_server_dependency_checks_step4_implementation_summary.md).

- [x] Git/PHP executable availability, unusable executables, and supported PHP version in both entry points; preserve manual retry options. PHP 8.1 source compatibility and local bootstrap/write behavior verified.
- [x] PDO/PDO SQLite and mbstring availability with runtime-specific recovery guidance. Verified missing extensions, disabled mbstring functions, and restored in-memory SQLite opening.
- [x] Required process execution capabilities, including harmless execution probes and restriction guidance. Verified disabled `proc_open`/`exec` and restored PHP child, shell, and Git execution.
- [ ] Optional terminal/PCNTL support with plain logging and reliable shutdown.
- [ ] Optional intl normalization with reduced-behavior guidance.
- [ ] End-to-end missing/disabled/restored, state-preservation, unattended, and local read/write verification.
- [ ] Launcher prerequisites, platform setup instructions, command discovery, and readiness limits documented and verified.

## Local startup and core runtime

- [ ] **Git executable and repository setup.** Detect missing Git before local repository bootstrap, rebuilds, or writes. Distinguish missing Git from an invalid checkout, permissions, and commit-identity failures; provide installation/PATH guidance and a retry path. Evidence: [LocalRepositoryBootstrap](../../src/ForumRewrite/Support/LocalRepositoryBootstrap.php), [ReadModelMetadata](../../src/ForumRewrite/ReadModel/ReadModelMetadata.php), and [LocalWriteService](../../src/ForumRewrite/Write/LocalWriteService.php).
- [ ] **Supported PHP version.** Detect an incompatible interpreter before loading application code and explain the supported minimum. The deployment runbook currently states PHP 8.1+; reconcile that claim with the code before encoding a version check. Keep this separate from the existing missing-PHP check. Evidence: [production assumptions](../runbooks/production_deploy.md), [launcher](../../v3).
- [ ] **PDO and the PDO SQLite driver.** Check the selected PHP runtime can open SQLite before read-model work; explain how to enable the matching extension. Distinguish a missing driver from a missing, unwritable, or corrupt database. The standalone `sqlite3` executable is not a replacement for PDO SQLite. Evidence: [ReadModelBuilder](../../src/ForumRewrite/ReadModel/ReadModelBuilder.php), [PublicOfflineSnapshotBuilder](../../src/ForumRewrite/Offline/PublicOfflineSnapshotBuilder.php).
- [ ] **PHP mbstring.** Check multibyte functions before parsing canonical text or handling Unicode; explain installation/enabling for the selected PHP runtime. Evidence: [GenericTextRecordParser](../../src/ForumRewrite/Canonical/GenericTextRecordParser.php), [UnicodeTextPolicy](../../src/ForumRewrite/Support/UnicodeTextPolicy.php).
- [ ] **PHP process execution capabilities.** Detect unavailable or disabled `proc_open`, `exec`, and applicable shell functions before server startup or Git operations. Describe host restrictions separately from missing executables; point users to host configuration or another supported environment. Evidence: [development server](../../scripts/dev_server.php), [local bootstrap](../../src/ForumRewrite/Support/LocalRepositoryBootstrap.php).
- [ ] **Terminal utilities and optional signal support.** Make missing `stty` or unavailable PHP PCNTL support degrade cleanly where possible. Preserve plain logging and reliable server shutdown; do not make terminal cosmetics a universal installation requirement. Evidence: [development server](../../scripts/dev_server.php).
- [ ] **Launcher prerequisites.** Document Bash and the utilities used before runtime checks, notably `dirname` and `cat`. A missing Bash interpreter prevents the launcher from displaying its own guidance, so cover that case in setup documentation. Evidence: [launcher](../../v3).

## Dependencies for optional commands

- [ ] **whiptail and an interactive terminal for `./v3 tui`.** Extend the existing missing-tool guidance with setup instructions, respecting `V3_TUI_WHIPTAIL`; preserve the existing status/private-config fallback. A noninteractive terminal requires a different workflow rather than package installation. Evidence: [terminal operator UI](../../scripts/terminal_operator_ui.sh).
- [ ] **Node.js, npm, playwright-core, and Chromium for the OpenPGP canary.** Diagnose each prerequisite separately, provide dependency setup guidance, and respect the explicit browser path/environment override. Missing npm concerns package setup, not ordinary forum startup. Preserve the production-write confirmation requirement. Evidence: [package manifest](../../package.json), [canary](../../scripts/openpgp_production_canary.mjs).
- [ ] **PHP zip extension for thread archives.** Extend the existing `ZipArchive` error with runtime-specific enabling instructions; verify availability before archive work. Evidence: [archive command](../../scripts/archive_thread.php).
- [ ] **PHP zlib and the gzip executable for instance imports.** Distinguish missing decompression/checksum tools from a corrupt archive, and check prerequisites before expensive download/import work. Evidence: [RepositoryArchive](../../src/ForumRewrite/Import/RepositoryArchive.php), [instance import prerequisites](../runbooks/instance_content_import.md).
- [ ] **tar and gzip for repository archive imports.** Check the archive listing/extraction tools before mutation; explain which tool is missing and how to retry. Do not assume this command uses the same extraction path as instance import. Evidence: [repository import command](../../scripts/import_repository_archive.php).
- [ ] **PHP network streams, TLS support, and trust configuration.** For HTTPS imports and provider requests, distinguish disabled URL access or missing HTTPS support from certificate, DNS, connectivity, and authentication failures. Provide configuration recovery without disabling certificate verification. Evidence: [InstanceArchiveDownloader](../../src/ForumRewrite/Import/InstanceArchiveDownloader.php), [provider transport](../../src/ForumRewrite/Llm/OpenAiCompatibleStructuredChatProvider.php).
- [ ] **Codex executable and authentication for handoffs.** Respect `--codex-bin`; explain unavailable executables and authentication/configuration failures before consuming queued work where possible. Keep this optional for ordinary forum use. Evidence: [runner command](../../scripts/run_codex_handoffs.php), [CodexHandoffRunner](../../src/ForumRewrite/Codex/CodexHandoffRunner.php).
- [ ] **Configured text editor for private-config editing.** Validate the chosen editor before launching it and provide the existing view/configuration alternatives; do not require an editor for read-only commands. Evidence: [private config command](../../scripts/write_private_config.php).
- [ ] **PHP intl for Unicode normalization.** Explain the existing reduced Unicode behavior when `Normalizer` is unavailable; offer enabling instructions rather than blocking all startup. Evidence: [UnicodeTextPolicy](../../src/ForumRewrite/Support/UnicodeTextPolicy.php), [UnicodeRiskInspector](../../src/ForumRewrite/Analysis/UnicodeRiskInspector.php), [production Unicode guidance](../runbooks/production_deploy.md).

## Deployment and operator prerequisites

- [ ] **Apache and mod_rewrite for the documented production deployment.** Add deployment diagnostics and recovery guidance without requiring Apache for the local PHP development server. Evidence: [production runbook](../runbooks/production_deploy.md).
- [ ] **cron service and crontab for scheduled queue processing.** Explain scheduling prerequisites and retain manual `task-queue run` operation. The current cron command prints installation instructions; verify the scheduler separately from generating that text. Evidence: [task queue command](../../scripts/task_queue.php).
- [ ] **sqlite3 executable for documented private database backups.** Add operator setup guidance without treating it as a core application dependency. Evidence: [backup procedure](../runbooks/production_deploy.md).
- [ ] **Git credentials for remote operations.** Distinguish authentication failure from missing Git and network failure; point to credential setup without embedding secrets in remote URLs or logs. Local runtime Git operations should not require GitHub credentials. Evidence: [FDP sync launcher path](../../v3).
- [ ] **Provider configuration and credentials for LLM features.** Guide users to private-config setup and provider verification; preserve offline operation where supported. Treat missing credentials as configuration, not software installation. Evidence: [private config command](../../scripts/write_private_config.php), [provider check](../../scripts/test_agent_reply_provider.php).

## Acceptance checklist for each future fallback

- [ ] Name the affected workflow, supported environments, and exact capability being checked.
- [ ] Distinguish missing software, PATH/configuration errors, permissions, authentication, and runtime failures.
- [ ] Provide guidance readable without the missing dependency and preserve manual recovery.
- [ ] Offer installation only through an explicit user choice; unattended runs must not prompt or install implicitly.
- [ ] Preserve original arguments on retry; verify missing, declined, failed, unsupported, and restored cases.
- [ ] Keep optional dependencies from blocking unrelated commands and help.
- [ ] Verify failures occur before avoidable state changes; document partial changes and recovery for installation attempts.
