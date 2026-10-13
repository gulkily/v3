> **Feature plan:** [Step 1](./local_server_dependency_install_step1_solution_assessment.md) · [Step 2](./local_server_dependency_install_step2_feature_description.md) · [Step 3](./local_server_dependency_install_step3_development_plan.md) · [Step 4](./local_server_dependency_install_step4_implementation_summary.md)

## Original Query

Also, it would be nice if there was an option to do all the dependency installs at once once check-dependencies runs. Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md for this that we can pick up in a new chat.

## Understood Intent

Extend the local-server dependency report with an explicit option to install missing dependencies together, then verify readiness. Both `./v3 check-dependencies` and its `dependency-check` alias should offer the same behavior. This document is a handoff for a new chat; installation is not implemented or authorized by this planning request.

## Problem Statement

The report identifies missing local-server dependencies but requires users to run recovery commands themselves, often separately.

## Option A: One combined manual command

After checking dependencies, print one installation command for all missing installable prerequisites, with optional packages listed separately.

- Pros: Small scope; easy to inspect and copy; avoids launcher privilege handling.
- Cons: Does not provide an installation option within the dependency-check flow; users still rerun verification themselves.

## Option B: Explicit install mode with a reviewed batch

Offer an opt-in installation mode that checks dependencies, shows the proposed packages, requests confirmation, installs the batch, and reruns the checks.

- Pros: Completes the check–install–verify flow; batches packages; ordinary checks remain read-only.
- Cons: Requires package-manager, privilege, cancellation, and partial-failure handling; some unavailable capabilities require configuration rather than installation.

## Option C: Interactive offer after every check

When dependencies are missing, offer installation immediately after the report without a separate mode.

- Pros: Discoverable; convenient during first-time setup.
- Cons: Changes the diagnostic command's interaction contract; complicates redirected and unattended use.

## Recommendation

Choose **Option B**, initially for the existing Ubuntu 24.04/APT local-server setup.

- Keep ordinary checks and startup free of installation prompts or package changes; installation requires an explicit mode and confirmation of the concrete package batch.
- Batch missing required packages for the selected PHP runtime; allow optional packages through an explicit choice. Do not install replacements for disabled functions, host restrictions, PATH problems, or unavailable terminal access.
- Handle missing PHP as well as missing PHP extensions and Git. Explain launcher prerequisites that prevent the command itself from running.
- Use supported platform/package mappings; on other systems, retain actionable manual guidance. Never silently switch the selected PHP runtime or rewrite host configuration.
- Explain administrator privileges and system package changes before execution. Support existing root sessions; cancellation must leave the system untouched.
- Recheck after installation; report remaining limitations and partial package-manager failures accurately. Package operations may have made changes even when installation fails; do not promise automatic rollback.
- Complete vertical slice: run the explicit mode, review the missing-package batch, confirm, install, verify, then retry the original startup command. Cover decline, noninteractive use, unavailable privileges, unsupported platforms, failed installs, and already-ready systems.
- Keep broader command dependencies, remote credentials, deployment setup, and configuration repair outside this first slice. Target one day or eight stages.

## New-chat Handoff

Steps 1 and 2 are approved; Step 3 is drafted and awaits `Approved Step 3` before implementation. All planning edits remain uncommitted until the Step 4 feature branch and planning commit. The requested [platform support checklist](./local_server_dependency_platform_support_checklist.md) records future candidates without expanding the initial installer scope. Inspect `scripts/local_server_dependencies.sh`, `scripts/check_local_server_dependencies.php`, and `scripts/php_setup_guidance.sh`; existing readiness checks are shared by startup and the report. Keep the [dependency checklist](../dependency_fallback_checklist.md) updated. The prior [local-server plan](../local_server_dependency_checks/local_server_dependency_checks_step1_solution_assessment.md) intentionally specified manual recovery; this proposal changes that boundary only for an explicitly opted-in mode.
