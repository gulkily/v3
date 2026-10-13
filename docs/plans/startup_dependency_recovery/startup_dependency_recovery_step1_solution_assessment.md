> **Feature plan:** [Step 1](./startup_dependency_recovery_step1_solution_assessment.md) · [Step 2](./startup_dependency_recovery_step2_feature_description.md) · [Step 3](./startup_dependency_recovery_step3_development_plan.md) · [Step 4](./startup_dependency_recovery_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Please write Step 1 for the section titled "Fix the discovered guidance gaps".

It would be nice if when I run `./v3 start` and a dependency is missing, I am prompted with the option to Y/n automatic recovery (provided I can sudo).

## Understood Intent

Address the [guidance gaps](../local_server_dependency_recovery_checklist.md#fix-the-discovered-guidance-gaps), including the user's Ubuntu 22.04/PHP 8.1 failures. The new request supersedes the earlier noninteractive-startup boundary for terminal sessions. Automatic recovery on this user's platform therefore needs to join this slice, rather than remain a later extension. Resuming startup after successful recovery is the recommended interpretation below.

## Problem

Startup detects unavailable dependencies but neither provides sufficiently concrete repairs on the user's platform nor offers an authorized recovery path at the point of failure.

## Option A — Actionable guidance and a separate install command

Identify the platform/runtime and provide verified installation or configuration instructions, directing supported users to the explicit installer.

- Pros: Smallest change; improves both startup and diagnostic messages.
- Cons: Does not meet the requested startup prompt; requires another command and retry.

## Option B — Prompt, recover, then require a manual restart

Offer reviewed package recovery during interactive startup, then recheck and tell the user to repeat their original startup command.

- Pros: Meets the prompt request; reuses the installer and retains a distinct startup retry.
- Cons: Adds a manual step even after successful recovery; requires Ubuntu 22.04 installer support to help this user.

## Option C — Prompt, recover, recheck and continue startup

Combine actionable guidance with interactive package recovery, then resume the original startup request only when installation succeeds and required checks pass.

- Pros: Completes the user's ordinary startup journey; preserves their arguments and avoids another command.
- Cons: Must preserve caller privileges, manage cancellation/failure without starting the server, and verify two platform mappings.

## Recommendation and completion boundary

Choose **Option C**, retaining Option A's manual guidance.

- Include Ubuntu 22.04/PHP 8.1 and preserve Ubuntu 24.04/PHP 8.3 distribution packages. Explain unsupported combinations and their manual recovery path.
- Show OS/CLI, required transaction, privileges and system effects before `Recover missing dependencies and continue startup? [Y/n]`. Enter accepts; `n`, EOF or pre-execution cancellation leaves packages untouched and stops startup. Reconfirm changed transactions after metadata refresh.
- Prompt only for verified, installable required failures in a terminal. Support root and password-authenticated sudo; authenticate after consent. Sudo presence does not prove authorization; an uncached password does not prove ineligibility. Denied/cancelled authentication produces manual guidance without installation.
- Keep redirected startup and ordinary diagnostics read-only/noninteractive. Optional intl is separate, unselected by default and nonblocking; optional-only limitations do not trigger recovery.
- Share recovery knowledge across startup, both aliases and the installer: concrete package commands for missing packages, specific enabling/PATH instructions for configuration problems. Do not automatically rewrite configuration, replace custom runtimes or relax host restrictions.
- After successful installation and fresh required readiness, resume once with original address/logging options and caller privileges; elevate package operations only. Failure/interruption reports partial-change risks and manual recovery, without rollback promises, retry loops or startup.
- Verify both reported failure states, actionable fallback, consent/privilege/output modes and real install-to-HTTP-read/write/shutdown on disposable matching environments. Keep WSL evidence distinct; update help/docs and checklist claims accordingly.

One startup-to-usable-server slice, reusing existing recovery machinery; no schema/deployment changes. Validate fit within one day/eight stages before implementation, without silently deferring the user's Ubuntu 22.04 outcome again. This assessment authorizes no implementation or host package changes.
