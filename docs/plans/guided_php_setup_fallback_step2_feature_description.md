> **Feature plan:** [Step 1](./guided_php_setup_fallback_step1_solution_assessment.md) · [Step 2](./guided_php_setup_fallback_step2_feature_description.md) · [Step 3](./guided_php_setup_fallback_step3_development_plan.md) · [Step 4](./guided_php_setup_fallback_step4_implementation_summary.md)

## Problem

The existing missing-PHP fallback points to general setup documentation, leaving users to find instructions for their environment. Startup should provide relevant manual installation or PATH-repair guidance that helps them retry successfully.

## User Stories

- As a new contributor, I want setup instructions for my supported environment so that I can install the PHP runtime needed to start v3.
- As a user with PHP already installed outside PATH, I want repair guidance so that I can make it available without an unnecessary installation.

## Core Requirements

- When PHP is unavailable on PATH, explain the prerequisite, mention possible existing installations, and give manual PATH-repair and availability-check guidance.
- Initially recognize Ubuntu 24.04 with APT and display relevant manual package setup instructions, including the PHP CLI and core SQLite/multibyte capabilities. Instructions must explain any required privileges and tell users to retry their original startup command.
- Unrecognized environments, other Ubuntu releases, unavailable APT, and failed environment detection retain useful platform-neutral instructions and the stable README setup reference.
- Display guidance on standard error and exit nonzero without prompting, running installation commands, changing PATH or shell profiles, or automatically retrying. Behavior must remain usable in unattended execution.
- Preserve existing-PHP startup, all existing startup options, and PHP-independent help. Environment recognition must not add a prerequisite that prevents the generic fallback from working.

## Delivery Scope

- **Work type:** Application change with supporting setup documentation and focused verification.
- Extend the missing-PHP `./v3 start` guidance only. Automatic installation, other platform-specific recipes, runtime validation for existing PHP installations, other launcher commands, and the [dependency checklist](dependency_fallback_checklist.md) are separate future work.

## Completion Boundary

Entry is the usual startup command without PHP available. Supported users receive relevant instructions; everyone else receives generic recovery guidance. Users complete setup or PATH repair themselves, confirm PHP availability, and retry the same command to reach the existing local server. Release requires guidance-selection and failure checks, preserved-argument checks, validated setup instructions, and a real PHP server smoke test after restoring availability; no production deployment is required.

## Risks

- **Incorrect environment recognition:** Users could receive unsuitable commands. Before Step 3, validate a bounded Ubuntu-release/APT recognition policy; use generic guidance for ambiguous or unreadable environment information.
- **Incomplete or stale recipe:** Installation may leave users unable to start. Before Step 3, check the proposed packages and runtime prerequisites against official distribution sources and project usage; document required capabilities and manual recovery.
- **Unnecessary installation:** PHP may already exist outside PATH. Before Step 3, review message ordering; present the existing-installation/PATH check before suggesting packages, without promising to find every installation.
- **Missing detection utilities:** Extra commands could break the original fallback. Before Step 3, review launcher prerequisites and test controlled PATH cases; detection failure must retain generic guidance.

## Shared Component Inventory

- `v3`: Extend the canonical missing-PHP recovery helper and retain the startup argument contract.
- `README.md` PHP setup: Extend the existing stable reference with supported manual setup and PATH recovery.
- `tests/missing_php_startup_test.sh`: Extend existing launcher checks for supported and generic guidance.
- `scripts/dev_server.php`: Reuse unchanged after users restore PHP and retry. No browser UI or API renders this failure.

## Simple User Flow

1. Run the usual startup command without PHP available.
2. Read PATH-repair guidance and the applicable manual installation instructions.
3. Complete setup manually and verify PHP availability in the same terminal.
4. Retry the original startup command and start the existing server.

## Success Criteria

- Supported, unsupported, and detection-failure cases produce the appropriate actionable guidance, nonzero exit, and no shell-level PHP-not-found error.
- Missing-PHP runs never prompt or execute installation/environment-modification commands.
- Restoring PHP and retrying reaches a responding local server with original options preserved; existing-PHP startup and independent help retain their behavior.
