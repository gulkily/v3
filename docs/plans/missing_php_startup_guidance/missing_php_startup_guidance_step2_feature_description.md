> **Feature plan:** [Step 1](./missing_php_startup_guidance_step1_solution_assessment.md) · [Step 2](./missing_php_startup_guidance_step2_feature_description.md) · [Step 3](./missing_php_startup_guidance_step3_development_plan.md) · [Step 4](./missing_php_startup_guidance_step4_implementation_summary.md)

## Problem

Starting v3 without PHP currently produces a shell error with no recovery guidance. Users need a clear explanation and a path from missing prerequisite to successful local startup.

## User Stories

- As a new contributor, I want startup to explain that PHP CLI is required so that I can resolve the missing prerequisite.
- As a user with PHP installed outside PATH, I want guidance on making it available so that I can retry startup successfully.

## Core Requirements

- When PHP is unavailable on PATH, `./v3 start` reports the missing PHP CLI prerequisite clearly to standard error, exits unsuccessfully, and avoids the shell-level PHP-not-found error.
- The message explains installation or PATH correction, points to a stable project setup guide, and tells the user to retry their startup command.
- Guidance remains usable without PHP and platform-neutral; this release does not install software or request elevated privileges.
- Keep prerequisite detection distinct from recovery guidance so future, explicitly chosen installation assistance can extend this flow while retaining manual setup.
- With PHP available, preserve existing startup arguments and behavior; launcher help and PHP-independent commands remain usable without PHP.

## Delivery Scope

- **Work type:** Application change, with supporting setup documentation and focused verification.
- Deliver Option A for `./v3 start`, provisioned for Option C through separable recovery guidance and a stable setup reference. Platform-specific installers, PHP version/extension validation, and checks for other commands or dependencies are outside this slice.

## Completion Boundary

Normal entry is `./v3 start`, including existing startup options. The missing-PHP outcome is actionable guidance and a nonzero exit; recovery is manual installation or PATH correction followed by a successful retry. Release requires verifying the missing-PHP flow, startup with PHP, and unaffected help/PHP-independent behavior.

## Risks

- **Misleading diagnosis:** PHP may be installed but absent from PATH. Validate wording during Step 3 review; describe unavailability on PATH rather than asserting PHP is uninstalled.
- **Unusable recovery reference:** Guidance could depend on the runtime that is missing. Validate the destination during Step 3 review; extend README setup guidance readable directly from the checkout.
- **Future-installation scope growth:** Platform-specific assistance could delay this fix. Validate the completion contract during Step 3 review; limit provision to separable guidance and a stable documentation destination.

## Shared Component Inventory

- `v3`: Existing startup and terminal error surface; extend it for this prerequisite failure and preserve its help surface.
- `scripts/dev_server.php`: Existing normal startup flow; reuse it once PHP is available.
- `README.md` Local Run section: Existing setup surface; extend it with a stable PHP prerequisite/recovery reference.
- No existing browser UI or API renders this shell-level failure; no new UI or API is needed.

## Simple User Flow

1. Run the usual startup command without PHP available.
2. Read the prerequisite explanation and follow the setup reference.
3. Install PHP CLI or correct PATH, then retry the same command.
4. Start the existing local server successfully.

## Success Criteria

- Missing-PHP startup produces a nonzero exit and guidance naming PHP CLI, PATH, setup documentation, and retry, without the original shell error or server startup.
- After restoring PHP availability, the same startup command reaches the existing server flow with its arguments preserved.
- Help and a representative PHP-independent command remain usable without PHP; no installation is attempted.
