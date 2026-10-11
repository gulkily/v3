> **Feature plan:** [Step 1](./guided_php_setup_fallback_step1_solution_assessment.md) · [Step 2](./guided_php_setup_fallback_step2_feature_description.md) · [Step 3](./guided_php_setup_fallback_step3_development_plan.md) · [Step 4](./guided_php_setup_fallback_step4_implementation_summary.md)

## Original Query

Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md for the more comprehensive no-PHP fallback we discussed before.

## Understood Intent

Expand the implemented missing-PHP check and separate recovery guidance with platform-aware manual setup instructions. The selected approach is Option A below: users perform installation or PATH repair themselves, then retry startup.

## Problem Statement

The current startup fallback explains missing PHP and points to documentation, but users must still determine the appropriate setup steps and complete recovery themselves.

## Option A: Platform-aware manual setup guidance

Extend the existing fallback to recognize supported environments and present relevant installation or PATH-repair instructions, leaving users to perform setup and retry startup.

- Pros: Small scope; no system modifications by v3; useful in interactive and unattended environments.
- Cons: Users still carry out installation and retry manually; assistance stops short of completing recovery.

## Option B: Opt-in guided installation and startup recovery

Extend the fallback with a clearly explained installation offer for explicitly supported environments; after the user chooses it, attempt setup, verify PHP availability, and resume the original startup request. Keep manual guidance for declined, failed, unsupported, or unattended cases.

- Pros: Completes the missing-runtime-to-running-server flow; reuses existing detection and guidance; preserves user control.
- Cons: Requires bounded platform support, privilege handling, and failure recovery; installation may leave partial system changes that v3 cannot undo.

## Option C: Isolated runtime fallback

Offer an explicitly chosen isolated PHP runtime, such as a container or project-local runtime, to start v3 without a system PHP installation.

- Pros: Can avoid changing system PHP; may provide a predictable runtime environment.
- Cons: Adds another runtime acquisition or prerequisite problem; introduces networking, filesystem, lifecycle, and update responsibilities beyond the existing startup flow.

## Recommendation

Choose **Option A: platform-aware manual setup guidance**. Name the supported environments in Step 2 and retain the existing platform-neutral guidance for unrecognized environments.

- Complete vertical slice: Run `./v3 start` without PHP, receive relevant installation or PATH-repair instructions, complete setup manually, verify PHP availability, and retry the original command to start the existing server.
- Missing-PHP startup must exit unsuccessfully after displaying guidance, without prompting, installing packages, modifying PATH/shell profiles, or automatically retrying startup. The same guidance remains usable in unattended execution.
- Reuse the startup check, recovery guidance, and README setup reference. Explain that PHP may already be installed outside PATH before advising a new installation.
- Validate instructions for each supported environment and the runtime prerequisites they provide. Keep executable installation assistance, broader launcher coverage, and alternative runtime backends as separate follow-ups.
