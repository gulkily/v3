> **Feature plan:** [Step 1](./local_server_dependency_checks_step1_solution_assessment.md) · [Step 2](./local_server_dependency_checks_step2_feature_description.md) · [Step 3](./local_server_dependency_checks_step3_development_plan.md) · [Step 4](./local_server_dependency_checks_step4_implementation_summary.md)

## Original Query

We did it for PHP, now please do dependency availability checks for all the other things in the checklist. Write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

For the local server only for now, please.

Which means `./v3 start`.

And maybe add a distinct dependency check command, please.

## Understood Intent

Extend the existing missing-PHP startup guidance to the local-server items in the [dependency fallback checklist](../dependency_fallback_checklist.md). Assess a separate command for checking those same prerequisites without starting the server; other commands and deployment prerequisites remain future work.

## Problem Statement

`./v3 start` detects missing PHP, but other unavailable runtime capabilities can cause later failures without clear recovery guidance.

## Option A: Startup checks only

Check local-server prerequisites during `./v3 start`, explain required failures and optional limitations, and let users complete setup manually before retrying.

- Pros: Smallest complete startup recovery flow; builds on the PHP fallback.
- Cons: Users must attempt startup to inspect dependencies; no dedicated diagnostic command.

## Option B: Shared checks for startup and a distinct command

Use the same local-server assessment for `./v3 start` and a proposed `./v3 check-dependencies` command that reports readiness without starting the server or changing application state.

- Pros: Consistent guidance; supports setup verification and unattended diagnostics; satisfies both requested entry points.
- Cons: Adds a command and reporting contract; must keep startup and diagnostic results aligned.

## Option C: Standalone diagnostic command only

Provide a local-server dependency report that users run before startup, while retaining the existing startup behavior.

- Pros: Offers an explicit setup check with limited startup changes.
- Cons: Users can skip it and encounter the same late failures; does not complete the requested startup protection.

## Recommendation

Choose **Option B**, with manual recovery guidance consistent with the PHP work.

- Required coverage: Git availability; a supported PHP version; PDO and PDO SQLite; mbstring; and process execution capabilities needed for server startup and local Git operations. Reconcile the documented PHP minimum with source compatibility before declaring it supported.
- Optional coverage: `stty`, PCNTL, and intl normalization. Explain reduced behavior and preserve plain logging, usable local operation, and reliable shutdown when optional capabilities are absent.
- Launcher prerequisites: document Bash and pre-check utilities such as `dirname` and `cat`; explain recovery outside the launcher when it cannot run its own diagnostics.
- Report dependency availability separately from repository, database, permissions, and runtime failures. Offer runtime-specific installation, enabling, or PATH guidance; never prompt or install automatically.
- Check required capabilities before avoidable startup mutations or server launch. The diagnostic command performs no bootstrap, rebuild, application writes, or server launch; required failures return an unsuccessful status, while optional limitations remain warnings.
- Complete vertical slice: run either entry point, identify a missing capability, follow manual guidance, and retry with the original startup options to reach a usable local server. Verify restored startup, ordinary local reads/writes, and shutdown, plus missing, disabled, and optional-capability cases.
- Keep the slice within one day or eight development stages. Broader command coverage, deployment checks, remote credentials, provider setup, and automatic installation are excluded.
