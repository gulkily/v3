> **Feature plan:** [Step 1](./missing_php_startup_guidance_step1_solution_assessment.md) · [Step 2](./missing_php_startup_guidance_step2_feature_description.md) · [Step 3](./missing_php_startup_guidance_step3_development_plan.md) · [Step 4](./missing_php_startup_guidance_step4_implementation_summary.md)

## Original Query

I just tried to start v3 on a system without PHP installed, and it was not friendly:

```text
$ ./v3 start
./v3: line 195: exec: php: not found
```

Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md to improve on this.

## Problem Statement

When PHP is unavailable, `./v3 start` exits with a shell error that does not explain the prerequisite or help the user get the application running.

## Option A: Friendly prerequisite guidance for startup

Check whether PHP is available before starting the application, and explain that the PHP command-line runtime must be installed and available on PATH, with a pointer to setup guidance and instructions to retry.

- Pros: Directly fixes the reported experience; small, independently releasable scope; leaves installation under the user's control.
- Cons: Other PHP-dependent commands retain their existing missing-runtime behavior; users still perform installation themselves.

## Option B: Consistent prerequisite guidance across PHP-dependent commands

Provide the same missing-PHP explanation and recovery guidance for every launcher command that requires PHP, while keeping commands that do not need PHP usable.

- Pros: Consistent first-run experience across the launcher; prevents equivalent errors beyond startup.
- Cons: Broader command coverage and verification than the reported startup issue requires.

## Option C: Guided PHP installation during startup

Offer platform-specific installation assistance when startup finds PHP missing, then let the user retry startup after completing setup.

- Pros: More direct onboarding for users unfamiliar with PHP installation.
- Cons: Platform and package-manager differences increase maintenance; installation may require elevated permissions or environment-specific choices.

## Recommendation

Choose **Option A now, with provision for Option C later**. The complete vertical slice is: the user runs `./v3 start` without PHP, receives an actionable explanation and an unsuccessful exit without a shell-level error, installs the PHP CLI or corrects PATH, and retries successfully through the existing startup flow.

- Keep prerequisite detection distinct from recovery guidance so future installation assistance can extend the same missing-PHP flow.
- Keep the initial message concise and platform-neutral, pointing to a stable setup guide that can later include platform-specific installation assistance.
- Future guided installation should be an explicit user choice, with manual setup still available; do not install software or introduce platform-specific installers in this slice.
- Broader dependency checks and coverage of other launcher commands remain separate follow-up work.
