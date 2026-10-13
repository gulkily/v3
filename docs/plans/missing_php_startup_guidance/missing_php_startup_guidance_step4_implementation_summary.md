> **Feature plan:** [Step 1](./missing_php_startup_guidance_step1_solution_assessment.md) · [Step 2](./missing_php_startup_guidance_step2_feature_description.md) · [Step 3](./missing_php_startup_guidance_step3_development_plan.md) · [Step 4](./missing_php_startup_guidance_step4_implementation_summary.md)

## Stage 1 - Friendly startup prerequisite and recovery guidance

- Changes: Added a startup-only PHP availability check and separate recovery-message helper; added README PHP setup guidance. Grouped the four plan artifacts in their feature folder per FDP directory rules.
- Verification: `bash -n v3` and `git diff --check` passed. Controlled PATH retaining Bash, dirname, and cat but excluding PHP produced exit 1, empty stdout, and actionable stderr without the shell-level PHP error. Restoring PHP through an extracted temporary Ubuntu PHP CLI package allowed `./v3 start <port> --quiet` to serve `/assets/site.css` with HTTP 200; the server was stopped and temporary request state cleaned up.
- Notes: No system packages were installed. The initial dynamic-page probe returned 503 because the explicitly configured temporary repository did not exist; the successful static-asset probe validates server startup and HTTP serving, not application data configuration. No deployment, external services, or database changes were needed. Future installer assistance can extend the separate guidance flow; no installer was added.

## Stage 2 - Standalone launcher regression verification

- Changes: Added `tests/missing_php_startup_test.sh`, runnable with Bash without PHP, covering controlled missing-PHP failure, setup reference, PHP-independent help, and argument capture through a temporary PHP stand-in.
- Verification: `bash tests/missing_php_startup_test.sh`, `bash -n v3 tests/missing_php_startup_test.sh`, and `git diff --check` passed. Cases cover default address, numeric and colon-prefixed ports, listen-all, explicit address, quiet/verbose, and log-file paths containing spaces. Missing-PHP execution has only Bash, dirname, and cat available; it completes with the expected guidance and no attempted installer invocation.
- Notes: The stand-in verifies forwarding only; Stage 1 supplies real PHP server evidence. Temporary fixtures are removed on exit. Full PHP application tests are not applicable to this launcher-only change; production deployment, migrations, and external service checks are not required.
