> **Feature plan:** [Step 1](./startup_dependency_recovery_step1_solution_assessment.md) · [Step 2](./startup_dependency_recovery_step2_feature_description.md) · [Step 3](./startup_dependency_recovery_step3_development_plan.md) · [Step 4](./startup_dependency_recovery_step4_implementation_summary.md)

Debug mode: enabled

Implementation in progress.

## Stage 1 - Actionable recovery guidance
- Changes: shared Ubuntu 22.04/PHP 8.1 and 24.04/PHP 8.3 mapping; verified package provenance and CLI ownership for extension commands; installed/disabled modules receive CLI-specific phpenmod/configuration guidance. Unverified sources, custom runtimes and PATH problems remain manual. Direct PHP probes no longer infer package installation merely from absent capabilities.
- Verification: shell dependency and installer suites; PHP syntax; real-runtime capability/color/NO_COLOR and 20 HTTP read/write/shutdown cases passed. Disposable Ubuntu 22.04 manual CLI install, missing-extension recipe, readiness, disabled mbstring diagnosis and manual enabling all passed. Transcripts: `/home/wsl/.cache/startup-dependency-recovery-planning/logs/`.
- Notes: corrected the older help fixture to use the canonical Railspike command; deprecated FDP intentionally writes a warning. Host packages unchanged. Actual automatic Jammy installation remains Stage 2.
