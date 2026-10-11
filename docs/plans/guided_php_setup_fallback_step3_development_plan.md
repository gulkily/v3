> **Feature plan:** [Step 1](./guided_php_setup_fallback_step1_solution_assessment.md) · [Step 2](./guided_php_setup_fallback_step2_feature_description.md) · [Step 3](./guided_php_setup_fallback_step3_development_plan.md) · [Step 4](./guided_php_setup_fallback_step4_implementation_summary.md)

## Completion Contract

- Entry/outcome: Missing-PHP `./v3 start` reports PATH recovery first, then Ubuntu 24.04/APT manual setup or generic guidance, on stderr with a nonzero exit and no shell-level PHP error.
- Recovery: Users repair PATH or install PHP manually, check availability, and retry their original command; the existing server responds with options preserved.
- Boundary: No prompts, package operations, shell-profile/PATH changes, or automatic retries. Existing-PHP startup and independent help stay unchanged; broader dependency work remains separate.
- Verification/release: Validate package instructions, exercise supported/generic detection and no-mutation behavior, and smoke-test a real local server after restoring PHP. No deployment, actual system installation, or external service is required. Record evidence and commit each completed stage with its Step 4 summary.
- Commit gate: After `Approved Step 3`, create a feature branch and first commit only approved Steps 1–3. Keep the dependency checklist outside these commits. At four artifacts, group this feature's plans and update plan navigation/index links.

## Key Risks

- **High risk:** Incorrect advice can leave users stuck. Before Stage 1, verify package/runtime coverage and PATH-first wording; Ubuntu lists [PHP CLI](https://packages.ubuntu.com/noble/php-cli), [SQLite support](https://packages.ubuntu.com/noble/php-sqlite3), and [mbstring](https://packages.ubuntu.com/noble/php8.3-mbstring). Use the matching PHP 8.3 package family and explain administrator privileges.
- Misclassification or missing tools can break fallback. Validate recognition fixtures before shipping; require Linux, exact Ubuntu 24.04 identity, and available APT. Parse release data without executing it; ambiguous, unreadable, or failed detection returns generic guidance.
- Accidental package execution would violate manual-only scope. Review command boundaries and test with failing package-manager stand-ins; render instructions as text only.

## Stage 1

- Goal: Deliver platform-aware guidance and a complete manual recovery path (≤1 hour).
- Dependencies: Approved Step 3, branch/planning commit, validated package coverage and recognition policy.
- Expected changes: Extend the launcher recovery flow with a reusable Bash guidance helper and README setup instructions. Internal helper signature: `php_setup_platform(os_release_path)` returns a supported platform or generic result; production supplies the system release file, while tests supply fixtures. Do not expose test overrides as launcher options.
- Verification approach: Shell syntax/whitespace checks; missing-PHP startup on the actual host; fixture checks for supported versus generic advice; review PATH-first wording, privileges, setup reference, and retry instructions. Confirm no installer commands run.
- Risks or open questions: Release-file quoting or duplicate identity fields could misclassify hosts; validate representative fixtures early and use generic guidance for ambiguous data. Confirm package modules cover PDO SQLite and mbstring before committing.
- Canonical components/API contracts touched: `v3` startup/recovery surface, new internal Bash helper under `scripts/`, and README PHP setup; existing server flow reused unchanged.

## Stage 2

- Goal: Lock down detection failures, unchanged commands, and successful recovery (≤1 hour).
- Dependencies: Stage 1 verification, summary update, and commit completed.
- Expected changes: Extend `tests/missing_php_startup_test.sh` with fixture-driven guidance checks and executable stand-ins; retain existing address/logging forwarding cases.
- Verification approach: Cover Ubuntu 24.04/APT, another release/distribution, missing/unreadable/malformed release data, detection-tool failure, and missing APT. Test piped startup returns without prompting; package-manager/sudo stand-ins must never execute. Restore a real temporary PHP runtime, retry startup, request a local HTTP asset, and stop the server. Check all plan/source links after grouping artifacts.
- Risks or open questions: Helper-only tests may miss launcher wiring; pair fixtures with actual launcher execution. A PHP stand-in verifies arguments only; retain real-server evidence and clean temporary processes/state.
- Canonical components/API contracts touched: Existing standalone shell regression test and launcher argument contract; no browser/API/database changes.
