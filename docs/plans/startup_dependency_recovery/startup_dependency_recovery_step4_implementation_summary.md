> **Feature plan:** [Step 1](./startup_dependency_recovery_step1_solution_assessment.md) · [Step 2](./startup_dependency_recovery_step2_feature_description.md) · [Step 3](./startup_dependency_recovery_step3_development_plan.md) · [Step 4](./startup_dependency_recovery_step4_implementation_summary.md)

Debug mode: enabled

Implementation in progress.

## Stage 1 - Actionable recovery guidance
- Changes: shared Ubuntu 22.04/PHP 8.1 and 24.04/PHP 8.3 mapping; verified package provenance and CLI ownership for extension commands; installed/disabled modules receive CLI-specific phpenmod/configuration guidance. Unverified sources, custom runtimes and PATH problems remain manual. Direct PHP probes no longer infer package installation merely from absent capabilities.
- Verification: shell dependency and installer suites; PHP syntax; real-runtime capability/color/NO_COLOR and 20 HTTP read/write/shutdown cases passed. Disposable Ubuntu 22.04 manual CLI install, missing-extension recipe, readiness, disabled mbstring diagnosis and manual enabling all passed. Transcripts: `/home/wsl/.cache/startup-dependency-recovery-planning/logs/`.
- Notes: corrected the older help fixture to use the canonical Railspike command; deprecated FDP intentionally writes a warning. Host packages unchanged. Actual automatic Jammy installation remains Stage 2.

## Stage 2 - Confirmed installation on both releases
- Changes: coordinator uses the shared release/CLI mapping for ownership, candidate sources, package selection, transaction allowlist and reassessment. Both explicit aliases retain exact-yes confirmation; help lists both supported releases. Cross-release APT sources are rejected.
- Verification: installer fixtures and changed-transaction confirmation passed. Real Ubuntu Base 22.04.5 and 24.04.3 chroots passed missing PHP/Git installation, separate optional selection/install, ready no-op, custom/configuration refusal, network/lock/partial failures, interruption cleanup, password-sudo install/interruption, and installed-runtime HTTP read/write/shutdown tests. Disabled installed modules refused reinstall and showed phpenmod guidance on both releases.
- Notes: concurrent chroots share the host PID view; fixed child-process assertions to scope by root filesystem. Runs resumed at the interrupted test boundary after that harness correction. Retained transcripts: `/home/wsl/.cache/startup-dependency-recovery-install-{22,24}-b/logs/`; final clean-image acceptance remains Stage 5. No host package changes.
